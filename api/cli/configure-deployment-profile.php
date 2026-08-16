<?php

/**
 * First-deployment profile selector.
 * Usage: php api/cli/configure-deployment-profile.php --profile=healthcare
 */
require_once dirname(__DIR__) . '/service/DeploymentConfigService.php';

$allowed = ['healthcare', 'education', 'generic-inspection'];
$profile = '';
foreach (($argv ?? []) as $argument) {
    if (str_starts_with($argument, '--profile=')) $profile = substr($argument, 10);
}

if ($profile === '') {
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "This setup command must run from the command line.\n");
        exit(2);
    }
    echo "Select deployment profile:\n1. Healthcare quality assessment\n2. Education / school assessment\n3. Generic inspection / audit\n";
    echo 'Enter 1, 2, or 3: ';
    $selection = trim((string)fgets(STDIN));
    $profile = ['1' => 'healthcare', '2' => 'education', '3' => 'generic-inspection'][$selection] ?? '';
}

if (!in_array($profile, $allowed, true)) {
    fwrite(STDERR, 'Invalid profile. Choose: ' . implode(', ', $allowed) . ".\n");
    exit(2);
}

try {
    DeploymentConfigService::applyProfile($profile, 0);
    echo "Applied '$profile' deployment profile. Review api/config/domain.json and api/config/modules.json before starting the web server.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not apply deployment profile: ' . $e->getMessage() . "\n");
    exit(1);
}
