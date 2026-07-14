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

class StateReportService extends StateDashboardService
{
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

    public static function exportCatalog(): array
    {
        return [
            ['key' => 'summary', 'title' => 'State Summary', 'description' => 'Summary counts for facilities, assessments, CQI, performance and certification.'],
            ['key' => 'facilities', 'title' => 'All Facility List', 'description' => 'Facility master list with state, division, district, block, type, NIN and coordinates.'],
            ['key' => 'assessments', 'title' => 'Assessment Details', 'description' => 'All assessment records with status, departments, checkpoints, action plans and score fields.'],
            ['key' => 'cqi', 'title' => 'CQI Details', 'description' => 'Action plan and gap closure extract with responsible person, target date and revised score.'],
            ['key' => 'performance', 'title' => 'Performance Details', 'description' => 'KPI and Outcome entries with month, numerator, denominator, result and remarks.'],
            ['key' => 'certification', 'title' => 'Certification History', 'description' => 'Certification history from certification_history with decoded status, dates and score.']
        ];
    }

    private static function writeSummary(mysqli $con, $out, array $filters): void
    {
        $facility = self::facilityCategory($con, $filters);
        $assessment = self::assessmentProgress($con, $filters, true);
        $cqi = self::cqiSummary($con, $filters);
        $performance = self::performanceSummary($con, $filters);
        $certification = self::certificationSummary($con, $filters);

        self::csvRow($out, ['Report', 'Metric', 'Value']);
        self::csvRow($out, ['Facilities', 'Total Facilities', $facility['total_facilities'] ?? 0]);
        foreach (($facility['facility_types'] ?? []) as $row) {
            self::csvRow($out, ['Facilities', 'Facility Type - ' . ($row['facility_type'] ?? ''), $row['count'] ?? 0]);
        }
        self::csvRow($out, ['Assessments', 'Total', $assessment['total'] ?? 0]);
        self::csvRow($out, ['Assessments', 'Active', $assessment['active'] ?? 0]);
        self::csvRow($out, ['Assessments', 'Completed', $assessment['completed'] ?? 0]);
        self::csvRow($out, ['Assessments', 'Cancelled', $assessment['cancelled'] ?? 0]);
        self::csvRow($out, ['CQI', 'Facilities With Action Plan', $cqi['facilities_with_action_plan'] ?? 0]);
        self::csvRow($out, ['CQI', 'Completed', $cqi['completed'] ?? 0]);
        self::csvRow($out, ['CQI', 'Pending', $cqi['pending'] ?? 0]);
        self::csvRow($out, ['CQI', 'Overdue', $cqi['overdue'] ?? 0]);
        self::csvRow($out, ['Performance', 'Facilities', $performance['summary']['facilities'] ?? 0]);
        self::csvRow($out, ['Performance', 'Performance Entries', $performance['summary']['performance_entries'] ?? 0]);
        self::csvRow($out, ['Performance', 'Submitted Months', $performance['summary']['submitted_months'] ?? 0]);
        self::csvRow($out, ['Certification', 'Total', $certification['total'] ?? 0]);
        foreach (($certification['status'] ?? []) as $row) {
            self::csvRow($out, ['Certification', 'Status - ' . ($row['status'] ?? ''), $row['count'] ?? 0]);
        }
    }

    private static function writeFacilities(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'facilities')) {
            self::csvRow($out, ['Facilities table is not available.']);
            return;
        }

        self::csvRow($out, [
            'Facility ID', 'State', 'Division', 'District', 'Block', 'Facility Name',
            'Facility Type ID', 'NIN', 'Latitude', 'Longitude', 'Active'
        ]);

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
                $row['fac_id'] ?? '', $row['state_name'] ?? '', $row['division'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['fac_name'] ?? '',
                $row['Health_facilty_type'] ?? '', $row['NIN_no'] ?? '', $row['lat'] ?? '',
                $row['longit'] ?? '', $row['is_active'] ?? ''
            ];
        });
    }

    private static function writeAssessments(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_master')) {
            self::csvRow($out, ['Assessment table is not available.']);
            return;
        }

        self::csvRow($out, [
            'Facility ID', 'NIN', 'Facility', 'District', 'Block', 'Assessment ID',
            'Assessment Name', 'Framework', 'Start Date', 'End Date', 'Status',
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

        $where = self::facilityWhereLocal($filters, 'f');
        $sql = "
            SELECT f.fac_id, f.NIN_no, f.fac_name, f.Dist_Name, f.Block_Name,
                   a.assessment_id, a.assessment_name, a.framework_code, a.start_date,
                   a.end_date, a.status, COALESCE(rs.checkpoint_done, 0) AS checkpoint_done,
                   COALESCE(rs.original_score, 0) AS original_score,
                   COALESCE(rs.final_score, 0) AS final_score,
                   COALESCE(aps.action_plans, 0) AS action_plans,
                   COALESCE(aps.completed_action_plans, 0) AS completed_action_plans,
                   COALESCE(aps.last_action_update, '') AS last_action_update
            FROM assessment_master a
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$responseJoin}
            {$actionJoin}
            {$where['sql']}
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC
        ";

        self::streamQuery($con, $sql, $where['types'], $where['params'], $out, function (array $row): array {
            return [
                $row['fac_id'] ?? '', $row['NIN_no'] ?? '', $row['fac_name'] ?? '',
                $row['Dist_Name'] ?? '', $row['Block_Name'] ?? '', $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '', $row['framework_code'] ?? '', $row['start_date'] ?? '',
                $row['end_date'] ?? '', $row['status'] ?? '', $row['checkpoint_done'] ?? 0,
                $row['original_score'] ?? 0, $row['final_score'] ?? 0, $row['action_plans'] ?? 0,
                $row['completed_action_plans'] ?? 0, $row['last_action_update'] ?? ''
            ];
        });
    }

    private static function writeCqi(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'assessment_action_plan')) {
            self::csvRow($out, ['CQI action plan table is not available.']);
            return;
        }

        self::csvRow($out, [
            'District', 'Block', 'Facility Name', 'NIN', 'Facility Type',
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

    private static function writePerformance(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'performance_entries')) {
            self::csvRow($out, ['Performance table is not available.']);
            return;
        }

        self::csvRow($out, [
            'District', 'Block', 'Facility Name', 'NIN', 'Facility Type',
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

    private static function writeCertification(mysqli $con, $out, array $filters): void
    {
        if (!self::tableExistsLocal($con, 'certification_history')) {
            self::csvRow($out, ['Certification history table is not available.']);
            return;
        }

        self::csvRow($out, [
            'History ID', 'Facility ID', 'NIN', 'Facility', 'District', 'Block',
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
                $row['history_id'] ?? '', $row['fac_id_fk'] ?? '', $row['fac_nin'] ?? '',
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

    private static function selectColumn(mysqli $con, string $table, string $column, string $alias): string
    {
        return self::columnExistsLocal($con, $table, $column)
            ? "{$alias}.{$column}"
            : "''";
    }

    private static function monthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        return date('F', mktime(0, 0, 0, $month, 1));
    }

    private static function facilityTypeName(mixed $typeId): string
    {
        $id = (int)$typeId;
        $map = [
            1 => 'CHC',
            2 => 'DH',
            3 => 'PHC',
            4 => 'SDH',
            5 => 'UPHC',
            6 => 'U-CHC',
            7 => 'HWC',
            8 => 'AAM-SC'
        ];

        return $map[$id] ?? (string)$typeId;
    }

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

    private static function checkpointMap(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $path = __DIR__ . '/../config/frameworks/saqshi-nqas.json';
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

    private static function csvRow($out, array $fields): void
    {
        fputcsv($out, $fields, ',', '"', '', "\r\n");
    }

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
