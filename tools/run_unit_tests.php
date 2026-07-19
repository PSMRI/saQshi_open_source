<?php

/**
 * Minimal dependency-free unit test runner.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$testsDir = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit';
if (!is_dir($testsDir)) {
    echo "No unit tests found.\n";
    exit(0);
}

$GLOBALS['SAQSHI_TESTS'] = [
    'passed' => 0,
    'failed' => 0,
];

function sqAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sqAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function sqTest(string $name, callable $callback): void
{
    try {
        $callback();
        $GLOBALS['SAQSHI_TESTS']['passed']++;
        echo "PASS {$name}\n";
    } catch (Throwable $throwable) {
        $GLOBALS['SAQSHI_TESTS']['failed']++;
        echo "FAIL {$name}: {$throwable->getMessage()}\n";
    }
}

$files = glob($testsDir . DIRECTORY_SEPARATOR . '*Test.php') ?: [];
sort($files);

foreach ($files as $file) {
    require $file;
}

$passed = $GLOBALS['SAQSHI_TESTS']['passed'];
$failed = $GLOBALS['SAQSHI_TESTS']['failed'];
echo "Unit tests completed: {$passed} passed, {$failed} failed.\n";

exit($failed > 0 ? 1 : 0);
