<?php
/**
 * Adds missing lightweight PHPDoc comments to SaQshi API PHP files.
 *
 * This is a maintainer utility for documentation hygiene. It does not parse or
 * rewrite PHP logic; it only inserts comments immediately above class/function
 * declarations when a PHPDoc block is not already present.
 */

$root = realpath(__DIR__ . '/../api');
if ($root === false) {
    fwrite(STDERR, "API directory not found.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$changedFiles = 0;
$addedBlocks = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $source = file_get_contents($path);
    if ($source === false || $source === '') {
        continue;
    }

    $lines = preg_split("/(\r\n|\n|\r)/", $source);
    $lineEnding = str_contains($source, "\r\n") ? "\r\n" : "\n";
    $out = [];
    $changed = false;

    if (!hasFileHeader($lines)) {
        $lines = insertFileHeader($lines, $path, $root);
        $changed = true;
        $addedBlocks++;
    }

    foreach ($lines as $index => $line) {
        if (preg_match('/^(\s*)(?:(final|abstract)\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $match)) {
            if (!hasPhpDocBefore($out)) {
                $out = array_merge($out, classDocBlock($match[1], $match[3]));
                $changed = true;
                $addedBlocks++;
            }
        } elseif (preg_match('/^(\s*)(?:(public|protected|private)\s+)?(?:(static)\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $match)) {
            if (!hasPhpDocBefore($out)) {
                $out = array_merge($out, functionDocBlock($match[1], $match[4]));
                $changed = true;
                $addedBlocks++;
            }
        }

        $out[] = $line;
    }

    if ($changed) {
        file_put_contents($path, implode($lineEnding, $out));
        $changedFiles++;
    }
}

echo "Changed files: {$changedFiles}\n";
echo "Added PHPDoc blocks: {$addedBlocks}\n";

function hasPhpDocBefore(array $out): bool
{
    for ($i = count($out) - 1; $i >= 0; $i--) {
        $line = trim($out[$i]);
        if ($line === '') {
            continue;
        }

        return str_starts_with($line, '*/') || str_starts_with($line, '/**') || str_starts_with($line, '/*!');
    }

    return false;
}

function hasFileHeader(array $lines): bool
{
    $limit = min(8, count($lines));
    for ($i = 0; $i < $limit; $i++) {
        if (str_contains($lines[$i], '/**') || str_contains($lines[$i], '/*!')) {
            return true;
        }
    }

    return false;
}

function insertFileHeader(array $lines, string $path, string $root): array
{
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $title = readableName(pathinfo($path, PATHINFO_FILENAME));
    $header = [
        '/**',
        ' * SaQshi API',
        " * {$relative}",
        " * Purpose: {$title} endpoint/support workflow.",
        ' */',
        '',
    ];

    if (isset($lines[0]) && trim($lines[0]) === '<?php') {
        array_splice($lines, 1, 0, array_merge([''], $header));
        return $lines;
    }

    return array_merge($header, $lines);
}

function classDocBlock(string $indent, string $className): array
{
    $label = readableName($className);

    return [
        $indent . '/**',
        $indent . " * Provides {$label} behavior for SaQshi API workflows.",
        $indent . ' */',
    ];
}

function functionDocBlock(string $indent, string $functionName): array
{
    $label = readableName($functionName);

    return [
        $indent . '/**',
        $indent . " * Handles {$label} processing for this API workflow.",
        $indent . ' */',
    ];
}

function readableName(string $name): string
{
    $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
    $name = str_replace('_', ' ', (string) $name);
    $name = trim(strtolower((string) $name));

    return $name === '' ? 'request' : $name;
}
