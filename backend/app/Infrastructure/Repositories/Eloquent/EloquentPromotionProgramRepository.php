<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Models\Court;
use App\Models\PromotionProgram;
use App\Models\VoucherCode;
use App\Models\VoucherUse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentPromotionProgramRepository implements PromotionProgramRepositoryInterface
{
    /** Upper case without I, O, 0 and 1, which read alike; 32^10 codes make a collision rare. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 10;

    private const CODE_ATTEMPTS = 5;

    public function forVenue(int $venueId): Collection
    {
        return PromotionProgram::query()
            ->with($this->courtsById())
            ->withCount($this->usageCounts())
            ->where('venue_id', $venueId)
            ->orderByDesc('id')
            ->get();
    }

    public function loadDetail(PromotionProgram $program): PromotionProgram
    {
        return $program->load($this->courtsById())->loadCount($this->usageCounts());
    }

    public function loadCodes(PromotionProgram $program): PromotionProgram
    {
        return $program->load([...$this->courtsById(), 'codes' => fn ($query) => $query->orderBy('id'), 'codes.activeUse.reservation:id,code']);
    }

    public function lockCourts(array $courtIds): Collection
    {
        return Court::query()->whereIn('id', $courtIds)->orderBy('id')->sharedLock()->get();
    }

    public function find(int $id): ?PromotionProgram
    {
        return PromotionProgram::query()->find($id);
    }

    public function findForUpdate(int $id): ?PromotionProgram
    {
        return PromotionProgram::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function findForShare(int $id): ?PromotionProgram
    {
        return PromotionProgram::query()->whereKey($id)->sharedLock()->first();
    }

    public function findCode(string $code): ?VoucherCode
    {
        return VoucherCode::query()->where('code', $code)->first();
    }

    public function giftableCodes(int $venueId, CarbonInterface $at): Collection
    {
        $at = CarbonImmutable::instance($at)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        return VoucherCode::query()
            ->with('program.courts')
            ->whereDoesntHave('activeUse')
            ->whereHas('program', fn ($program) => $program
                ->where('type', PromotionProgram::TYPE_PRIVATE)
                ->where('venue_id', $venueId)
                ->whereNull('stopped_at')
                ->where('starts_at', '<=', $at)
                ->where('ends_at', '>', $at))
            ->orderBy('id')
            ->get();
    }

    public function coversCourt(PromotionProgram $program, int $courtId): bool
    {
        return $program->courts()->whereKey($courtId)->exists();
    }

    public function runningPublicForCourt(int $courtId, CarbonInterface $at): Collection
    {
        // Program times are wall-clock on the application clock (D13), so $at is compared on that clock.
        $at = CarbonImmutable::instance($at)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        return PromotionProgram::query()
            ->where('type', PromotionProgram::TYPE_PUBLIC)
            ->whereNull('stopped_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->whereHas('courts', fn ($query) => $query->whereKey($courtId))
            ->get();
    }

    public function create(array $attributes, array $courtIds): PromotionProgram
    {
        $program = PromotionProgram::create($attributes);
        $program->courts()->attach($courtIds);

        return $program;
    }

    public function createCodes(PromotionProgram $program, int $count): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                // A savepoint, so a collision leaves the caller's transaction usable for the next attempt.
                DB::transaction(fn () => VoucherCode::insert($this->codeRows((int) $program->id, $count)));

                return;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::CODE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    public function update(PromotionProgram $program, array $attributes): PromotionProgram
    {
        $program->update($attributes);

        return $program;
    }

    public function syncCourts(PromotionProgram $program, array $courtIds): bool
    {
        $changes = $program->courts()->sync($courtIds);

        return $changes['attached'] !== [] || $changes['detached'] !== [];
    }

    /**
     * $count distinct codes; one insert stays under PostgreSQL's 65535 bound parameters up to 16000 codes.
     *
     * @return list<array{program_id: int, code: string, created_at: mixed, updated_at: mixed}>
     */
    private function codeRows(int $programId, int $count): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            // Keyed for uniqueness, valued so an all-digit code stays a string.
            $codes[$code] = $code;
        }
        $now = now();

        return array_map(fn (string $code): array => ['program_id' => $programId, 'code' => $code, 'created_at' => $now, 'updated_at' => $now], array_values($codes));
    }

    /** @return array<string, \Closure> */
    private function courtsById(): array
    {
        return ['courts' => fn ($query) => $query->orderBy('courts.id')];
    }

    /** @return array<string, \Closure> */
    private function usageCounts(): array
    {
        return [
            'uses as held_uses_count' => fn ($query) => $query->where('status', VoucherUse::STATUS_HELD),
            'uses as used_uses_count' => fn ($query) => $query->where('status', VoucherUse::STATUS_USED),
        ];
    }
}
