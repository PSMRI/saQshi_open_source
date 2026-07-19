<?php

/**
 * Runs php -l over project PHP files.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$failures = [];

function phpSyntaxShouldSkip(string $relativePath): bool
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
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (phpSyntaxShouldSkip($relative)) {
        continue;
    }

    $command = 'php -l ' . escapeshellarg($path);
    exec($command, $output, $code);
    if ($code !== 0) {
        $failures[] = $relative . ': ' . implode(' ', $output);
    }
}

if ($failures === []) {
    echo "PHP syntax check passed.\n";
    exit(0);
}

echo "PHP syntax check failed:\n";
foreach ($failures as $failure) {
    echo "  {$failure}\n";
}

exit(1);
