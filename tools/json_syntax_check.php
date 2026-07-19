<?php

/**
 * Validates project JSON files.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$failures = [];

function jsonSyntaxShouldSkip(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);
    foreach (['.git/', 'vendor/', 'node_modules/', 'uploads/', 'api/storage/'] as $prefix) {
        if (str_starts_with($relativePath, $prefix)) {
            return true;
        }
    }

    return false;
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (jsonSyntaxShouldSkip($relative)) {
        continue;
    }

    $content = file_get_contents($path);
    json_decode((string) $content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $failures[] = "{$relative}: " . json_last_error_msg();
    }
}

if ($failures === []) {
    echo "JSON syntax check passed.\n";
    exit(0);
}

echo "JSON syntax check failed:\n";
foreach ($failures as $failure) {
    echo "  {$failure}\n";
}

exit(1);
