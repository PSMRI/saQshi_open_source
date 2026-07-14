<?php

/*! SaQshi Open Source | State Dashboard API | dashboard.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Event::dispatch('state.dashboard.viewed', ['user_id' => SessionManager::userId()]);

    $facilityCategory = StateDashboardService::facilityCategory($con, $_GET);
    $certificationSummary = StateDashboardService::certificationSummary($con, $_GET);
    $currentMonthStatus = StateDashboardService::currentMonthStatus($con, $_GET);
    $assessmentSummary = [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];

    try {
        $assessmentSummary = StateDashboardService::assessmentProgress($con, $_GET, true);
    } catch (Throwable $assessmentError) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log('State dashboard assessment summary failed', [
                'error' => $assessmentError->getMessage()
            ]);
        }

        $assessmentSummary['_error'] = 'Assessment summary could not be loaded.';
    }

    Response::success('State dashboard loaded', [
        'filters' => [
            'state_code' => (string)($_GET['state_code'] ?? ''),
            'division' => (string)($_GET['division'] ?? ''),
            'district' => (string)($_GET['district'] ?? ''),
            'block' => (string)($_GET['block'] ?? ''),
            'facility_type' => (string)($_GET['facility_type'] ?? ''),
            'month' => (string)($_GET['month'] ?? ''),
            'year' => (string)($_GET['year'] ?? '')
        ],
        'facility_category' => $facilityCategory,
        'certification_summary' => $certificationSummary,
        'assessment_summary' => $assessmentSummary,
        'cqi_summary' => [
            'total_action_plans' => 0,
            'completed' => 0,
            'pending' => 0,
            'overdue' => 0,
            'rows' => []
        ],
        'performance_summary' => $currentMonthStatus['performance'],
        'current_month_status' => $currentMonthStatus,
        'attention' => [],
        'attention_summary' => []
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
