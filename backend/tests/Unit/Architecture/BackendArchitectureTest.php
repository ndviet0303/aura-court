<?php

namespace Tests\Unit\Architecture;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Repo-wide rules from .agents/rules/backend-architecture.md. Files that broke a rule when it was
 * introduced are listed in architecture-allowlist.json as [rule, file, ceiling]; ceilings only go down.
 */
class BackendArchitectureTest extends TestCase
{
    private const LIMITS = [
        'class-lines' => 300,
        'controller-public-methods' => 7,
        'inline-validation' => 0,
        'storage-call' => 0,
        'model-query' => 0,
        'action-request' => 0,
        'mutable-dto' => 0,
        'form-request-shape' => 0,
        'unbound-repository' => 0,
    ];

    public function test_every_file_stays_within_its_rule_limit_or_allowlist_ceiling(): void
    {
        $values = $this->measure();
        $allowlist = json_decode(file_get_contents(__DIR__.'/architecture-allowlist.json'), true, flags: JSON_THROW_ON_ERROR);
        $ceilings = [];
        foreach ($allowlist as [$rule, $file, $ceiling]) {
            $ceilings["{$rule} {$file}"] = $ceiling;
        }
        $problems = [];

        foreach ($values as $key => $value) {
            [$rule, $file] = explode(' ', $key, 2);
            $limit = self::LIMITS[$rule];
            $ceiling = $ceilings[$key] ?? null;
            if ($value <= $limit) {
                continue;
            }
            if ($ceiling === null) {
                $problems[] = "{$file}: {$rule} is {$value}, limit {$limit}. Fix it, or allowlist it:\n  [\"{$rule}\", \"{$file}\", {$value}],";
            } elseif ($value > $ceiling) {
                $problems[] = "{$file}: {$rule} is {$value}, above its ceiling {$ceiling} (limit {$limit}).";
            }
        }

        foreach ($allowlist as [$rule, $file]) {
            if (! isset(self::LIMITS[$rule])) {
                $problems[] = "{$file}: unknown rule \"{$rule}\" in the allowlist.";
            } elseif (($values["{$rule} {$file}"] ?? 0) <= self::LIMITS[$rule]) {
                $problems[] = "{$file}: {$rule} is within its limit now. Remove its allowlist entry.";
            }
        }

        $this->assertSame('', implode("\n", $problems));
    }

    /**
     * @return array<string, int> keyed by "rule file", with the file relative to app/
     */
    private function measure(): array
    {
        $appDir = dirname(__DIR__, 3).'/app';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS)) as $info) {
            if ($info->getExtension() === 'php') {
                $files[] = substr($info->getPathname(), strlen($appDir) + 1);
            }
        }

        $models = array_map(fn ($file) => basename($file, '.php'), array_filter($files, fn ($file) => str_starts_with($file, 'Models/')));
        // The old scoped test's method list: calls such as with(), whereIn() or firstOrCreate() are not counted.
        $modelQuery = '/\b(?:'.implode('|', $models).')::(?:query|where|find|findOrFail|create|update|first|get|all|paginate)\b/';
        $provider = file_get_contents($appDir.'/Providers/RepositoryServiceProvider.php');

        $values = [];
        foreach ($files as $file) {
            $code = file_get_contents("{$appDir}/{$file}");
            $class = 'App\\'.str_replace('/', '\\', substr($file, 0, -4));
            $counts = ['class-lines' => $this->lineCount($code)];

            if (str_starts_with($file, 'Http/Controllers/')) {
                $counts['controller-public-methods'] = class_exists($class) ? $this->ownPublicMethods($class) : 0;
                $counts['inline-validation'] = substr_count($code, '$request->validate(');
                $counts['storage-call'] = substr_count($code, 'Storage::');
                $counts['model-query'] = preg_match_all($modelQuery, $code);
            } elseif (preg_match('#^Domain/[^/]+/Actions/#', $file)) {
                $counts['model-query'] = preg_match_all($modelQuery, $code);
                $counts['action-request'] = substr_count($code, 'use Illuminate\Http\Request;') + $this->requestParameters($class);
            } elseif (preg_match('#^Domain/[^/]+/DTOs/#', $file)) {
                $counts['mutable-dto'] = preg_match('/\breadonly\s+class\b/', $code) ? 0 : 1;
            } elseif (str_starts_with($file, 'Http/Requests/')) {
                $counts['form-request-shape'] = (str_contains($code, 'extends FormRequest') ? 0 : 1)
                    + (preg_match('/public\s+function\s+rules\s*\(/', $code) ? 0 : 1)
                    + (preg_match('/public\s+function\s+authorize\s*\(/', $code) ? 0 : 1);
            } elseif (str_starts_with($file, 'Contracts/Repositories/')) {
                $counts['unbound-repository'] = str_contains($provider, basename($file, '.php').'::class') ? 0 : 1;
            }

            foreach ($counts as $rule => $count) {
                $values["{$rule} {$file}"] = $count;
            }
        }

        return $values;
    }

    /** Editor lines: a trailing newline does not add one. */
    private function lineCount(string $code): int
    {
        return $code === '' ? 0 : substr_count($code, "\n") + (str_ends_with($code, "\n") ? 0 : 1);
    }

    private function ownPublicMethods(string $class): int
    {
        $methods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);

        return count(array_filter(
            $methods,
            fn ($method) => $method->getDeclaringClass()->getName() === $class && ! str_starts_with($method->getName(), '__'),
        ));
    }

    /** Parameters of execute() typed as an HTTP request or a FormRequest. */
    private function requestParameters(string $class): int
    {
        if (! class_exists($class) || ! method_exists($class, 'execute')) {
            return 0;
        }
        $types = array_map(fn ($parameter) => $parameter->getType(), (new ReflectionMethod($class, 'execute'))->getParameters());

        return count(array_filter($types, fn ($type) => $type instanceof ReflectionNamedType && is_a($type->getName(), Request::class, true)));
    }
}
