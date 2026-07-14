<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Shared Service
 * PerformanceService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * PerformanceService.php
 * -------------------------------------------------------
 * Shared helpers for Performance Monitoring.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/FormulaEngine.php';

class PerformanceService
{
    private static ?array $departmentNameCache = null;

    public static function tableName(): string
    {
        return 'performance_entries';
    }

    public static function ensureTable(mysqli $con): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS performance_entries (
                entry_id BIGINT NOT NULL AUTO_INCREMENT,
                fac_id INT NOT NULL,
                dept_id INT NOT NULL DEFAULT 0,
                indicator_type VARCHAR(20) NOT NULL,
                indicator_id INT NOT NULL,
                indicator_code VARCHAR(80) NULL,
                indicator_name VARCHAR(500) NULL,
                entry_month TINYINT NOT NULL,
                entry_year SMALLINT NOT NULL,
                numerator_value DECIMAL(14,4) NOT NULL DEFAULT 0,
                denominator_value DECIMAL(14,4) NOT NULL DEFAULT 0,
                result_value DECIMAL(14,4) NULL,
                formula_id INT NULL,
                remarks TEXT NULL,
                created_by INT NULL,
                updated_by INT NULL,
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (entry_id),
                UNIQUE KEY uq_performance_entry (fac_id, dept_id, indicator_type, indicator_id, entry_month, entry_year),
                KEY idx_performance_fac_period (fac_id, entry_year, entry_month),
                KEY idx_performance_indicator (indicator_type, indicator_id)
            )
        ";

        if (!$con->query($sql)) {
            Response::serverError('Unable to create performance_entries table: ' . $con->error);
        }
    }

    public static function readJson(string $path, array $fallback = []): array
    {
        if (!file_exists($path)) {
            return $fallback;
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : $fallback;
    }

    public static function rulesConfig(): array
    {
        return self::readJson(__DIR__ . '/../config/performance/rules.json', [
            'default_rule' => [
                'kpi_applicable' => true,
                'outcome_applicable' => true,
                'outcome_treated_as_kpi' => false,
                'block_kpi_entry' => false,
                'message' => ''
            ],
            'facility_type_rules' => []
        ]);
    }

    public static function facilityTypeRule(int $facilityTypeId): array
    {
        $config = self::rulesConfig();
        $rule = $config['default_rule'] ?? [];

        foreach (($config['facility_type_rules'] ?? []) as $item) {
            if ((int)($item['fac_type_id'] ?? 0) === $facilityTypeId) {
                $rule = array_merge($rule, $item);
                break;
            }
        }

        return [
            'fac_type_id' => $facilityTypeId,
            'kpi_applicable' => (bool)($rule['kpi_applicable'] ?? true),
            'outcome_applicable' => (bool)($rule['outcome_applicable'] ?? true),
            'outcome_treated_as_kpi' => (bool)($rule['outcome_treated_as_kpi'] ?? false),
            'block_kpi_entry' => (bool)($rule['block_kpi_entry'] ?? false),
            'message' => (string)($rule['message'] ?? '')
        ];
    }

    public static function assertIndicatorAllowed(int $facilityTypeId, string $indicatorType): array
    {
        $type = strtoupper(trim($indicatorType));
        $rule = self::facilityTypeRule($facilityTypeId);

        if ($type === 'KPI' && (!$rule['kpi_applicable'] || $rule['block_kpi_entry'])) {
            Response::validation([
                'indicator_type' => $rule['message'] ?: 'KPI entry is not applicable for this facility type.'
            ]);
        }

        if ($type === 'OUTCOME' && !$rule['outcome_applicable']) {
            Response::validation([
                'indicator_type' => 'Outcome entry is not applicable for this facility type.'
            ]);
        }

        return $rule;
    }

    public static function configuredIndicatorCount(string $indicatorType, int $facilityTypeId = 0, int $departmentId = 0): int
    {
        $type = strtoupper(trim($indicatorType));
        $path = $type === 'KPI'
            ? __DIR__ . '/../config/performance/kpi.json'
            : __DIR__ . '/../config/performance/outcome.json';

        $config = self::readJson($path, []);
        return count(self::flattenIndicators($config, $type, $facilityTypeId, $departmentId));
    }

    public static function effectivePerformanceType(int $facilityTypeId): string
    {
        $rule = self::facilityTypeRule($facilityTypeId);
        return (!empty($rule['outcome_treated_as_kpi']) || empty($rule['kpi_applicable']) || !empty($rule['block_kpi_entry']))
            ? 'OUTCOME'
            : 'KPI';
    }

    public static function facilityMeta(int $facId): array
    {
        $path = __DIR__ . '/../config/masters/facilities.json';
        $empty = [
            'fac_id' => $facId,
            'fac_type_id' => 0,
            'fac_name' => '',
            'facility_type' => ''
        ];

        if ($facId <= 0 || !file_exists($path)) {
            return $empty;
        }

        $states = self::readJson($path, []);

        foreach ($states as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            if ((int)($facility['fac_id'] ?? 0) === $facId) {
                                return [
                                    'fac_id' => $facId,
                                    'fac_type_id' => (int)($facility['fac_type_id'] ?? 0),
                                    'fac_name' => (string)($facility['fac_name'] ?? ''),
                                    'facility_type' => (string)($facility['facilities_type'] ?? '')
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $empty;
    }

    public static function departmentNames(): array
    {
        if (self::$departmentNameCache !== null) {
            return self::$departmentNameCache;
        }

        $paths = [
            __DIR__ . '/../config/masters/department.json',
            __DIR__ . '/../config/masters/departmet.json'
        ];

        $rows = [];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $rows = self::readJson($path, []);
                break;
            }
        }

        $map = [];

        foreach ((array)$rows as $row) {
            $deptId = (int)($row['fac_dept_id'] ?? $row['dept_id'] ?? $row['department_id'] ?? 0);
            $name = trim((string)($row['dept_name'] ?? $row['department_name'] ?? $row['name'] ?? ''));

            if ($deptId > 0 && $name !== '') {
                $map[$deptId] = $name;
            }
        }

        self::$departmentNameCache = $map;

        return self::$departmentNameCache;
    }

    public static function departmentName(int $departmentId, string $fallback = ''): string
    {
        $map = self::departmentNames();

        if (isset($map[$departmentId])) {
            return $map[$departmentId];
        }

        return $fallback !== '' ? $fallback : 'Department ' . $departmentId;
    }

    public static function activeAssessment(mysqli $con, int $facilityId): ?array
    {
        if ($facilityId <= 0) {
            return null;
        }

        $stmt = $con->prepare("
            SELECT assessment_id, assessment_name, framework_code, status
            FROM assessment_master
            WHERE fac_id_fk = ?
              AND UPPER(TRIM(status)) IN ('ACTIVE', 'IN_PROGRESS')
            ORDER BY
                CASE UPPER(TRIM(status))
                    WHEN 'ACTIVE' THEN 1
                    WHEN 'IN_PROGRESS' THEN 2
                    ELSE 3
                END,
                assessment_id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            Response::serverError('Active assessment prepare failed: ' . $con->error);
        }

        $stmt->bind_param('i', $facilityId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            $stmt = $con->prepare("
                SELECT a.assessment_id, a.assessment_name, a.framework_code, a.status
                FROM assessment_master a
                INNER JOIN assessment_department_status ds
                    ON ds.ass_period_id = a.assessment_id
                   AND ds.fac_id_fk = a.fac_id_fk
                   AND ds.is_active = 1
                WHERE a.fac_id_fk = ?
                ORDER BY a.assessment_id DESC
                LIMIT 1
            ");

            if (!$stmt) {
                Response::serverError('Active assessment fallback prepare failed: ' . $con->error);
            }

            $stmt->bind_param('i', $facilityId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
        }

        if (!$row) {
            $assessmentId = self::latestActiveAssessmentPeriodId($con, $facilityId);

            if ($assessmentId > 0) {
                $stmt = $con->prepare("
                    SELECT assessment_id, assessment_name, framework_code, status
                    FROM assessment_master
                    WHERE assessment_id = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    Response::serverError('Active assessment period prepare failed: ' . $con->error);
                }

                $stmt->bind_param('i', $assessmentId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();

                if (!$row) {
                    return [
                        'assessment_id' => $assessmentId,
                        'assessment_name' => 'Assessment ' . $assessmentId,
                        'framework_code' => '',
                        'status' => 'ACTIVE'
                    ];
                }
            }
        }

        return $row ? [
            'assessment_id' => (int)$row['assessment_id'],
            'assessment_name' => (string)$row['assessment_name'],
            'framework_code' => (string)$row['framework_code'],
            'status' => (string)$row['status']
        ] : null;
    }

    public static function latestActiveAssessmentPeriodId(mysqli $con, int $facilityId): int
    {
        if ($facilityId <= 0) {
            return 0;
        }

        $stmt = $con->prepare("
            SELECT ass_period_id
            FROM assessment_department_status
            WHERE fac_id_fk = ?
              AND is_active = 1
            GROUP BY ass_period_id
            ORDER BY MAX(COALESCE(updated_on, activated_on)) DESC, ass_period_id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            Response::serverError('Latest active assessment period prepare failed: ' . $con->error);
        }

        $stmt->bind_param('i', $facilityId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return $row ? (int)$row['ass_period_id'] : 0;
    }

    public static function activeDepartmentIds(mysqli $con, int $facilityId): array
    {
        $assessment = self::activeAssessment($con, $facilityId);
        $assessmentId = $assessment ? (int)$assessment['assessment_id'] : self::latestActiveAssessmentPeriodId($con, $facilityId);

        if ($assessmentId <= 0) {
            return [];
        }

        $stmt = $con->prepare("
            SELECT dept_id
            FROM assessment_department_status
            WHERE fac_id_fk = ?
              AND ass_period_id = ?
              AND is_active = 1
            ORDER BY dept_id
        ");

        if (!$stmt) {
            Response::serverError('Active department prepare failed: ' . $con->error);
        }

        $stmt->bind_param('ii', $facilityId, $assessmentId);
        $stmt->execute();

        $ids = [];
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['dept_id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public static function filterByDepartmentIds(array $items, array $departmentIds): array
    {
        $allowed = array_flip(array_map('intval', $departmentIds));

        return array_values(array_filter($items, function (array $item) use ($allowed): bool {
            $deptId = (int)($item['department_id'] ?? 0);
            return $deptId > 0 && isset($allowed[$deptId]);
        }));
    }

    public static function flattenIndicators(array $config, string $type, int $facilityTypeId = 0, int $departmentId = 0): array
    {
        $rows = [];

        if (isset($config['facility_types']) && is_array($config['facility_types'])) {
            foreach ($config['facility_types'] as $facilityType) {
                $facTypeId = (int)($facilityType['facility_type_id'] ?? $facilityType['fac_type_id'] ?? 0);

                if ($facilityTypeId > 0 && $facTypeId !== $facilityTypeId) {
                    continue;
                }

                foreach (($facilityType['departments'] ?? []) as $department) {
                    $deptId = (int)($department['department_id'] ?? $department['dept_id'] ?? 0);

                    if ($departmentId > 0 && $deptId !== $departmentId) {
                        continue;
                    }

                    foreach (($department['indicators'] ?? []) as $indicator) {
                        if (!(bool)($indicator['is_active'] ?? true)) {
                            continue;
                        }

                        $rows[] = self::normalizeIndicator(
                            $indicator,
                            $type,
                            $facTypeId,
                            (string)($facilityType['facility_type_name'] ?? ''),
                            $deptId,
                            self::departmentName($deptId, (string)($department['department_name'] ?? ''))
                        );
                    }
                }
            }

            return $rows;
        }

        $key = strtolower($type) === 'kpi' ? 'kpis' : 'outcomes';
        $items = $config[$key] ?? $config['indicators'] ?? [];

        foreach ((array)$items as $indicator) {
            $rows[] = self::normalizeIndicator($indicator, $type, $facilityTypeId, '', $departmentId, '');
        }

        return $rows;
    }

    public static function normalizeIndicator(
        array $indicator,
        string $type,
        int $facilityTypeId,
        string $facilityTypeName,
        int $departmentId,
        string $departmentName
    ): array {
        return [
            'indicator_id' => (int)($indicator['indicator_id'] ?? $indicator['kpi_id'] ?? $indicator['outcome_id'] ?? 0),
            'indicator_code' => (string)($indicator['indicator_code'] ?? $indicator['kpi_code'] ?? $indicator['outcome_code'] ?? ''),
            'indicator_type' => strtoupper((string)($indicator['indicator_type'] ?? $type)),
            'indicator_name' => (string)($indicator['indicator_name'] ?? $indicator['kpi_name'] ?? $indicator['outcome_name'] ?? $indicator['name'] ?? ''),
            'facility_type_id' => $facilityTypeId,
            'facility_type_name' => $facilityTypeName,
            'department_id' => $departmentId,
            'department_name' => $departmentName !== '' ? $departmentName : self::departmentName($departmentId),
            'frequency' => (string)($indicator['frequency'] ?? 'MONTHLY'),
            'formula_id' => (int)($indicator['formula_id'] ?? 0),
            'precision' => (int)($indicator['precision'] ?? 2),
            'fields' => $indicator['fields'] ?? [],
            'validation' => $indicator['validation'] ?? [],
            'result_readonly' => (bool)($indicator['result_readonly'] ?? true),
            'allow_decimal' => (bool)($indicator['allow_decimal'] ?? true)
        ];
    }

    public static function dashboard(mysqli $con, int $facId, array $filters = []): array
    {
        self::ensureTable($con);
        $facility = self::facilityMeta($facId);
        $facilityTypeId = (int)($facility['fac_type_id'] ?? 0);
        $rule = self::facilityTypeRule($facilityTypeId);
        $effectiveType = self::effectivePerformanceType($facilityTypeId);
        $summary = self::summary($con, $facId);
        $showAll = !empty($filters['all_indicators']) || (($filters['scope'] ?? '') === 'all');
        $trendLimit = $showAll ? 0 : 8;
        $effectiveSeries = self::indicatorTrends($con, $facId, ['indicator_type' => $effectiveType, 'limit' => $trendLimit])['series'];

        return [
            'facility' => $facility,
            'rule' => $rule,
            'effective_indicator_type' => $effectiveType,
            'effective_indicator_label' => $effectiveType === 'OUTCOME' && !empty($rule['outcome_treated_as_kpi']) ? 'Outcome as KPI' : $effectiveType,
            'summary' => $summary,
            'month_status' => self::monthlyStatus($con, $facId, ['indicator_type' => $effectiveType]),
            'indicator_trends' => [
                'KPI' => $effectiveType === 'KPI' ? $effectiveSeries : [],
                'OUTCOME' => $effectiveType === 'OUTCOME' ? $effectiveSeries : self::indicatorTrends($con, $facId, ['indicator_type' => 'OUTCOME', 'limit' => $trendLimit])['series'],
                'EFFECTIVE' => $effectiveSeries
            ],
            'trend' => self::trend($con, $facId, ['indicator_type' => $effectiveType])['series']
        ];
    }

    public static function summary(mysqli $con, int $facId): array
    {
        self::ensureTable($con);

        $stmt = $con->prepare("
            SELECT
                COUNT(*) AS total_entries,
                COUNT(DISTINCT CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS total_months,
                COUNT(DISTINCT CASE WHEN indicator_type = 'KPI' THEN CONCAT(entry_year, '-', LPAD(entry_month, 2, '0')) END) AS kpi_months,
                COUNT(DISTINCT CASE WHEN indicator_type = 'OUTCOME' THEN CONCAT(entry_year, '-', LPAD(entry_month, 2, '0')) END) AS outcome_months,
                COUNT(DISTINCT CASE WHEN indicator_type = 'KPI' THEN indicator_id END) AS kpi_indicators,
                COUNT(DISTINCT CASE WHEN indicator_type = 'OUTCOME' THEN indicator_id END) AS outcome_indicators,
                MAX(CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS latest_period
            FROM performance_entries
            WHERE fac_id = ?
        ");

        if (!$stmt) {
            Response::serverError('Performance summary prepare failed: ' . $con->error);
        }

        $stmt->bind_param('i', $facId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        return [
            'total_entries' => (int)($row['total_entries'] ?? 0),
            'total_months' => (int)($row['total_months'] ?? 0),
            'kpi_months' => (int)($row['kpi_months'] ?? 0),
            'outcome_months' => (int)($row['outcome_months'] ?? 0),
            'kpi_indicators' => (int)($row['kpi_indicators'] ?? 0),
            'outcome_indicators' => (int)($row['outcome_indicators'] ?? 0),
            'latest_period' => $row['latest_period'] ?? null
        ];
    }

    public static function monthlyStatus(mysqli $con, int $facId, array $filters = []): array
    {
        self::ensureTable($con);

        $indicatorType = strtoupper((string)($filters['indicator_type'] ?? ''));
        $params = [$facId];
        $types = 'i';
        $where = 'fac_id = ?';

        if (in_array($indicatorType, ['KPI', 'OUTCOME'], true)) {
            $where .= ' AND indicator_type = ?';
            $params[] = $indicatorType;
            $types .= 's';
        }

        $stmt = $con->prepare("
            SELECT
                entry_year,
                entry_month,
                SUM(CASE WHEN indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(*) AS total_entries
            FROM performance_entries
            WHERE {$where}
            GROUP BY entry_year, entry_month
            ORDER BY entry_year ASC, entry_month ASC
        ");

        if (!$stmt) {
            Response::serverError('Performance monthly status prepare failed: ' . $con->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $rows = [];
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'period' => sprintf('%04d-%02d', (int)$row['entry_year'], (int)$row['entry_month']),
                'kpi_entries' => (int)$row['kpi_entries'],
                'outcome_entries' => (int)$row['outcome_entries'],
                'total_entries' => (int)$row['total_entries']
            ];
        }

        return $rows;
    }

    public static function trend(mysqli $con, int $facId, array $filters = []): array
    {
        self::ensureTable($con);

        $indicatorType = strtoupper((string)($filters['indicator_type'] ?? ''));
        $params = [$facId];
        $types = 'i';
        $where = 'fac_id = ?';

        if (in_array($indicatorType, ['KPI', 'OUTCOME'], true)) {
            $where .= ' AND indicator_type = ?';
            $params[] = $indicatorType;
            $types .= 's';
        }

        $sql = "
            SELECT entry_year, entry_month, indicator_type, COUNT(*) AS entries
            FROM performance_entries
            WHERE {$where}
            GROUP BY entry_year, entry_month, indicator_type
            ORDER BY entry_year ASC, entry_month ASC, indicator_type ASC
        ";

        $stmt = $con->prepare($sql);

        if (!$stmt) {
            Response::serverError('Performance trend prepare failed: ' . $con->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $rows = [];
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'period' => sprintf('%04d-%02d', (int)$row['entry_year'], (int)$row['entry_month']),
                'indicator_type' => $row['indicator_type'],
                'entries' => (int)$row['entries']
            ];
        }

        return [
            'filters' => $filters,
            'series' => $rows
        ];
    }

    public static function indicatorTrends(mysqli $con, int $facId, array $filters = []): array
    {
        self::ensureTable($con);

        $indicatorType = strtoupper((string)($filters['indicator_type'] ?? ''));
        $seriesLimit = max(0, (int)($filters['limit'] ?? 8));
        $params = [$facId];
        $types = 'i';
        $where = 'fac_id = ?';

        if (in_array($indicatorType, ['KPI', 'OUTCOME'], true)) {
            $where .= ' AND indicator_type = ?';
            $params[] = $indicatorType;
            $types .= 's';
        }

        $stmt = $con->prepare("
            SELECT
                dept_id,
                indicator_type,
                indicator_id,
                indicator_code,
                indicator_name,
                entry_year,
                entry_month,
                numerator_value,
                denominator_value,
                result_value
            FROM performance_entries
            WHERE {$where}
            ORDER BY entry_year DESC, entry_month DESC, updated_on DESC, entry_id DESC
            LIMIT 500
        ");

        if (!$stmt) {
            Response::serverError('Performance indicator trend prepare failed: ' . $con->error);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $series = [];
        $order = [];
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $key = implode('|', [
                $row['indicator_type'],
                (int)$row['dept_id'],
                (int)$row['indicator_id']
            ]);

            if (!isset($series[$key])) {
                if ($seriesLimit > 0 && count($order) >= $seriesLimit) {
                    continue;
                }

                $order[] = $key;
                $series[$key] = [
                    'indicator_type' => $row['indicator_type'],
                    'indicator_id' => (int)$row['indicator_id'],
                    'indicator_code' => (string)$row['indicator_code'],
                    'indicator_name' => (string)$row['indicator_name'],
                    'department_id' => (int)$row['dept_id'],
                    'department_name' => self::departmentName((int)$row['dept_id']),
                    'points' => []
                ];
            }

            $series[$key]['points'][] = [
                'period' => sprintf('%04d-%02d', (int)$row['entry_year'], (int)$row['entry_month']),
                'numerator' => (float)$row['numerator_value'],
                'denominator' => (float)$row['denominator_value'],
                'result' => (float)$row['result_value']
            ];
        }

        foreach ($series as &$item) {
            $item['points'] = array_reverse($item['points']);
        }
        unset($item);

        return [
            'filters' => $filters,
            'series' => array_values($series)
        ];
    }
}
