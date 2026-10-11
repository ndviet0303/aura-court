<?php

namespace Tests\Unit\Architecture;

use App\Providers\RepositoryServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ScopedBackendArchitectureTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = realpath(__DIR__.'/../../../app') ?: throw new \RuntimeException('Cannot locate app directory');
    }

    /**
     * Verify all scoped controllers adhere to:
     * - Max 300 lines of code.
     * - Max 7 public methods.
     * - No inline validation ($request->validate).
     * - No direct Eloquent Model queries (must use Repositories/Services).
     * - No direct Storage facade calls.
     */
    public function test_scoped_controllers_conform_to_thin_orchestrator_rules(): void
    {
        $controllerDirs = [
            $this->basePath.'/Http/Controllers/Partner',
            $this->basePath.'/Http/Controllers/Subscription',
        ];

        $files = $this->collectPhpFiles($controllerDirs);
        if (empty($files)) {
            $this->assertTrue(true, 'No scoped controllers to check yet.');

            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = count(explode("\n", $content));
            $className = $this->extractFullyQualifiedClassName($file);

            $this->assertLessThanOrEqual(
                300,
                $lines,
                "Controller [{$file}] exceeds 300 lines (current: {$lines}). Decompose to maintain thin orchestrators."
            );

            $this->assertStringNotContainsString(
                '$request->validate(',
                $content,
                "Controller [{$file}] contains inline validation. Use a FormRequest instead."
            );

            $this->assertStringNotContainsString(
                'Storage::',
                $content,
                "Controller [{$file}] interacts with Storage directly. Use an injected storage service contract."
            );

            // Check for forbidden static Eloquent queries in controllers
            $this->assertNoDirectEloquentQueries($content, $file);

            if ($className && class_exists($className)) {
                $ref = new ReflectionClass($className);
                $publicMethods = array_filter(
                    $ref->getMethods(ReflectionMethod::IS_PUBLIC),
                    fn ($m) => $m->getDeclaringClass()->getName() === $className && ! str_starts_with($m->getName(), '__')
                );

                $this->assertLessThanOrEqual(
                    7,
                    count($publicMethods),
                    "Controller [{$className}] has more than 7 public methods (current: ".count($publicMethods).').'
                );
            }
        }
    }

    /**
     * Verify all scoped domain Actions adhere to:
     * - Max 300 lines of code.
     * - No Request or FormRequest in method parameters.
     * - No direct Model static queries (must inject Repository Interfaces).
     * - Single responsibility (execute method).
     */
    public function test_scoped_actions_conform_to_clean_architecture_rules(): void
    {
        $actionDirs = [
            $this->basePath.'/Domain/Partner/Actions',
            $this->basePath.'/Domain/Subscription/Actions',
            $this->basePath.'/Domain/Venue/Actions',
        ];

        $files = $this->collectPhpFiles($actionDirs);
        if (empty($files)) {
            $this->assertTrue(true, 'No scoped actions to check yet.');

            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = count(explode("\n", $content));
            $className = $this->extractFullyQualifiedClassName($file);

            $this->assertLessThanOrEqual(
                300,
                $lines,
                "Action [{$file}] exceeds 300 lines (current: {$lines}). Split into smaller use cases."
            );

            // Actions must not import or accept HTTP Requests
            $this->assertStringNotContainsString(
                'use Illuminate\\Http\\Request;',
                $content,
                "Action [{$file}] must not depend on Illuminate\\Http\\Request. Actions accept DTOs or primitives."
            );

            // Check for forbidden static Eloquent queries in actions
            $this->assertNoDirectEloquentQueries($content, $file);

            if ($className && class_exists($className)) {
                $ref = new ReflectionClass($className);
                if ($ref->hasMethod('execute')) {
                    $method = $ref->getMethod('execute');
                    foreach ($method->getParameters() as $param) {
                        $type = $param->getType();
                        if ($type && method_exists($type, 'getName')) {
                            $typeName = $type->getName();
                            $this->assertFalse(
                                is_subclass_of($typeName, 'Illuminate\Http\Request') || $typeName === 'Illuminate\Http\Request',
                                "Action [{$className}::execute] parameter \${$param->getName()} accepts a Request object [{$typeName}]. Use a readonly DTO."
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Verify all scoped DTOs are readonly classes.
     */
    public function test_scoped_dtos_are_readonly_classes(): void
    {
        $dtoDirs = [
            $this->basePath.'/Domain/Partner/DTOs',
            $this->basePath.'/Domain/Subscription/DTOs',
            $this->basePath.'/Domain/Venue/DTOs',
        ];

        $files = $this->collectPhpFiles($dtoDirs);
        if (empty($files)) {
            $this->assertTrue(true, 'No scoped DTOs to check yet.');

            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = count(explode("\n", $content));

            $this->assertLessThanOrEqual(
                300,
                $lines,
                "DTO [{$file}] exceeds 300 lines (current: {$lines})."
            );

            $this->assertMatchesRegularExpression(
                '/\breadonly\s+class\b/',
                $content,
                "DTO [{$file}] must be declared as a 'readonly class'."
            );
        }
    }

    /**
     * Verify all scoped FormRequests extend FormRequest and have authorize/rules methods.
     */
    public function test_scoped_form_requests_have_proper_structure(): void
    {
        $requestDirs = [
            $this->basePath.'/Http/Requests/Partner',
            $this->basePath.'/Http/Requests/Subscription',
            $this->basePath.'/Http/Requests/Venue',
        ];

        $files = $this->collectPhpFiles($requestDirs);
        if (empty($files)) {
            $this->assertTrue(true, 'No scoped FormRequests to check yet.');

            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                'extends FormRequest',
                $content,
                "Request [{$file}] must extend Illuminate\\Foundation\\Http\\FormRequest."
            );

            $this->assertMatchesRegularExpression(
                '/public\s+function\s+rules\s*\(/',
                $content,
                "Request [{$file}] must define a public rules() method."
            );

            $this->assertMatchesRegularExpression(
                '/public\s+function\s+authorize\s*\(/',
                $content,
                "Request [{$file}] must define a public authorize() method."
            );
        }
    }

    /**
     * Verify all repository interfaces in Contracts/Repositories are bound in RepositoryServiceProvider.
     */
    public function test_repository_contracts_are_bound_in_service_provider(): void
    {
        $providerContent = file_get_contents($this->basePath.'/Providers/RepositoryServiceProvider.php');

        $repoContractsDir = $this->basePath.'/Contracts/Repositories';
        $contractFiles = $this->collectPhpFiles([$repoContractsDir]);

        foreach ($contractFiles as $file) {
            $interfaceName = basename($file, '.php');
            $this->assertStringContainsString(
                $interfaceName.'::class',
                $providerContent,
                "Repository interface [{$interfaceName}] is not registered in RepositoryServiceProvider."
            );
        }
    }

    private function assertNoDirectEloquentQueries(string $content, string $filePath): void
    {
        $disallowedPatterns = [
            '/\b(HostKyc|Organization|OrganizationMember|Venue|Court|PlatformPlan|PlatformSubscription|User)::(query|where|find|findOrFail|create|update|first|get|all|paginate)\b/',
        ];

        foreach ($disallowedPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $this->fail(
                    "Direct Eloquent query found in [{$filePath}]: '{$matches[0]}'. ".
                    'Domain Actions and Controllers must interact with database records exclusively through Repository contracts.'
                );
            }
        }
    }

    /**
     * @param  array<string>  $directories
     * @return array<string>
     */
    private function collectPhpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                    $files[] = $fileInfo->getPathname();
                }
            }
        }

        return $files;
    }

    private function extractFullyQualifiedClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $m)) {
            $namespace = trim($m[1]);
        }
        if (preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $m)) {
            $class = trim($m[1]);
        }

        return ($namespace && $class) ? "{$namespace}\\{$class}" : null;
    }
}
