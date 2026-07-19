<?php

/**
 * Lightweight PHP style checker for SaQshi.
 *
 * This is intentionally dependency-free. It does not replace PHPCS/PHP CS Fixer,
 * but it provides a practical project quality gate for common style issues.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$strict = in_array('--strict', $argv, true);
$issues = [];

function phpStyleShouldSkip(string $relativePath): bool
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
    if (phpStyleShouldSkip($relative)) {
        continue;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $issues[] = "ERROR {$relative}: unable to read file";
        continue;
    }

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        if (str_contains($line, "\t")) {
            $issues[] = "WARN {$relative}:{$lineNumber} contains tab indentation";
        }
        if (preg_match('/\s+$/', $line) === 1) {
            $issues[] = "WARN {$relative}:{$lineNumber} has trailing whitespace";
        }
    }

    $content = implode("\n", $lines);
    if (preg_match('/\?>\s*$/', $content) === 1) {
        $issues[] = "WARN {$relative}: remove closing PHP tag in PHP-only files";
    }
}

if ($issues === []) {
    echo "PHP style check passed.\n";
    exit(0);
}

echo "PHP style check completed with findings:\n";
foreach ($issues as $issue) {
    echo "  {$issue}\n";
}

exit($strict ? 1 : 0);
