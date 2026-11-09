<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * add-local-api-docs D7: the Scramble docs must stay a local-only tool. Two layers keep them off
 * production: the package is dev-only and the image installs without dev packages, and no code
 * defines the gate that would let Scramble open outside APP_ENV=local.
 */
class ApiDocsExposureTest extends TestCase
{
    public function test_scramble_is_a_dev_dependency_only(): void
    {
        $composer = json_decode(file_get_contents($this->backend().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('dedoc/scramble', $composer['require-dev']);
        $this->assertArrayNotHasKey('dedoc/scramble', $composer['require']);
    }

    public function test_backend_image_installs_without_dev_dependencies(): void
    {
        $this->assertStringContainsString('composer install --no-dev', file_get_contents($this->backend().'/Dockerfile'));
    }

    public function test_no_code_defines_the_docs_gate(): void
    {
        $backend = $this->backend();
        $files = [$backend.'/bootstrap/app.php'];
        foreach (['app', 'routes'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($backend.'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $info) {
                if ($info->getExtension() === 'php') {
                    $files[] = $info->getPathname();
                }
            }
        }

        $hits = array_values(array_filter($files, fn (string $file) => str_contains(file_get_contents($file), 'viewApiDocs')));

        $this->assertSame([], $hits, 'Defining the viewApiDocs gate opens /docs/api outside APP_ENV=local.');
    }

    private function backend(): string
    {
        return dirname(__DIR__, 3);
    }
}
