<?php
/**
 * SaQshi release readiness checker.
 *
 * Run from the open_source directory:
 *   php tools/release_readiness_check.php
 *
 * The checker is intentionally conservative. A warning does not always mean
 * the project is broken; it means a release owner should review the item
 * before publishing the repository publicly.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$errors = [];
$warnings = [];

function rr_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

function rr_warning(array &$warnings, string $message): void
{
    $warnings[] = $message;
}

function rr_should_skip_file(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);
    $skipPrefixes = [
        'uploads/',
        'api/storage/',
        '.git/',
        'node_modules/',
        'vendor/',
        '.codex_tmp/',
    ];

    foreach ($skipPrefixes as $prefix) {
        if (str_starts_with($relativePath, $prefix)) {
            return true;
        }
    }

    return (bool) preg_match('/\.(png|jpg|jpeg|gif|webp|ico|pdf|docx|xlsx|xls|zip|rar|7z|gz)$/i', $relativePath);
}

function rr_is_private_artifact(string $relativePath): bool
{
    return (bool) preg_match('/\.(zip|rar|7z|bak|backup|dump|sql\.gz|docx|xlsx|xls)$/i', $relativePath);
}

function rr_asset_path_to_file(string $root, string $assetPath): ?string
{
    $pathOnly = preg_replace('/[?#].*$/', '', trim($assetPath));
    if ($pathOnly === '' || preg_match('/^https?:\/\//i', $pathOnly)) {
        return null;
    }

    $pathOnly = str_replace('\\', '/', $pathOnly);
    if (str_starts_with($pathOnly, '/assets/')) {
        $relative = 'ui' . $pathOnly;
    } elseif (str_starts_with($pathOnly, '/ui/')) {
        $relative = ltrim($pathOnly, '/');
    } else {
        $relative = 'ui/' . ltrim($pathOnly, '/');
    }

    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

$requiredFiles = [
    'LICENSE',
    'NOTICE',
    'README.md',
    'CONTRIBUTING.md',
    'CODE_OF_CONDUCT.md',
    'MAINTAINERS.md',
    'SECURITY.md',
    'CHANGELOG.md',
    '.env.example',
    'docs/compliance/release_checklist.md',
    'docs/compliance/third_party_licenses.md',
    'docs/compliance/dpg_readiness_assessment.md',
    'docs/compliance/legal_privacy_confirmation.md',
    'docs/database/database_setup_and_migration.md',
    'docs/security/production_hardening.md',
    'docs/security/role_access_matrix.md',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        rr_error($errors, "Required release file missing: {$file}");
    }
}

if (is_file($root . DIRECTORY_SEPARATOR . '.env')) {
    rr_warning($warnings, "Local .env exists. Keep it untracked and never publish it.");
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'api/sql/schema/001_base_schema.sql')) {
    rr_warning($warnings, "Sanitized base schema not found at api/sql/schema/001_base_schema.sql.");
}

if (is_file($root . DIRECTORY_SEPARATOR . 'api/config/masters/facilities.json')) {
    rr_warning($warnings, "Confirm redistribution approval for real facility master data: api/config/masters/facilities.json.");
}

$dataApproval = $root . DIRECTORY_SEPARATOR . 'docs/compliance/data_redistribution_approval.md';
if (is_file($dataApproval)) {
    $approvalText = file_get_contents($dataApproval);
    if ($approvalText !== false && stripos($approvalText, 'Status: Pending') !== false) {
        rr_warning($warnings, "Data redistribution approval is still pending: docs/compliance/data_redistribution_approval.md.");
    }
}

$maintainersFile = $root . DIRECTORY_SEPARATOR . 'MAINTAINERS.md';
if (is_file($maintainersFile)) {
    $maintainersText = file_get_contents($maintainersFile);
    if ($maintainersText !== false && stripos($maintainersText, 'Pending') !== false) {
        rr_warning($warnings, "Maintainer/security/release contacts are still pending: MAINTAINERS.md.");
    }
}

$legalPrivacyFile = $root . DIRECTORY_SEPARATOR . 'docs/compliance/legal_privacy_confirmation.md';
if (is_file($legalPrivacyFile)) {
    $legalPrivacyText = file_get_contents($legalPrivacyFile);
    if ($legalPrivacyText !== false && stripos($legalPrivacyText, 'Status: Pending') !== false) {
        rr_warning($warnings, "Legal/privacy confirmation is still pending: docs/compliance/legal_privacy_confirmation.md.");
    }
}

$uiPagesDirectory = $root . DIRECTORY_SEPARATOR . 'ui/pages';
if (is_dir($uiPagesDirectory)) {
    $pageFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uiPagesDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($pageFiles as $pageFile) {
        if (!$pageFile->isFile() || strtolower($pageFile->getExtension()) !== 'json') {
            continue;
        }

        $json = json_decode((string) file_get_contents($pageFile->getPathname()), true);
        if (!is_array($json)) {
            continue;
        }

        foreach (['css', 'js'] as $assetType) {
            $assets = $json['assets'][$assetType] ?? [];
            if (!is_array($assets)) {
                continue;
            }

            foreach ($assets as $assetPath) {
                if (!is_string($assetPath)) {
                    continue;
                }

                $localFile = rr_asset_path_to_file($root, $assetPath);
                if ($localFile !== null && !is_file($localFile)) {
                    $relativePage = str_replace('\\', '/', substr($pageFile->getPathname(), strlen($root) + 1));
                    rr_warning($warnings, "Page asset reference is missing: {$assetPath} declared in {$relativePage}");
                }
            }
        }
    }
}

$runtimeDataChecks = [
    'uploads' => '/^(?!README\.md$).+/i',
    'api/storage/events' => '/\.(log|json|txt)$/i',
    'api/storage/logs' => '/\.(log|json|txt)$/i',
    'api/storage/keys' => '/\.(pem|key|crt|cer|p12|pfx)$/i',
];

foreach ($runtimeDataChecks as $relativeDirectory => $pattern) {
    $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    if (!is_dir($directory)) {
        continue;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($items as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        $fileName = $item->getFilename();
        if (preg_match($pattern, $fileName) && $relativePath !== 'uploads/README.md' && $relativePath !== 'api/storage/README.md') {
            rr_warning($warnings, "Runtime/private data file must not be published: {$relativePath}");
        }
    }
}

$previousDirectory = getcwd();
chdir($root);
$fileList = [];
$rgExitCode = 1;
exec('rg --files', $fileList, $rgExitCode);
if ($previousDirectory !== false) {
    chdir($previousDirectory);
}

if ($rgExitCode !== 0 || !$fileList) {
    rr_warning($warnings, "Unable to run rg --files. Install ripgrep or review files manually.");
    $fileList = $requiredFiles;
}

$secretPatterns = [
    '/DB_PASSWORD\s*=\s*(?!change_me|""|\'\')\S+/i' => 'Possible database password',
    '/password\s*[:=]\s*[\'"][^\'"]{6,}[\'"]/i' => 'Possible hardcoded password',
    '/api[_-]?key\s*[:=]\s*[\'"][^\'"]{10,}[\'"]/i' => 'Possible API key',
    '/secret\s*[:=]\s*[\'"][^\'"]{10,}[\'"]/i' => 'Possible secret value',
    '/-----BEGIN (RSA |EC |OPENSSH |)PRIVATE KEY-----/' => 'Private key material',
];

$rawErrorExposurePatterns = [
    '/Response::error\s*\(\s*\$e->getMessage\s*\(/' => 'Possible raw exception returned to API user',
    '/Response::error\s*\([^;]*(\$con->error|\$stmt->error|mysqli_error\s*\()/s' => 'Possible raw database error returned to API user',
    '/\b(echo|print|die|exit)\s*\([^;]*(\$e->getMessage\s*\(|\$con->error|\$stmt->error|mysqli_error\s*\()/s' => 'Possible raw error output',
];

foreach ($fileList as $relative) {
    $relative = str_replace('\\', '/', trim((string) $relative));
    if ($relative === '') {
        continue;
    }

    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        continue;
    }

    $size = filesize($path);
    if ($size === false) {
        rr_warning($warnings, "Unable to read file size: {$relative}");
        continue;
    }

    if (rr_is_private_artifact($relative)) {
        rr_warning($warnings, "Review/remove private or binary release artifact: {$relative}");
    }

    if ($size > 1048576) {
        rr_warning($warnings, "Review large release file over 1 MB: {$relative}");
        continue;
    }

    if (rr_should_skip_file($relative)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        rr_warning($warnings, "Unable to read file during scan: {$relative}");
        continue;
    }

    foreach ($secretPatterns as $pattern => $label) {
        if (preg_match($pattern, $content)) {
            rr_warning($warnings, "{$label} in {$relative}");
        }
    }

    foreach ($rawErrorExposurePatterns as $pattern => $label) {
        if (preg_match($pattern, $content)) {
            rr_warning($warnings, "{$label} in {$relative}");
        }
    }
}

echo "SaQshi Release Readiness Check\n";
echo "==============================\n\n";

if ($errors) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  [FAIL] {$error}\n";
    }
    echo "\n";
}

if ($warnings) {
    echo "Warnings:\n";
    foreach ($warnings as $warning) {
        echo "  [REVIEW] {$warning}\n";
    }
    echo "\n";
}

if (!$errors && !$warnings) {
    echo "[OK] No release blockers or review warnings found.\n";
}

echo "Result: " . ($errors ? 'FAILED' : 'PASSED_WITH_REVIEW') . "\n";
exit($errors ? 1 : 0);
