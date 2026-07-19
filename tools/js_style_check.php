<?php

/**
 * Lightweight JavaScript style checker for SaQshi.
 *
 * This dependency-free checker catches common issues until the project adopts
 * a full ESLint configuration.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$strict = in_array('--strict', $argv, true);
$issues = [];

function jsStyleShouldSkip(string $relativePath): bool
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
    new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . 'ui', FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (jsStyleShouldSkip($relative)) {
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
        if (preg_match('/(^|[^\w$])var\s+/', $line) === 1) {
            $issues[] = "WARN {$relative}:{$lineNumber} uses var; prefer const or let";
        }
        $previousLine = $lines[$index - 1] ?? '';
        $isDebugGuarded = str_contains($previousLine, 'debug') || str_contains($previousLine, 'APP.debug') || str_contains($previousLine, 'API.debug');
        if (!$isDebugGuarded && preg_match('/console\.log\s*\(/', $line) === 1) {
            $issues[] = "WARN {$relative}:{$lineNumber} contains console.log";
        }
    }
}

if ($issues === []) {
    echo "JavaScript style check passed.\n";
    exit(0);
}

echo "JavaScript style check completed with findings:\n";
foreach ($issues as $issue) {
    echo "  {$issue}\n";
}

exit($strict ? 1 : 0);
