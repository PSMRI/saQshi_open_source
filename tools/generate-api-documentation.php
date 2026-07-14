<?php
/**
 * Generates a source-derived API reference.
 *
 * Run from the repository root:
 *   php tools/generate-api-documentation.php
 *
 * The generated Markdown is intentionally committed alongside the source so a
 * developer can inspect every API endpoint and supporting component without
 * manually building an inventory after each change.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$apiRoot = $root . DIRECTORY_SEPARATOR . 'api';
$output = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'source-reference.md';

/** @return list<string> */
function filesUnder(string $directory, string $extension): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === $extension) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

/** @return list<string> */
function matches(string $pattern, string $text, int $group = 1): array
{
    preg_match_all($pattern, $text, $found);
    $values = $found[$group] ?? [];
    $values = array_map(static fn ($value): string => trim((string) $value), $values);
    return array_values(array_unique(array_filter($values, static fn ($value): bool => $value !== '')));
}

function markdownList(array $items, string $empty = 'None detected.'): string
{
    if ($items === []) {
        return $empty;
    }

    return implode("\n", array_map(static fn ($item): string => '- `' . str_replace('`', '\\`', $item) . '`', $items));
}

function relativePath(string $path, string $root): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function classify(string $relative): string
{
    if (preg_match('#^api/[^/]+/v\d+/.+\.php$#', $relative) === 1) {
        return basename($relative)[0] === '_' ? 'Internal endpoint helper' : 'HTTP endpoint';
    }
    if (str_starts_with($relative, 'api/core/')) {
        return 'Core infrastructure';
    }
    if (str_starts_with($relative, 'api/service/')) {
        return 'Service layer';
    }
    if (str_starts_with($relative, 'api/config/')) {
        return 'Configuration';
    }
    if (str_starts_with($relative, 'api/assets/')) {
        return 'Runtime dependency';
    }
    return 'API support file';
}

$lines = [
    '# SaQshi API Source Reference',
    '',
    '> Generated from the current files by `tools/generate-api-documentation.php`.',
    '> Regenerate this file whenever API source, services, core classes, or configuration change.',
    '',
    '## Scope',
    '',
    'This reference covers every PHP file and JSON configuration file under `api/`. For each PHP file it records its role, HTTP-method guards, includes, declared classes/functions, request fields detected in code, database tables referenced, and emitted events. Source comments and endpoint-specific guides add the business explanation where present.',
    '',
    '## API routing and common execution path',
    '',
    '- `api/routes.php` reads `route` from the query string, protects non-public routes with `AuthMiddleware::check()`, and loads `api/<route>.php`.',
    '- Endpoint files commonly load `auth_api.php`, database connection code, and then use `Security`, `SessionManager`, and `Response` helpers.',
    '- Public endpoints are files under `api/<module>/v1/` that do not begin with `_`.',
    '',
];

$phpFiles = filesUnder($apiRoot, 'php');
$jsonFiles = filesUnder($apiRoot, 'json');

$groups = [];
foreach ($phpFiles as $path) {
    $relative = relativePath($path, $root);
    $groups[classify($relative)][] = [$path, $relative];
}

foreach ($groups as $type => $entries) {
    $lines[] = '## ' . $type;
    $lines[] = '';

    foreach ($entries as [$path, $relative]) {
        $source = file_get_contents($path);
        if ($source === false) {
            continue;
        }

        $methods = matches("/Security::requireMethod\\(\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $source);
        $includes = matches("/require(?:_once)?\\s*(?:\\(|)\\s*[^;]*?['\"]([^'\"]+\\.php)['\"]/", $source);
        $classes = matches('/\\bclass\\s+([A-Za-z_][A-Za-z0-9_]*)/', $source);
        $functions = matches('/\\bfunction\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*\\(([^)]*)\\)/', $source, 1);
        $requestFields = matches("/\\\$request\\s*\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]/", $source);
        $tables = matches('/\\b(?:FROM|JOIN|UPDATE|INTO|TABLE)\\s+`?([A-Za-z_][A-Za-z0-9_]*)`?/i', $source);
        $events = matches("~Event::dispatch\\(\\s*['\"]([^'\"]+)['\"]~", $source);
        $doc = '';
        if (preg_match('/\/\\*\\*(.*?)\\*\//s', $source, $block) === 1) {
            $doc = trim(preg_replace('/^\\s*\\* ?/m', '', $block[1]));
            $doc = preg_replace('/^-{3,}.*$/m', '', $doc) ?? '';
            $doc = trim($doc);
        }

        $lines[] = '### `' . $relative . '`';
        $lines[] = '';
        $lines[] = '- **Role:** ' . classify($relative);
        $lines[] = '- **HTTP method guard:** ' . ($methods === [] ? 'Not detected (internal/helper or legacy handling).' : '`' . implode('`, `', $methods) . '`');
        if ($doc !== '') {
            $lines[] = '- **Source intent:** ' . str_replace("\n", ' ', $doc);
        }
        $lines[] = '';
        $lines[] = '**Dependencies included**';
        $lines[] = '';
        $lines[] = markdownList($includes);
        $lines[] = '';
        $lines[] = '**Declared classes and functions**';
        $lines[] = '';
        $declarations = array_merge(array_map(static fn ($name): string => 'class ' . $name, $classes), array_map(static fn ($name): string => 'function ' . $name, $functions));
        $lines[] = markdownList($declarations);
        $lines[] = '';
        $lines[] = '**Request fields read from `$request`**';
        $lines[] = '';
        $lines[] = markdownList($requestFields);
        $lines[] = '';
        $lines[] = '**Database tables referenced**';
        $lines[] = '';
        $lines[] = markdownList($tables);
        $lines[] = '';
        $lines[] = '**Events dispatched**';
        $lines[] = '';
        $lines[] = markdownList($events);
        $lines[] = '';
    }
}

$lines[] = '## Configuration files';
$lines[] = '';
$lines[] = 'Configuration files define static behaviour, validation rules, master data, certification settings, and performance settings. They do not expose HTTP endpoints.';
$lines[] = '';

foreach ($jsonFiles as $path) {
    $relative = relativePath($path, $root);
    $raw = file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $kind = is_array($decoded) ? (array_is_list($decoded) ? 'list' : 'object') : 'invalid JSON';
    $keys = is_array($decoded) && !array_is_list($decoded) ? array_slice(array_keys($decoded), 0, 20) : [];
    $lines[] = '### `' . $relative . '`';
    $lines[] = '';
    $lines[] = '- **Role:** Configuration';
    $lines[] = '- **JSON shape:** ' . $kind;
    $lines[] = '- **Top-level keys:** ' . ($keys === [] ? 'Not applicable or list-based configuration.' : '`' . implode('`, `', $keys) . '`');
    $lines[] = '';
}

$lines[] = '## Maintaining this reference';
$lines[] = '';
$lines[] = 'After modifying an API file, service, core class or JSON configuration, run `php tools/generate-api-documentation.php`, review the generated diff, and update the relevant hand-written module guide with business rules, response examples and extension notes.';
$lines[] = '';

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0777, true);
}

file_put_contents($output, implode("\n", $lines));
echo 'Generated ' . relativePath($output, $root) . ' for ' . count($phpFiles) . ' PHP files and ' . count($jsonFiles) . " JSON files.\n";
