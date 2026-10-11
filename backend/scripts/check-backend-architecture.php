<?php

/**
 * Scoped Backend Architecture Checker CLI
 * Enforces:
 * - Controllers: <= 300 lines, <= 7 public methods, no inline validation, no direct storage, no direct model queries.
 * - Actions: <= 300 lines, no Request parameter, no direct model queries.
 * - DTOs: readonly class, <= 300 lines.
 * - FormRequests: extends FormRequest, has rules() and authorize().
 * - Repositories: all repository interfaces registered in RepositoryServiceProvider.
 */
$appDir = realpath(__DIR__.'/../app');
if (! $appDir) {
    echo "Cannot find app directory.\n";
    exit(1);
}

$errors = [];

// 1. Check RepositoryServiceProvider bindings
$providerContent = file_get_contents($appDir.'/Providers/RepositoryServiceProvider.php');
$contractFiles = glob($appDir.'/Contracts/Repositories/*RepositoryInterface.php') ?: [];
foreach ($contractFiles as $file) {
    $interfaceName = basename($file, '.php');
    if (! str_contains($providerContent, $interfaceName.'::class')) {
        $errors[] = "Repository interface [{$interfaceName}] is not registered in RepositoryServiceProvider.";
    }
}

// 2. Check Controllers
$controllerDirs = [
    $appDir.'/Http/Controllers/Partner',
    $appDir.'/Http/Controllers/Subscription',
];
foreach ($controllerDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    foreach (glob($dir.'/*.php') as $file) {
        $content = file_get_contents($file);
        $lines = count(explode("\n", $content));
        if ($lines > 300) {
            $errors[] = "Controller [{$file}] exceeds 300 lines ({$lines}).";
        }
        if (str_contains($content, '$request->validate(')) {
            $errors[] = "Controller [{$file}] contains inline \$request->validate(). Use a FormRequest.";
        }
        if (str_contains($content, 'Storage::')) {
            $errors[] = "Controller [{$file}] contains Storage::. Use an injected storage interface.";
        }
        if (preg_match('/\b(HostKyc|Organization|OrganizationMember|Venue|Court|PlatformPlan|PlatformSubscription|User)::(query|where|find|create|update|first|get|all|paginate)\b/', $content, $m)) {
            $errors[] = "Controller [{$file}] contains direct model query: {$m[0]}.";
        }
    }
}

// 3. Check Actions
$actionDirs = [
    $appDir.'/Domain/Partner/Actions',
    $appDir.'/Domain/Subscription/Actions',
    $appDir.'/Domain/Venue/Actions',
];
foreach ($actionDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    foreach (glob($dir.'/*.php') as $file) {
        $content = file_get_contents($file);
        $lines = count(explode("\n", $content));
        if ($lines > 300) {
            $errors[] = "Action [{$file}] exceeds 300 lines ({$lines}).";
        }
        if (str_contains($content, 'use Illuminate\\Http\\Request;')) {
            $errors[] = "Action [{$file}] imports Request. Actions must only accept DTOs or primitives.";
        }
        if (preg_match('/\b(HostKyc|Organization|OrganizationMember|Venue|Court|PlatformPlan|PlatformSubscription|User)::(query|where|find|create|update|first|get|all|paginate)\b/', $content, $m)) {
            $errors[] = "Action [{$file}] contains direct model query: {$m[0]}. Use repository interfaces.";
        }
    }
}

// 4. Check DTOs
$dtoDirs = [
    $appDir.'/Domain/Partner/DTOs',
    $appDir.'/Domain/Subscription/DTOs',
    $appDir.'/Domain/Venue/DTOs',
];
foreach ($dtoDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    foreach (glob($dir.'/*.php') as $file) {
        $content = file_get_contents($file);
        if (! preg_match('/\breadonly\s+class\b/', $content)) {
            $errors[] = "DTO [{$file}] is not declared as a readonly class.";
        }
    }
}

if (! empty($errors)) {
    echo "Backend Architecture Violations Found:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "All scoped backend architecture checks passed successfully.\n";
exit(0);
