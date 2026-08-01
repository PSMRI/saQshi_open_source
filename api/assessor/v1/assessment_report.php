<?php

/*! SaQshi Open Source | Assessor Assessment CSV Report */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    $rows = (new AssessorService($con))->assessmentReportRows(
        SessionManager::userId(),
        SessionManager::username()
    );

    if (strtolower((string)($_GET['format'] ?? '')) === 'json') {
        $domainPath = __DIR__ . '/../../config/domain.json';
        $domainConfig = is_file($domainPath) ? json_decode((string)file_get_contents($domainPath), true) : [];
        $domain = (string)($domainConfig['domain'] ?? 'healthcare');
        Response::success('Assessment report loaded', [
            'rows' => $rows,
            'domain' => $domain,
            'facility_code_label' => $domain === 'education' ? 'UDISE Code' : 'NIN'
        ]);
        exit;
    }

    $filename = 'saqshi-assessment-report-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Facility / School', 'NIN / UDISE', 'District', 'Block', 'Assessment', 'Framework', 'Status', 'Start Date', 'End Date', 'Saved Checkpoints', 'Total Checkpoints', 'Score %'], ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['fac_name'], $row['fac_code'], $row['district'], $row['block'],
            $row['assessment_name'], $row['framework_code'], $row['status'],
            $row['start_date'], $row['end_date'], $row['saved_checkpoints'],
            $row['total_checkpoints'], $row['score_percent']
        ], ',', '"', '\\');
    }
    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unable to generate assessment report.']);
}
