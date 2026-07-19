<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Chat Data Service
 * ChatDataService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/StateDashboardService.php';
require_once __DIR__ . '/AssessorService.php';

class ChatDataService
{
    public static function answer(mysqli $con, array $intent, string $message, int $userId, int $facId): ?string
    {
        $tool = (string)($intent['data_tool'] ?? '');

        try {
            return match ($tool) {
                'current_month_status' => self::currentMonthStatus($con),
                'pending_cqi_status' => self::pendingCqiStatus($con),
                'facility_report' => self::facilityReport($con, $message, $userId, $facId),
                default => null,
            };
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log('Chat data service failed', [
                    'tool' => $tool,
                    'message' => $e->getMessage()
                ]);
            }

            return "I could not load that live data right now. Please try again, or open the related dashboard/report page.";
        }
    }

    private static function currentMonthStatus(mysqli $con): string
    {
        $filters = self::monitoringFilters();
        $status = StateDashboardService::currentMonthStatus($con, $filters);
        $cqi = StateDashboardService::cqiSummary($con, $filters);
        $scope = (string)($filters['_scope_label'] ?? 'your scope');
        $assessment = $status['assessment'] ?? [];
        $performance = $status['performance'] ?? [];

        return implode("\n", [
            "Current month status for {$scope}:",
            '',
            '- Assessment started: ' . (int)($assessment['started'] ?? 0),
            '- Assessment in progress: ' . (int)($assessment['in_progress'] ?? 0),
            '- Assessment completed: ' . (int)($assessment['completed'] ?? 0),
            '- KPI filled: ' . (int)($performance['kpi_filled'] ?? 0),
            '- Outcome filled: ' . (int)($performance['outcome_filled'] ?? 0),
            '- Facilities with pending action plan/gap work: ' . (int)($cqi['pending'] ?? 0),
            '- Gap closure/action overdue: ' . (int)($cqi['overdue'] ?? 0)
        ]);
    }

    private static function pendingCqiStatus(mysqli $con): string
    {
        $filters = self::monitoringFilters();
        $cqi = StateDashboardService::cqiSummary($con, $filters);
        $scope = (string)($filters['_scope_label'] ?? 'your scope');

        return implode("\n", [
            "CQI status for {$scope}:",
            '',
            '- Facilities having action plans: ' . (int)($cqi['facilities_with_action_plan'] ?? 0),
            '- Total action plans: ' . (int)($cqi['total_action_plans'] ?? 0),
            '- Completed: ' . (int)($cqi['completed'] ?? 0),
            '- Pending: ' . (int)($cqi['pending'] ?? 0),
            '- Overdue: ' . (int)($cqi['overdue'] ?? 0),
            '',
            'Open CQI Monitoring for facility-wise details.'
        ]);
    }

    private static function facilityReport(mysqli $con, string $message, int $userId, int $facId): string
    {
        $role = ChatIntentService::roleKey((int)SessionManager::roleId());
        $facility = null;

        if ($role === 'facility') {
            $facility = ['fac_id' => $facId];
        } elseif ($role === 'assessor') {
            $facility = self::findAssignedAssessorFacility($con, $message, $userId);
        } else {
            $facility = self::findScopedMonitoringFacility($message);
        }

        if (!$facility || (int)($facility['fac_id'] ?? 0) <= 0) {
            return 'I could not find that facility inside your assigned scope. Try the facility name or NIN, or open Facility Drill-down.';
        }

        $detail = StateDashboardService::facilityDetail($con, (int)$facility['fac_id']);
        if (!$detail) {
            return 'Facility summary is not available right now.';
        }

        return self::formatFacilityDetail($detail);
    }

    private static function formatFacilityDetail(array $detail): string
    {
        $facility = $detail['facility'] ?? [];
        $summary = $detail['summary'] ?? [];
        $assessment = $summary['assessments'] ?? [];
        $performance = $summary['performance'] ?? [];
        $cqi = $summary['cqi'] ?? [];
        $assessments = $detail['assessments'] ?? [];
        $latest = $assessments[0] ?? [];
        $name = trim((string)($facility['fac_name'] ?? $latest['fac_name'] ?? 'Selected facility'));

        return implode("\n", [
            "{$name} facility summary:",
            '',
            'Assessment:',
            '- Latest assessment: ' . self::dash($latest['assessment_name'] ?? ''),
            '- Status: ' . self::dash($latest['status'] ?? ''),
            '- Total assessments: ' . (int)($assessment['total'] ?? count($assessments)),
            '- Completed: ' . (int)($assessment['completed'] ?? 0),
            '- In progress: ' . (int)($assessment['in_progress'] ?? 0),
            '',
            'CQI:',
            '- Open/pending gaps or actions: ' . (int)($cqi['pending'] ?? 0),
            '- Completed: ' . (int)($cqi['completed'] ?? 0),
            '- Overdue: ' . (int)($cqi['overdue'] ?? 0),
            '',
            'Performance:',
            '- KPI months/entries: ' . self::dash($performance['kpi_months'] ?? $performance['kpi_entries'] ?? 0),
            '- Outcome months/entries: ' . self::dash($performance['outcome_months'] ?? $performance['outcome_entries'] ?? 0),
            '',
            'Open facility detail or reports for full records.'
        ]);
    }

    private static function findScopedMonitoringFacility(string $message): ?array
    {
        $filters = self::monitoringFilters();
        $facilities = self::flattenHierarchy(StateDashboardService::facilityHierarchy($filters));
        return self::matchFacility($facilities, $message);
    }

    private static function findAssignedAssessorFacility(mysqli $con, string $message, int $userId): ?array
    {
        $service = new AssessorService($con);
        $data = $service->mappedFacilitiesForUser($userId, SessionManager::username());
        $facilities = array_map(function (array $row): array {
            return [
                'fac_id' => (int)($row['fac_id'] ?? 0),
                'fac_name' => (string)($row['fac_name'] ?? ''),
                'NIN_no' => (string)($row['fac_nin'] ?? $row['NIN_no'] ?? '')
            ];
        }, $data['facilities'] ?? []);

        return self::matchFacility($facilities, $message);
    }

    private static function monitoringFilters(): array
    {
        if (!in_array((int)SessionManager::roleId(), [4, 5, 8, 9], true)) {
            return [];
        }

        return StateDashboardService::applyMonitoringScope([]);
    }

    private static function flattenHierarchy(array $hierarchy): array
    {
        $rows = [];

        foreach (($hierarchy['states'] ?? $hierarchy) as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            $rows[] = $facility;
                        }
                    }
                }
            }
        }

        return $rows;
    }

    private static function matchFacility(array $facilities, string $message): ?array
    {
        $text = strtolower($message);
        preg_match_all('/\d{4,}/', $message, $numbers);
        $digits = $numbers[0] ?? [];

        foreach ($facilities as $facility) {
            $nin = (string)($facility['NIN_no'] ?? $facility['fac_nin'] ?? '');
            foreach ($digits as $needle) {
                if ($nin !== '' && str_contains($nin, $needle)) {
                    return $facility;
                }
            }
        }

        $clean = trim((string)preg_replace('/\b(show|report|facility|status|details|summary|of|for|please|the|nin)\b/i', ' ', $message));
        $clean = strtolower((string)preg_replace('/\s+/', ' ', $clean));

        foreach ($facilities as $facility) {
            $name = strtolower((string)($facility['fac_name'] ?? $facility['facility_name'] ?? ''));
            if ($name !== '' && ($clean !== '' && (str_contains($name, $clean) || str_contains($clean, $name)))) {
                return $facility;
            }
        }

        return count($facilities) === 1 ? $facilities[0] : null;
    }

    private static function dash(mixed $value): string
    {
        $text = trim((string)$value);
        return $text === '' ? '-' : $text;
    }
}
