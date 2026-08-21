<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Report Service
 * StateReportService.php
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

require_once __DIR__ . '/StateDashboardService.php';
require_once __DIR__ . '/../core/Crypto.php';

/**
 * Provides state report service behavior for SaQshi API workflows.
 */
class StateReportService extends StateDashboardService
{
    /**
     * Handles stream csv processing for this API workflow.
     */
    public static function streamCsv(mysqli $con, string $report, array $filters = []): void
    {
        $report = strtolower(trim($report));
        $filename = 'saqshi-state-' . str_replace('_', '-', $report ?: 'summary') . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        try {
            match ($report) {
                'facilities' => self::writeFacilities($con, $out, $filters),
                'assessments' => self::writeAssessments($con, $out, $filters),
                'class_assessment_scores' => self::writeClassAssessmentScores($con, $out, $filters),
                'assessor_activity' => self::writeAssessorActivity($con, $out, $filters),
                'cqi' => self::writeCqi($con, $out, $filters),
                'performance' => self::writePerformance($con, $out, $filters),
                'certification' => self::writeCertification($con, $out, $filters),
                default => self::writeSummary($con, $out, $filters),
            };
        } catch (Throwable $e) {
            self::csvRow($out, ['Export Error', $e->getMessage()]);
        }

        fclose($out);
        exit;
    }

    /**
     * Handles export catalog processing for this API workflow.
     */
    public static function exportCatalog(): array
    {
        $labels = self::domainLabels();
        $summaryAreas = [$labels['facilities'], $labels['assessments']];
        if (self::moduleEnabled('cqi')) {
            $summaryAreas[] = 'CQI';
        }
        if (self::moduleEnabled('performance')) {
            $summaryAreas[] = 'performance';
        }
        if (self::moduleEnabled('certification')) {
            $summaryAreas[] = 'certification';
        }

        $assessmentDescription = 'All ' . $labels['assessment'] . ' records with status, '
            . $labels['departments'] . ', checkpoints';
        if (self::moduleEnabled('cqi')) {
            $assessmentDescription .= ', action plans';
        }
        $assessmentDescription .= ' and score fields.';

        $reports = [
            ['key' => 'summary', 'title' => 'State Summary', 'description' => 'Summary counts for ' . self::humanList($summaryAreas) . '.'],
            ['key' => 'facilities', 'title' => 'All ' . $labels['facility'] . ' List', 'description' => $labels['facility'] . ' master list with state, division, district, block, ' . $labels['facility'] . ' type, ' . $labels['facility_code'] . ' and coordinates.'],
            ['key' => 'assessments', 'title' => 'Assessment Details', 'description' => $assessmentDescription],
            ['key' => 'class_assessment_scores', 'title' => 'Class-wise Assessment Scores', 'description' => 'One row per assessed class with indicator, marks, status and configured domain-wise percentage scores.'],
            ['key' => 'assessor_activity', 'title' => $labels['assessor'] . ' Activity', 'description' => 'Completed assessment count and assessment-level details for each ' . $labels['assessor'] . '.'],
        ];

        if (self::moduleEnabled('cqi')) {
            $reports[] = ['key' => 'cqi', 'title' => 'CQI Details', 'description' => 'Action plan and gap closure extract with responsible person, target date and revised score.'];
        }
        if (self::moduleEnabled('performance')) {
            $reports[] = ['key' => 'performance', 'title' => 'Performance Details', 'description' => 'Performance entries with month, numerator, denominator, result and remarks.'];
        }
        if (self::moduleEnabled('certification')) {
            $reports[] = ['key' => 'certification', 'title' => 'Certification History', 'description' => $labels['facility'] . ' certification history with decoded status, dates and score.'];
        }
        return $reports;
    }

    /**
     * Handles write summary processing for this API workflow.
     */
    private static function writeSummary(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        $facility = self::facilityCategory($con, $filters);
        $assessment = self::assessmentProgress($con, $filters, true);
        $cqi = self::moduleEnabled('cqi') ? self::cqiSummary($con, $filters) : [];
        $performance = self::moduleEnabled('performance') ? self::performanceSummary($con, $filters) : [];
        $certification = self::moduleEnabled('certification') ? self::certificationSummary($con, $filters) : [];

        self::csvRow($out, ['Report', 'Metric', 'Value']);
        self::csvRow($out, [$labels['facilities'], 'Total ' . $labels['facilities'], $facility['total_facilities'] ?? 0]);
        foreach (($facility['facility_types'] ?? []) as $row) {
            self::csvRow($out, [$labels['facilities'], $labels['facility'] . ' Type - ' . ($row['facility_type'] ?? ''), $row['count'] ?? 0]);
        }
        self::csvRow($out, [$labels['assessments'], 'Total', $assessment['total'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Active', $assessment['active'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Completed', $assessment['completed'] ?? 0]);
        self::csvRow($out, [$labels['assessments'], 'Cancelled', $assessment['cancelled'] ?? 0]);
        if (self::moduleEnabled('cqi')) {
            self::csvRow($out, ['CQI', $labels['facilities'] . ' With Action Plan', $cqi['facilities_with_action_plan'] ?? 0]);
            self::csvRow($out, ['CQI', 'Completed', $cqi['completed'] ?? 0]);
            self::csvRow($out, ['CQI', 'Pending', $cqi['pending'] ?? 0]);
            self::csvRow($out, ['CQI', 'Overdue', $cqi['overdue'] ?? 0]);
        }
        if (self::moduleEnabled('performance')) {
            self::csvRow($out, ['Performance', 'Facilities', $performance['summary']['facilities'] ?? 0]);
            self::csvRow($out, ['Performance', 'Performance Entries', $performance['summary']['performance_entries'] ?? 0]);
            self::csvRow($out, ['Performance', 'Submitted Months', $performance['summary']['submitted_months'] ?? 0]);
        }
        if (self::moduleEnabled('certification')) {
            self::csvRow($out, ['Certification', 'Total', $certification['total'] ?? 0]);
            foreach (($certification['status'] ?? []) as $row) {
                self::csvRow($out, ['Certification', 'Status - ' . ($row['status'] ?? ''), $row['count'] ?? 0]);
            }
        }
    }

    /**
     * Handles write facilities processing for this API workflow.
     */
    private static function writeFacilities(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        self::csvRow($out, [
            'State', 'Division', 'District', 'Block', $labels['facility'] . ' Name',
            $labels['facility'] . ' Type', $labels['facility_code'], 'Latitude', 'Longitude', 'Active'
        ]);

        $masterRows = self::facilityMasterRows();
        if ($masterRows !== []) {
            foreach ($masterRows as $row) {
                if (!self::matchesMasterFilters($row, $filters)) {
                    continue;
                }
                self::csvRow($out, [
                    $row['state_name'], $row['division'], $row['district'],
                    $row['block'], $row['fac_name'], self::facilityTypeName($row['facility_type'] ?? ''), $row['nin_no'],
                    $row['latitude'], $row['longitude'], '1'
                ]);
            }
            return;
        }

        if (!self::tableExistsLocal($con, 'facilities')) {
            self::csvRow($out, ['Facilities table is not available.']);
            return;
        }

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.fac_id, f.state_name, f.division, f.Dist_Name, f.Block_Name, f.fac_name,
                   f.Health_facilty_type, f.NIN_no, f.lat, f.longit, f.is_active
            FROM facilities f
            {$where['sql']}
            ORDER BY f.state_name, f.division, f.Dist_Name, f.Block_Name, f.fac_name
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['state_name'] ?? '', $row['division'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['fac_name'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''), $row['NIN_no'] ?? '', $row['lat'] ?? '',
                $row['longit'] ?? '', $row['is_active'] ?? ''
            ];
        });
    }

    /**
     * Handles write assessments processing for this API workflow.
     */
    private static function writeAssessments(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_master')) {
            self::csvRow($out, ['Assessment table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            $labels['facility_code'], $labels['facility'], 'District', 'Block', 'Assessment ID',
            'Assessment Name', 'Framework', 'Start Date', 'Planned End Date', 'Actual Completion Date', 'Cancellation Date', 'Status',
            $labels['assessor'] . ' Name', 'Class / Subject Teacher Name', 'Teacher ID', 'Subject', 'Class Section',
            'Checkpoint Done', 'Original Score', 'Final Score', 'Action Plans',
            'Completed Action Plans', 'Last Updated'
        ]);

        $responseTable = self::responseTable($con);
        $responseJoin = '';
        if ($responseTable) {
            $assessmentColumn = self::columnExistsLocal($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
            $finalScore = self::tableExistsLocal($con, 'assessment_action_plan')
                ? 'COALESCE(ap.revised_score, r.score)'
                : 'r.score';
            $actionJoinForScore = self::tableExistsLocal($con, 'assessment_action_plan')
                ? "LEFT JOIN assessment_action_plan ap ON ap.assessment_id = r.{$assessmentColumn} AND ap.dept_id = r.dept_id AND ap.checkpoint_id = r.checkpoint_id"
                : '';
            $responseJoin = "
                LEFT JOIN (
                    SELECT r.{$assessmentColumn} AS assessment_id,
                           COUNT(DISTINCT r.checkpoint_id) AS checkpoint_done,
                           ROUND(COALESCE(SUM(r.score), 0), 2) AS original_score,
                           ROUND(COALESCE(SUM({$finalScore}), 0), 2) AS final_score
                    FROM {$responseTable} r
                    {$actionJoinForScore}
                    GROUP BY r.{$assessmentColumn}
                ) rs ON rs.assessment_id = a.assessment_id
            ";
        }

        $actionJoin = self::tableExistsLocal($con, 'assessment_action_plan')
            ? "
                LEFT JOIN (
                    SELECT assessment_id,
                           COUNT(*) AS action_plans,
                           SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS completed_action_plans,
                           MAX(updated_on) AS last_action_update
                    FROM assessment_action_plan
                    GROUP BY assessment_id
                ) aps ON aps.assessment_id = a.assessment_id
            "
            : '';

        // One assessment can contain class-level details. Keep each saved
        // class record visible rather than dropping any teacher/section data.
        $hasAssessorInfo = self::tableExistsLocal($con, 'assessment_assessor_info');
        $hasClassSection = $hasAssessorInfo && self::columnExistsLocal($con, 'assessment_assessor_info', 'class_section');
        $assessorInfoJoin = $hasAssessorInfo
            ? 'LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id'
            : '';
        $assessorInfoSelect = $hasAssessorInfo
            ? "ai.dept_id AS class_id, ai.assessor_name, ai.assessee_name, ai.teacher_code, ai.subject_name, " . ($hasClassSection ? 'ai.class_section' : "''") . ' AS class_section,'
            : "NULL AS class_id, '' AS assessor_name, '' AS assessee_name, '' AS teacher_code, '' AS subject_name, '' AS class_section,";

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.fac_id, f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date,
                   a.end_date, a.completed_on, a.cancelled_on, a.status, COALESCE(rs.checkpoint_done, 0) AS checkpoint_done,
                   {$assessorInfoSelect}
                   COALESCE(rs.original_score, 0) AS original_score,
                   COALESCE(rs.final_score, 0) AS final_score,
                   COALESCE(aps.action_plans, 0) AS action_plans,
                   COALESCE(aps.completed_action_plans, 0) AS completed_action_plans,
                   COALESCE(aps.last_action_update, '') AS last_action_update
            FROM assessment_master a
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorInfoJoin}
            {$responseJoin}
            {$actionJoin}
            {$where['sql']}
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC, class_id
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['NIN_no'] ?? '', $row['fac_name'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '', $row['framework_code'] ?? '', $row['start_date'] ?? '',
                $row['end_date'] ?? '', $row['completed_on'] ?? '', $row['cancelled_on'] ?? '', $row['status'] ?? '',
                Crypto::decrypt((string)($row['assessor_name'] ?? '')),
                Crypto::decrypt((string)($row['assessee_name'] ?? '')),
                $row['teacher_code'] ?? '', $row['subject_name'] ?? '', $row['class_section'] ?? '',
                $row['checkpoint_done'] ?? 0,
                $row['original_score'] ?? 0, $row['final_score'] ?? 0, $row['action_plans'] ?? 0,
                $row['completed_action_plans'] ?? 0, $row['last_action_update'] ?? ''
            ];
        });
    }

    /**
     * Handles write cqi processing for this API workflow.
     */
    private static function writeCqi(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_action_plan')) {
            self::csvRow($out, ['CQI action plan table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'District', 'Block', $labels['facility'] . ' Name', $labels['facility_code'], $labels['facility'] . ' Type',
            'Assessment ID', 'Assessment Name', 'Assessment Status',
            'Open Gap', 'Closed Gap', 'Left Gap', 'Total Action Plan',
            'Overdue Gap', 'Last Updated'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                a.assessment_id,
                a.assessment_name,
                a.status AS assessment_status,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS closed_gap,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS open_gap,
                COUNT(*) AS total_action_plan,
                SUM(CASE WHEN ap.target_date IS NOT NULL AND ap.target_date < CURDATE() AND UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS overdue_gap,
                MAX(ap.updated_on) AS last_updated
            FROM assessment_action_plan ap
            LEFT JOIN assessment_master a ON a.assessment_id = ap.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$where['sql']}
            GROUP BY
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                a.assessment_id,
                a.assessment_name,
                a.status
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            $openGap = (int)($row['open_gap'] ?? 0);
            $closedGap = (int)($row['closed_gap'] ?? 0);
            return [
                $row['Dist_Name'] ?? '',
                $row['Block_Name'] ?? '',
                $row['fac_name'] ?? '',
                $row['NIN_no'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''),
                $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '',
                $row['assessment_status'] ?? '',
                $openGap,
                $closedGap,
                $openGap,
                $row['total_action_plan'] ?? 0,
                $row['overdue_gap'] ?? 0,
                $row['last_updated'] ?? ''
            ];
        });
    }

    /**
     * Handles write performance processing for this API workflow.
     */
    private static function writePerformance(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'performance_entries')) {
            self::csvRow($out, ['Performance table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'District', 'Block', $labels['facility'] . ' Name', $labels['facility_code'], $labels['facility'] . ' Type',
            'Total Departments', 'KPI Departments', 'Outcome Departments',
            'KPI Month Count', 'KPI Months', 'Outcome Month Count',
            'Outcome Months', 'KPI Entry Count', 'Outcome Entry Count',
            'Latest Updated'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        if (($filters['month'] ?? '') !== '') {
            $where['sql'] .= ' AND pe.entry_month = ?';
            $where['types'] .= 'i';
            $where['params'][] = (int)$filters['month'];
        }
        if (($filters['year'] ?? '') !== '') {
            $where['sql'] .= ' AND pe.entry_year = ?';
            $where['types'] .= 'i';
            $where['params'][] = (int)$filters['year'];
        }

        $sql = "
            SELECT
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type,
                COUNT(DISTINCT pe.dept_id) AS total_departments,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN pe.dept_id END) AS kpi_departments,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN pe.dept_id END) AS outcome_departments,
                SUM(CASE WHEN pe.indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN pe.indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0')) END) AS kpi_month_count,
                COUNT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0')) END) AS outcome_month_count,
                GROUP_CONCAT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'), '-01'), '%Y-%m-%d'), '%b-%y') END ORDER BY pe.entry_year, pe.entry_month SEPARATOR ', ') AS kpi_months,
                GROUP_CONCAT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN DATE_FORMAT(STR_TO_DATE(CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'), '-01'), '%Y-%m-%d'), '%b-%y') END ORDER BY pe.entry_year, pe.entry_month SEPARATOR ', ') AS outcome_months,
                MAX(pe.updated_on) AS latest_updated
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
            GROUP BY
                f.Dist_Name,
                f.Block_Name,
                f.fac_name,
                f.NIN_no,
                f.Health_facilty_type
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['Dist_Name'] ?? '',
                $row['Block_Name'] ?? '',
                $row['fac_name'] ?? '',
                $row['NIN_no'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''),
                $row['total_departments'] ?? 0,
                $row['kpi_departments'] ?? 0,
                $row['outcome_departments'] ?? 0,
                $row['kpi_month_count'] ?? 0,
                $row['kpi_months'] ?? '',
                $row['outcome_month_count'] ?? 0,
                $row['outcome_months'] ?? '',
                $row['kpi_entries'] ?? 0,
                $row['outcome_entries'] ?? 0,
                $row['latest_updated'] ?? ''
            ];
        });
    }

    /**
     * Handles write certification processing for this API workflow.
     */
    private static function writeCertification(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'certification_history')) {
            self::csvRow($out, ['Certification history table is not available.']);
            return;
        }

        $labels = self::domainLabels();
        self::csvRow($out, [
            'History ID', $labels['facility_code'], $labels['facility'], 'District', 'Block',
            'Status', 'Certification Type', 'Assessment Mode', 'Certification Date',
            'Valid From', 'Expiry Date', 'Score', 'Renewal Status', 'Remarks',
            'Action Type', 'Action By', 'Action On'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT ch.history_id, ch.fac_id_fk, ch.fac_nin, ch.new_data_json, ch.action_type,
                   ch.action_by, ch.action_on, f.fac_name, f.Dist_Name, f.Block_Name
            FROM certification_history ch
            LEFT JOIN facilities f ON f.fac_id = ch.fac_id_fk OR CAST(f.NIN_no AS CHAR) = CAST(ch.fac_nin AS CHAR)
            {$where['sql']}
            ORDER BY ch.action_on DESC, ch.history_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            $payload = json_decode((string)($row['new_data_json'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            return [
                $row['history_id'] ?? '', $row['fac_nin'] ?? '',
                $row['fac_name'] ?? '', $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '',
                $payload['status'] ?? $payload['Cert_status'] ?? '',
                $payload['certification_type'] ?? $payload['certification_level'] ?? $payload['type_of_ass'] ?? '',
                $payload['assessment_mode'] ?? $payload['ass_mod'] ?? '',
                $payload['certification_date'] ?? $payload['date_of_ass'] ?? '',
                $payload['valid_from'] ?? '',
                $payload['expiry_date'] ?? $payload['valid_to'] ?? $payload['validity'] ?? '',
                $payload['score'] ?? '',
                $payload['renewal_status'] ?? '',
                $payload['remarks'] ?? $payload['cert_detailscol'] ?? '',
                $row['action_type'] ?? '', $row['action_by'] ?? '', $row['action_on'] ?? ''
            ];
        });
    }

    /**
     * Handles response table processing for this API workflow.
     */
    private static function responseTable(mysqli $con): string
    {
        if (self::tableExistsLocal($con, 'assessment_response')) {
            return 'assessment_response';
        }
        if (self::tableExistsLocal($con, 'assessment_cycle_response')) {
            return 'assessment_cycle_response';
        }
        return '';
    }

    /**
     * Handles select column processing for this API workflow.
     */
    private static function selectColumn(mysqli $con, string $table, string $column, string $alias): string
    {
        return self::columnExistsLocal($con, $table, $column)
            ? "{$alias}.{$column}"
            : "''";
    }

    /**
     * Handles month name processing for this API workflow.
     */
    private static function monthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        return date('F', mktime(0, 0, 0, $month, 1));
    }

    /**
     * Exports completed-assessment counts and details by assigned Assessor/DPO.
     */
    private static function writeAssessorActivity(mysqli $con, $out, array $filters): void
    {
        $labels = self::domainLabels();
        $assessorLabel = $labels['assessor'];

        if (!self::tableExistsLocal($con, 'assessment_master') || !self::columnExistsLocal($con, 'assessment_master', 'assigned_assessor_id')) {
            self::csvRow($out, [$assessorLabel . ' activity is not available because assessments have no assigned ' . strtolower($assessorLabel) . '.']);
            return;
        }

        self::csvRow($out, [
            $assessorLabel . ' ID', $assessorLabel . ' Code', $assessorLabel . ' Name',
            'Completed Assessments', 'Assessment ID', 'Assessment Name', 'Framework',
            $labels['facility_code'], $labels['facility'] . ' Name',
            'District', 'Block', 'Start Date', 'Planned End Date', 'Actual Completion Date', 'Cancellation Date', 'Assessment Status'
        ]);

        $where = self::facilityWhereLocal($filters, 'f');
        $hasAssessorMaster = self::tableExistsLocal($con, 'assessor_master');
        $assessorJoin = $hasAssessorMaster
            ? 'LEFT JOIN assessor_master am ON am.assessor_id = a.assigned_assessor_id'
            : '';
        $assessorId = $hasAssessorMaster ? 'am.assessor_id' : 'a.assigned_assessor_id';
        $assessorCode = $hasAssessorMaster ? 'am.assessor_code' : "''";
        $assessorName = $hasAssessorMaster ? 'am.assessor_name' : "''";
        $completedJoin = "
            LEFT JOIN (
                SELECT assigned_assessor_id,
                       SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('COMPLETED', 'CLOSED') THEN 1 ELSE 0 END) AS completed_assessments
                FROM assessment_master
                WHERE assigned_assessor_id IS NOT NULL
                GROUP BY assigned_assessor_id
            ) ac ON ac.assigned_assessor_id = a.assigned_assessor_id
        ";

        $sql = "
            SELECT {$assessorId} AS assessor_id, {$assessorCode} AS assessor_code,
                   {$assessorName} AS assessor_name, COALESCE(ac.completed_assessments, 0) AS completed_assessments,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date, a.end_date, a.completed_on, a.cancelled_on, a.status,
                   f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name
            FROM assessment_master a
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$assessorJoin}
            {$completedJoin}
            {$where['sql']}
            ORDER BY assessor_name, assessor_code, a.assessment_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, static function (array $row): array {
            return [
                $row['assessor_id'] ?? '', $row['assessor_code'] ?? '',
                Crypto::decrypt((string) ($row['assessor_name'] ?? '')),
                $row['completed_assessments'] ?? 0, $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '', $row['framework_code'] ?? '',
                $row['NIN_no'] ?? '', $row['fac_name'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['start_date'] ?? '',
                $row['end_date'] ?? '', $row['completed_on'] ?? '', $row['cancelled_on'] ?? '', $row['status'] ?? ''
            ];
        });
    }

    /**
     * Returns display labels for the currently active deployment domain.
     */
    private static function domainLabels(): array
    {
        static $labels = null;
        if ($labels !== null) {
            return $labels;
        }

        $path = __DIR__ . '/../config/domain.json';
        $domain = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $configured = is_array($domain['labels'] ?? null) ? $domain['labels'] : [];
        $labels = [
            'facility' => (string) ($configured['facility'] ?? 'Facility'),
            'facilities' => (string) ($configured['facilities'] ?? 'Facilities'),
            'facility_code' => (string) ($configured['facility_code'] ?? 'NIN'),
            'assessor' => (string) ($configured['assessor'] ?? 'Assessor'),
            'assessment' => (string) ($configured['assessment'] ?? 'Assessment'),
            'assessments' => (string) ($configured['assessments'] ?? 'Assessments'),
            'department' => (string) ($configured['department'] ?? 'Department'),
            'departments' => (string) ($configured['departments'] ?? 'Departments'),
        ];
        return $labels;
    }

    /** Writes the education-friendly, one-row-per-class assessment score export. */
    private static function writeClassAssessmentScores(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_master') || !self::tableExistsLocal($con, 'assessment_department')) {
            self::csvRow($out, ['Class assessment tables are not available.']);
            return;
        }

        $hasInfo = self::tableExistsLocal($con, 'assessment_assessor_info');
        $classSection = $hasInfo && self::columnExistsLocal($con, 'assessment_assessor_info', 'class_section') ? 'ai.class_section' : "''";
        $infoJoin = $hasInfo ? 'LEFT JOIN assessment_assessor_info ai ON ai.assessment_id = a.assessment_id AND ai.dept_id = ad.dept_id' : '';
        $infoFields = $hasInfo
            ? self::selectColumn($con, 'assessment_assessor_info', 'assessor_name', 'ai') . ' AS assessor_name, '
                . self::selectColumn($con, 'assessment_assessor_info', 'assessee_name', 'ai') . ' AS assessee_name, '
                . self::selectColumn($con, 'assessment_assessor_info', 'head_master_name', 'ai') . ' AS head_master_name, '
                . self::selectColumn($con, 'assessment_assessor_info', 'subject_name', 'ai') . ' AS subject_name, '
                . "{$classSection} AS class_section,"
            : "'' AS assessor_name, '' AS assessee_name, '' AS head_master_name, '' AS subject_name, '' AS class_section,";
        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.Dist_Name, f.Block_Name, f.fac_name, f.NIN_no, f.Health_facilty_type,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date, a.end_date, a.status AS assessment_status,
                   ad.dept_id, ad.started_on AS class_start_date, ad.completed_on AS class_end_date, ad.status AS class_status,
                   {$infoFields}
                   COALESCE(r.completed_indicators, 0) AS completed_indicators,
                   COALESCE(r.obtained_marks, 0) AS obtained_marks
            FROM assessment_master a
            INNER JOIN assessment_department ad ON ad.assessment_id = a.assessment_id AND ad.fac_id_fk = a.fac_id_fk AND ad.is_active = 1
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$infoJoin}
            LEFT JOIN (
                SELECT assessment_id, dept_id, COUNT(DISTINCT checkpoint_id) AS completed_indicators, SUM(COALESCE(score, 0)) AS obtained_marks
                FROM assessment_response GROUP BY assessment_id, dept_id
            ) r ON r.assessment_id = a.assessment_id AND r.dept_id = ad.dept_id
            {$where['sql']}
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC, ad.dept_id
        ";
        $stmt = self::prepareAndBind($con, $sql, $where['types'], $where['params']);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Read all response scores for the selected report scope in one query.
        // This keeps large state exports from issuing one database query per class.
        $responseScores = [];
        $responseSql = "SELECT r.assessment_id, r.dept_id, r.checkpoint_id, COALESCE(r.score, 0) AS score
            FROM assessment_response r
            INNER JOIN assessment_master a ON a.assessment_id = r.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$where['sql']}";
        $responseStmt = self::prepareAndBind($con, $responseSql, $where['types'], $where['params']);
        $responseStmt->execute();
        foreach ($responseStmt->get_result() as $response) {
            $responseScores[(int)$response['assessment_id'] . ':' . (int)$response['dept_id']][(int)$response['checkpoint_id']] = (float)$response['score'];
        }
        $responseStmt->close();

        $domains = [];
        foreach ($rows as &$row) {
            $map = self::classDomainMap((string)($row['framework_code'] ?: 'saqshi-education'), (int)$row['Health_facilty_type'], (int)$row['dept_id']);
            $row['_domain_map'] = $map;
            foreach ($map['domains'] as $name => $_) $domains[$name] = true;
        }
        unset($row);
        $domainNames = array_keys($domains);
        natcasesort($domainNames);
        $domainNames = array_values($domainNames);

        self::csvRow($out, array_merge([
            'District', 'Block', 'School Name', 'UDISE Code', 'Assessment ID', 'Assessment Name',
            'Assessor Name', 'Assessee Name', 'Class', 'Class Teacher Name', 'Subject Name', 'Section',
            'Assessment Start Date', 'Assessment End Date', 'Class Start Date', 'Class End Date',
            'Total Indicators', 'Completed Indicators', 'Total Marks', 'Obtained Marks',
            'Assessment Status', 'Class Status', 'Row Score %'
        ], array_map(static fn (string $name): string => $name . ' Score %', $domainNames)));

        foreach ($rows as $row) {
            $map = $row['_domain_map'];
            $assesseeName = Crypto::decrypt((string)($row['assessee_name'] ?? ''));
            // Older records do not have a separately captured class-teacher
            // field. In that case the recorded assessee is the best available
            // class contact and keeps the export useful for existing data.
            $classTeacherName = trim((string)($row['head_master_name'] ?? '')) ?: $assesseeName;
            $classResponseScores = $responseScores[(int)$row['assessment_id'] . ':' . (int)$row['dept_id']] ?? [];
            $totalMarks = 0.0;
            $domainObtained = array_fill_keys($domainNames, 0.0);
            $domainTotal = array_fill_keys($domainNames, 0.0);
            foreach ($map['checkpoints'] as $checkpointId => $checkpoint) {
                $max = (float)$checkpoint['max_score'];
                $domain = $checkpoint['domain'];
                $totalMarks += $max;
                $domainTotal[$domain] = ($domainTotal[$domain] ?? 0) + $max;
                if (isset($classResponseScores[$checkpointId])) {
                    $domainObtained[$domain] = ($domainObtained[$domain] ?? 0) + $classResponseScores[$checkpointId];
                }
            }
            $obtained = (float)$row['obtained_marks'];
            $rowScore = $totalMarks > 0 ? round(($obtained / $totalMarks) * 100, 2) : 0;
            $domainScores = array_map(static fn (string $domain): string => ($domainTotal[$domain] ?? 0) > 0
                ? (string)round((($domainObtained[$domain] ?? 0) / $domainTotal[$domain]) * 100, 2) : '', $domainNames);
            self::csvRow($out, array_merge([
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['fac_name'] ?? '', $row['NIN_no'] ?? '',
                $row['assessment_id'] ?? '', $row['assessment_name'] ?? '', Crypto::decrypt((string)($row['assessor_name'] ?? '')),
                $assesseeName, self::className((int)$row['dept_id']), $classTeacherName, $row['subject_name'] ?? '', $row['class_section'] ?? '',
                $row['start_date'] ?? '', $row['end_date'] ?? '', $row['class_start_date'] ?? '', $row['class_end_date'] ?? '',
                count($map['checkpoints']), $row['completed_indicators'] ?? 0, round($totalMarks, 2), round($obtained, 2),
                $row['assessment_status'] ?? '', $row['class_status'] ?? '', $rowScore
            ], $domainScores));
        }
    }

    /** Returns configured checkpoint and domain metadata for one class. */
    private static function classDomainMap(string $frameworkCode, int $facilityTypeId, int $deptId): array
    {
        static $cache = [];
        $key = $frameworkCode . ':' . $facilityTypeId . ':' . $deptId;
        if (isset($cache[$key])) return $cache[$key];
        $path = __DIR__ . '/../config/frameworks/' . preg_replace('/[^a-z0-9_-]/i', '', $frameworkCode) . '.json';
        $framework = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $result = ['domains' => [], 'checkpoints' => []];
        foreach (is_array($framework) ? $framework : [] as $facilityType) {
            if ((int)($facilityType['fac_type_id'] ?? 0) !== $facilityTypeId) continue;
            foreach (($facilityType['departments'] ?? []) as $department) {
                if ((int)($department['fac_dept_id'] ?? 0) !== $deptId) continue;
                foreach (($department['concerns'] ?? []) as $concern) {
                    $domain = trim((string)($concern['concern_name'] ?? $concern['concern_des'] ?? '')) ?: 'Uncategorised';
                    $result['domains'][$domain] = true;
                    foreach (($concern['subtypes'] ?? []) as $subtype) foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                        $id = (int)($checkpoint['csqa_id'] ?? 0);
                        if ($id <= 0 || isset($result['checkpoints'][$id])) continue;
                        $scores = array_map(static fn ($option): float => (float)($option['score'] ?? 0), is_array($checkpoint['response']['options'] ?? null) ? $checkpoint['response']['options'] : []);
                        $result['checkpoints'][$id] = ['domain' => $domain, 'max_score' => $scores ? max($scores) : 2];
                    }
                }
            }
        }
        return $cache[$key] = $result;
    }

    private static function className(int $deptId): string
    {
        return self::departmentMap()[$deptId] ?? ('Class ' . $deptId);
    }

    /**
     * Formats a short, human-readable list for report descriptions.
     */
    private static function humanList(array $items): string
    {
        $items = array_values(array_filter($items, static fn ($item): bool => trim((string) $item) !== ''));
        $count = count($items);
        if ($count < 2) {
            return (string) ($items[0] ?? 'the enabled modules');
        }
        if ($count === 2) {
            return $items[0] . ' and ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    /**
     * Checks whether an optional module is enabled for the active domain.
     */
    private static function moduleEnabled(string $key): bool
    {
        static $modules = null;
        if ($modules === null) {
            $path = __DIR__ . '/../config/modules.json';
            $config = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
        }

        return !isset($modules[$key]) || !empty($modules[$key]['enabled']);
    }

    /**
     * Handles facility type name processing for this API workflow.
     */
    private static function facilityTypeName(mixed $typeId): string
    {
        $id = (int)$typeId;
        static $map = null;

        if ($map === null) {
            $map = [];
            $path = __DIR__ . '/../config/masters/facility_types.json';
            $types = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];

            foreach (is_array($types) ? $types : [] as $type) {
                $map[(int)($type['fac_type_id'] ?? 0)] = (string)($type['facilities_type'] ?? '');
            }
        }

        return $map[$id] ?? (string)$typeId;
    }

    /**
     * Handles department map processing for this API workflow.
     */
    private static function departmentMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach ([
            __DIR__ . '/../config/masters/departmet.json',
            __DIR__ . '/../config/masters/department.json'
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $rows = json_decode((string)file_get_contents($path), true);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $id = (int)($row['fac_dept_id'] ?? $row['dept_id'] ?? $row['department_id'] ?? 0);
                $name = trim((string)($row['dept_name'] ?? $row['department_name'] ?? $row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $map[$id] = $name;
                }
            }
        }

        return $map;
    }

    /**
     * Handles checkpoint map processing for this API workflow.
     */
    private static function checkpointMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? json_decode((string)file_get_contents($domainPath), true) : [];
        $frameworkCode = preg_replace('/[^a-z0-9_-]/i', '', (string)($domain['default_framework'] ?? '')) ?: 'saqshi-nqas';
        $path = __DIR__ . '/../config/frameworks/' . $frameworkCode . '.json';
        if (!is_file($path)) {
            return $map;
        }

        $facilityTypes = json_decode((string)file_get_contents($path), true);
        if (!is_array($facilityTypes)) {
            return $map;
        }

        foreach ($facilityTypes as $facilityType) {
            foreach (($facilityType['departments'] ?? []) as $department) {
                foreach (($department['concerns'] ?? []) as $concern) {
                    foreach (($concern['subtypes'] ?? []) as $subtype) {
                        foreach (($subtype['checkpoints'] ?? []) as $checkpoint) {
                            $id = (string)($checkpoint['csqa_id'] ?? '');
                            if ($id === '') {
                                continue;
                            }
                            $map[$id] = [
                                'department_name' => (string)($department['dept_name'] ?? ''),
                                'concern_name' => trim((string)($concern['concern_des'] ?? '') . ' ' . (string)($concern['concern_name'] ?? '')),
                                'standard' => (string)($subtype['Reference_No'] ?? $checkpoint['c_subtype_Reference_No_fk'] ?? ''),
                                'measurable_element' => (string)($checkpoint['Measurable_Element'] ?? ''),
                                'checkpoint' => (string)($checkpoint['Checkpoint'] ?? ''),
                                'assessment_method' => (string)($checkpoint['Assessment_Method'] ?? '')
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Handles performance indicator map processing for this API workflow.
     */
    private static function performanceIndicatorMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach ([
            __DIR__ . '/../config/performance/outcome.json',
            __DIR__ . '/../config/performance/kpi.json'
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            self::collectPerformanceIndicators($data, $map);
        }

        return $map;
    }

    /**
     * Handles collect performance indicators processing for this API workflow.
     */
    private static function collectPerformanceIndicators(array $node, array &$map): void
    {
        if (isset($node['indicator_id']) || isset($node['kpi_id']) || isset($node['outcome_id'])) {
            $id = (string)($node['indicator_id'] ?? $node['kpi_id'] ?? $node['outcome_id'] ?? '');
            $type = strtoupper((string)($node['indicator_type'] ?? (str_contains((string)($node['indicator_code'] ?? ''), 'KPI') ? 'KPI' : 'OUTCOME')));
            if ($id !== '') {
                $labels = self::indicatorFieldLabels($node);
                $details = [
                    'indicator_code' => (string)($node['indicator_code'] ?? $node['kpi_code'] ?? $node['outcome_code'] ?? ''),
                    'indicator_name' => (string)($node['indicator_name'] ?? $node['kpi_name'] ?? $node['outcome_name'] ?? ''),
                    'numerator_label' => $labels['numerator'],
                    'denominator_label' => $labels['denominator']
                ];
                $map[$id] = $details;
                $map[$type . ':' . $id] = $details;
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectPerformanceIndicators($value, $map);
            }
        }
    }

    /**
     * Handles indicator field labels processing for this API workflow.
     */
    private static function indicatorFieldLabels(array $indicator): array
    {
        $labels = ['numerator' => '', 'denominator' => ''];
        foreach (($indicator['fields'] ?? []) as $field) {
            $name = strtolower((string)($field['field_name'] ?? $field['field_id'] ?? ''));
            if ($name === 'numerator' || $name === 'n') {
                $labels['numerator'] = (string)($field['label'] ?? '');
            }
            if ($name === 'denominator' || $name === 'd') {
                $labels['denominator'] = (string)($field['label'] ?? '');
            }
        }
        return $labels;
    }

    /**
     * Handles facility where local processing for this API workflow.
     */
    private static function facilityWhereLocal(array $filters, string $alias): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];
        $map = [
            'state_code' => "{$alias}.state_code",
            'division' => "{$alias}.division",
            'district' => "{$alias}.Dist_Name",
            'block' => "{$alias}.Block_Name",
            'facility_type' => "{$alias}.Health_facilty_type"
        ];

        foreach ($map as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $types .= in_array($key, ['state_code', 'facility_type'], true) ? 'i' : 's';
                $params[] = in_array($key, ['state_code', 'facility_type'], true) ? (int)$filters[$key] : (string)$filters[$key];
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "({$alias}.fac_name LIKE ? OR CAST({$alias}.NIN_no AS CHAR) LIKE ? OR CAST({$alias}.fac_id AS CHAR) LIKE ? OR {$alias}.Dist_Name LIKE ? OR {$alias}.Block_Name LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return ['sql' => 'WHERE ' . implode(' AND ', $where), 'types' => $types, 'params' => $params];
    }

    /**
     * Flattens the configured facility master for exports in non-database domains.
     */
    private static function facilityMasterRows(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }

        $rows = [];
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? json_decode((string) file_get_contents($domainPath), true) : [];
        if (($domain['domain'] ?? '') !== 'education') {
            return $rows;
        }

        $path = __DIR__ . '/../config/masters/facilities.json';
        $states = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        if (!is_array($states)) {
            return $rows;
        }

        foreach ($states as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            $rows[] = [
                                'state_code' => (int) ($state['state_id'] ?? 0),
                                'state_name' => (string) ($state['state_name'] ?? ''),
                                'division' => (string) ($division['division_name'] ?? ''),
                                'district' => (string) ($district['dist_name'] ?? ''),
                                'block' => (string) ($block['block_name'] ?? ''),
                                'fac_id' => $facility['fac_id'] ?? '',
                                'fac_name' => (string) ($facility['fac_name'] ?? ''),
                                'facility_type' => $facility['fac_type_id'] ?? '',
                                'nin_no' => $facility['nin_no'] ?? '',
                                'latitude' => $facility['latitude'] ?? '',
                                'longitude' => $facility['longitude'] ?? '',
                            ];
                        }
                    }
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [
            $a['state_name'], $a['division'], $a['district'], $a['block'], $a['fac_name']
        ] <=> [
            $b['state_name'], $b['division'], $b['district'], $b['block'], $b['fac_name']
        ]);

        return $rows;
    }

    /**
     * Applies the State Report filters to a facility-master row.
     */
    private static function matchesMasterFilters(array $row, array $filters): bool
    {
        $exact = [
            'state_code' => 'state_code',
            'division' => 'division',
            'district' => 'district',
            'block' => 'block',
            'facility_type' => 'facility_type',
        ];
        foreach ($exact as $filter => $field) {
            if (($filters[$filter] ?? '') !== '' && (string) $filters[$filter] !== (string) $row[$field]) {
                return false;
            }
        }

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', [
            $row['fac_id'], $row['nin_no'], $row['fac_name'], $row['district'], $row['block']
        ]));
        return str_contains($haystack, $search);
    }

    /**
     * Handles stream query processing for this API workflow.
     */
    private static function streamQuery(mysqli $con, string $sql, string $types, array $params, $out, callable $mapRow): void
    {
        $stmt = self::prepareAndBind($con, $sql, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            self::csvRow($out, $mapRow($row));
        }
        $stmt->close();
    }

    /**
     * Handles csv row processing for this API workflow.
     */
    private static function csvRow($out, array $fields): void
    {
        fputcsv($out, $fields, ',', '"', '', "\r\n");
    }

    /**
     * Handles prepare and bind processing for this API workflow.
     */
    private static function prepareAndBind(mysqli $con, string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Report query prepare failed: ' . $con->error);
        }

        if ($types !== '') {
            $refs = [];
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            $stmt->bind_param($types, ...$refs);
        }

        return $stmt;
    }

    /**
     * Handles table exists local processing for this API workflow.
     */
    private static function tableExistsLocal(mysqli $con, string $table): bool
    {
        $stmt = $con->prepare("
            SELECT COUNT(*) AS table_count
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        $stmt->close();

        return (int)($row['table_count'] ?? 0) > 0;
    }

    /**
     * Handles column exists local processing for this API workflow.
     */
    private static function columnExistsLocal(mysqli $con, string $table, string $column): bool
    {
        $stmt = $con->prepare("
            SELECT COUNT(*) AS column_count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        $stmt->close();

        return (int)($row['column_count'] ?? 0) > 0;
    }
}
