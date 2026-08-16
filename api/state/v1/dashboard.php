<?php

/*! SaQshi Open Source | State Dashboard API | dashboard.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    Event::dispatch('state.dashboard.viewed', ['user_id' => SessionManager::userId()]);

    $facilityCategory = StateDashboardService::facilityCategory($con, $_GET);
    $certificationSummary = ['total' => 0, 'status' => [], 'map_points' => []];
    $currentMonthStatus = [
        'assessment' => ['started' => 0, 'in_progress' => 0, 'completed' => 0],
        'performance' => ['kpi_filled' => 0, 'outcome_filled' => 0]
    ];

    try {
        $certificationSummary = StateDashboardService::certificationSummary($con, $_GET);
    } catch (Throwable $certificationError) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log('State dashboard certification summary failed', [
                'error' => $certificationError->getMessage()
            ]);
        }
    }

    try {
        $currentMonthStatus = StateDashboardService::currentMonthStatus($con, $_GET);
    } catch (Throwable $monthStatusError) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log('State dashboard current month status failed', [
                'error' => $monthStatusError->getMessage()
            ]);
        }
    }
    $assessmentSummary = [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    $schoolCategorySummary = [
        'total_scored' => 0,
        'categories' => []
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

    try {
        $schoolCategorySummary = StateDashboardService::schoolCategorySummary($con, $_GET);
    } catch (Throwable $categoryError) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::log('State dashboard school category summary failed', [
                'error' => $categoryError->getMessage()
            ]);
        }
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
        'school_category_summary' => $schoolCategorySummary,
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
