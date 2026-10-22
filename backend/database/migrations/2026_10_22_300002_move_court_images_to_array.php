<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * D11 expand step (Migration Plan step 4, "Ảnh"): a court holds a list of images like a venue. Copies the legacy
 * single image_url into courts.images, then drops from courts.images and venues.images every entry the
 * StoredImageUrl rule would reject: anything that is neither an upload on the FileStorageService image disk nor a
 * built-in /courts/<file>.jpg preset present in public/courts. image_url stays until the contract step; the
 * pre-migration backup keeps the dropped links.
 */
return new class extends Migration
{
    private const PRESET_PATTERN = '#^/courts/[A-Za-z0-9_-]+\.jpg$#';

    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->json('images')->nullable()->default('[]');
        });

        DB::statement("UPDATE courts SET images = json_build_array(btrim(image_url)) WHERE btrim(image_url) <> ''");

        [$keep, $bindings] = $this->storedImagePredicate();
        foreach (['courts', 'venues'] as $table) {
            DB::statement(
                "UPDATE {$table} t SET images = (
                    SELECT COALESCE(json_agg(e.value ORDER BY e.ord), '[]'::json)
                    FROM json_array_elements_text(t.images) WITH ORDINALITY AS e(value, ord)
                    WHERE {$keep}
                )
                WHERE json_typeof(t.images) = 'array'",
                $bindings,
            );
        }
    }

    public function down(): void
    {
        // Best effort: the first image becomes image_url again; a court left without images keeps its old image_url.
        DB::statement('UPDATE courts SET image_url = images->>0 WHERE length(images->>0) BETWEEN 1 AND 255');

        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }

    /**
     * SQL keeping e.value when StoredImageUrl would accept it, with its bindings. The disk base URLs come from the
     * config of the environment running the migration, as FileStorageService::isStoredUrl reads them.
     *
     * @return array{string, list<string>}
     */
    private function storedImagePredicate(): array
    {
        $disk = config('filesystems.disks.r2.key') ? 'r2' : (config('filesystems.disks.s3.key') ? 's3' : 'public');
        $bases = [];
        foreach (array_unique([$disk, 'public']) as $name) {
            try {
                $base = Storage::disk($name)->url('');
            } catch (\Throwable) {
                continue; // As isStoredUrl: a disk that cannot be built (no S3 adapter installed) holds no upload.
            }
            if (preg_match('#^https?://[^/]+/#', $base) === 1) {
                $bases[] = $base;
            }
        }
        $presets = array_values(array_filter(
            array_map(fn (string $file) => '/courts/'.basename($file), glob(public_path('courts/*.jpg')) ?: []),
            fn (string $path) => preg_match(self::PRESET_PATTERN, $path) === 1,
        ));

        $conditions = array_map(fn () => 'starts_with(e.value, ?)', $bases);
        if ($presets !== []) {
            $conditions[] = 'e.value IN ('.implode(', ', array_fill(0, count($presets), '?')).')';
        }

        return [$conditions === [] ? 'false' : implode(' OR ', $conditions), [...$bases, ...$presets]];
    }
};
