<?php

/** Role-11-only view of assessor credential delivery records. */
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/Crypto.php';

if (SessionManager::roleId() !== 11) {
    Response::forbidden('Credential delivery records are available only to role 11.');
}

Security::requireMethod('GET');

$search = trim((string)($_GET['search'] ?? ''));
$path = dirname(__DIR__, 2) . '/storage/notifications/email.log';
$rows = [];
$seenUsernames = [];
$profileQuery = $con->prepare("SELECT am.assessor_name, am.mobile_no, am.mail_id,
    GROUP_CONCAT(DISTINCT CONCAT_WS(' - ', f.fac_name, f.Dist_Name) ORDER BY f.fac_name SEPARATOR ', ') AS mapped_schools
    FROM assessor_master am
    INNER JOIN s_user u ON u.u_id = am.user_id
    LEFT JOIN assessor_facility_mapping afm ON afm.assessor_id = am.assessor_id AND afm.assignment_status = 'ACTIVE'
    LEFT JOIN facilities f ON f.fac_id = afm.fac_id
    WHERE u.u_name = ? AND u.password_must_change = 1
    GROUP BY am.assessor_id
    LIMIT 1");

if (is_file($path) && is_readable($path)) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach (array_reverse($lines) as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry) || (($entry['meta']['template'] ?? '') !== 'assessor_login')) {
            continue;
        }

        $message = (string)($entry['message'] ?? '');
        if (!preg_match('/Username:\s*([^\s]+)/i', $message, $usernameMatch)
            || !preg_match('/Temporary password:\s*([^\s]+)/i', $message, $passwordMatch)) {
            continue;
        }

        // The log can contain multiple delivery attempts for one assessor.
        // It is read newest first, so retain only the latest entry per username.
        $usernameKey = strtolower($usernameMatch[1]);
        if (isset($seenUsernames[$usernameKey])) {
            continue;
        }
        $seenUsernames[$usernameKey] = true;

        $profile = [];
        if ($profileQuery) {
            $username = $usernameMatch[1];
            $profileQuery->bind_param('s', $username);
            $profileQuery->execute();
            $result = $profileQuery->get_result();
            $profile = $result ? ($result->fetch_assoc() ?: []) : [];
        }

        // Once the assessor changes the initial password, it must no longer
        // appear in this temporary-credential list.
        if (!$profile) {
            continue;
        }

        $row = [
            'username' => $usernameMatch[1],
            'name' => Crypto::decrypt($profile['assessor_name'] ?? ''),
            'mobile_no' => Crypto::decrypt($profile['mobile_no'] ?? ''),
            'mapped_schools' => (string)($profile['mapped_schools'] ?? ''),
            'temporary_password' => $passwordMatch[1],
            'email' => Crypto::decrypt($profile['mail_id'] ?? '') ?: (string)($entry['to'] ?? ''),
            'created_at' => (string)($entry['created_at'] ?? ''),
            'status' => (string)($entry['status'] ?? '')
        ];

        $haystack = strtolower(implode(' ', $row));
        if ($search !== '' && !str_contains($haystack, strtolower($search))) {
            continue;
        }

        $rows[] = $row;
        if (count($rows) >= 1000) {
            break;
        }
    }
}

if ($profileQuery) {
    $profileQuery->close();
}

Response::success('Credential delivery records loaded.', [
    'rows' => $rows,
    'limit' => 1000
]);
