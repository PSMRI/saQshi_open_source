<?php

/**
 * SaQshi quality gate.
 *
 * Runs syntax, lightweight style, JSON validation, unit tests and release
 * readiness checks in one command.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$commands = [
    ['PHP syntax', 'php tools/php_syntax_check.php'],
    ['JSON syntax', 'php tools/json_syntax_check.php'],
    ['PHP style', 'php tools/php_style_check.php'],
    ['JavaScript style', 'php tools/js_style_check.php'],
    ['Unit tests', 'php tools/run_unit_tests.php'],
    ['Release readiness', 'php tools/release_readiness_check.php'],
];

$failed = false;
foreach ($commands as [$label, $command]) {
    echo "\n== {$label} ==\n";
    passthru($command, $code);
    if ($code !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
