<?php

/*! SaQshi Open Source | Assessor Assessment CSV Report */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    $domainPath = __DIR__ . '/../../config/domain.json';
    $domainConfig = is_file($domainPath) ? json_decode((string)file_get_contents($domainPath), true) : [];
    $labels = is_array($domainConfig['labels'] ?? null) ? $domainConfig['labels'] : [];
    $facilityLabel = (string)($labels['facility'] ?? 'Facility');
    $facilityCodeLabel = (string)($labels['facility_code'] ?? 'NIN');

    $rows = (new AssessorService($con))->assessmentReportRows(
        SessionManager::userId(),
        SessionManager::username()
    );

    if (strtolower((string)($_GET['format'] ?? '')) === 'json') {
        $domain = (string)($domainConfig['domain'] ?? 'healthcare');
        Response::success('Assessment report loaded', [
            'rows' => $rows,
            'domain' => $domain,
            'facility_code_label' => $facilityCodeLabel
        ]);
        exit;
    }

    $filename = 'saqshi-assessment-report-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');

    $output = fopen('php://output', 'w');
    fputcsv($output, [$facilityLabel, $facilityCodeLabel, 'District', 'Block', 'Assessment', 'Framework', 'Assessor Name', 'Assessor Code', 'Class / Department', 'Status', 'Start Date', 'Planned End Date', 'Actual Completion Date', 'Cancellation Date', 'Saved Checkpoints', 'Total Checkpoints', 'Score %'], ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['fac_name'], $row['fac_code'], $row['district'], $row['block'],
            $row['assessment_name'], $row['framework_code'], $row['assessor_name'] ?? '', $row['assessor_code'] ?? '', $row['classes'] ?? '', $row['status'],
            $row['start_date'], $row['end_date'], $row['completed_on'], $row['cancelled_on'], $row['saved_checkpoints'],
            $row['total_checkpoints'], $row['score_percent']
        ], ',', '"', '\\');
    }
    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Unable to generate assessment report.']);
}
