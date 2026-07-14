<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Indicator Analytics Service
 * StateIndicatorAnalyticsService.php
 * Version 1.1.0 | Updated 2026-07-13
 * ==========================================================
 */

class StateIndicatorAnalyticsService
{
    public static function analytics(mysqli $con, array $filters = []): array
    {
        $pagination = self::pagination($filters, 25, 100);
        $minFacilities = max(1, (int)($filters['min_facilities'] ?? 1));

        return [
            'filters' => [
                'search' => trim((string)($filters['search'] ?? '')),
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'min_facilities' => $minFacilities
            ],
            'assessment' => self::assessmentWeakIndicators($con, $filters, $pagination, $minFacilities)
        ];
    }

    public static function streamZeroFacilityList(mysqli $con, int $checkpointId, array $filters = []): void
    {
        $responseTable = self::responseTable($con);
        if ($checkpointId <= 0 || $responseTable === '' || !self::tableExists($con, 'assessment_master')) {
            Response::validation(['checkpoint_id' => 'Valid checkpoint ID is required.']);
        }

        $filename = 'saqshi-zero-score-checkpoint-' . $checkpointId . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        self::csvRow($out, [
            'District', 'Block', 'Facility Name', 'NIN', 'Facility Type',
            'Assessment ID', 'Assessment Name', 'Checkpoint ID', 'Checkpoint',
            'Department', 'Standard', 'Score', 'Response Value', 'Updated On'
        ]);

        $assessmentColumn = self::columnExists($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
        $where = self::facilityWhere($filters, 'f');
        $where['sql'] .= ' AND r.checkpoint_id = ? AND r.score <= 0';
        $where['types'] .= 'i';
        $where['params'][] = $checkpointId;
        $meta = self::checkpointMap()[(string)$checkpointId] ?? [];

        $rows = self::rows($con, "
            SELECT f.Dist_Name, f.Block_Name, f.fac_name, f.NIN_no, f.Health_facilty_type,
                   a.assessment_id, a.assessment_name, r.checkpoint_id, r.score,
                   r.response_value, r.updated_on
            FROM {$responseTable} r
            LEFT JOIN assessment_master a ON a.assessment_id = r.{$assessmentColumn}
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$where['sql']}
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name, a.assessment_id DESC
        ", $where['types'], $where['params']);

        foreach ($rows as $row) {
            self::csvRow($out, [
                $row['Dist_Name'] ?? '',
                $row['Block_Name'] ?? '',
                $row['fac_name'] ?? '',
                $row['NIN_no'] ?? '',
                self::facilityTypeName($row['Health_facilty_type'] ?? ''),
                $row['assessment_id'] ?? '',
                $row['assessment_name'] ?? '',
                $row['checkpoint_id'] ?? '',
                $meta['checkpoint'] ?? '',
                $meta['department_name'] ?? '',
                $meta['standard'] ?? '',
                $row['score'] ?? '',
                $row['response_value'] ?? '',
                $row['updated_on'] ?? ''
            ]);
        }

        fclose($out);
        exit;
    }

    private static function assessmentWeakIndicators(mysqli $con, array $filters, array $pagination, int $minFacilities): array
    {
        $responseTable = self::responseTable($con);
        if ($responseTable === '' || !self::tableExists($con, 'assessment_master')) {
            return ['summary' => ['indicators' => 0, 'responses' => 0, 'facilities' => 0], 'rows' => []];
        }

        $assessmentColumn = self::columnExists($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
        $where = self::facilityWhere($filters, 'f');
        $zeroWhere = $where;
        $zeroWhere['sql'] .= ' AND r.score <= 0';
        $meta = self::checkpointMap();

        $summary = self::one($con, "
            SELECT COUNT(DISTINCT r.checkpoint_id) AS indicators,
                   COUNT(*) AS responses,
                   COUNT(DISTINCT a.fac_id_fk) AS facilities
            FROM {$responseTable} r
            LEFT JOIN assessment_master a ON a.assessment_id = r.{$assessmentColumn}
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$zeroWhere['sql']}
        ", $zeroWhere['types'], $zeroWhere['params']);

        $total = self::one($con, "
            SELECT COUNT(*) AS row_count
            FROM (
                SELECT r.checkpoint_id,
                       COUNT(DISTINCT a.fac_id_fk) AS zero_facility_count
                FROM {$responseTable} r
                LEFT JOIN assessment_master a ON a.assessment_id = r.{$assessmentColumn}
                LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
                {$zeroWhere['sql']}
                GROUP BY r.checkpoint_id
                HAVING zero_facility_count >= ?
            ) weak_indicators
        ", $zeroWhere['types'] . 'i', array_merge($zeroWhere['params'], [$minFacilities]));

        $rows = self::rows($con, "
            SELECT
                r.checkpoint_id,
                COUNT(DISTINCT a.fac_id_fk) AS facility_count,
                COUNT(*) AS response_count,
                COUNT(DISTINCT a.fac_id_fk) AS zero_facility_count,
                COUNT(*) AS zero_count,
                0 AS partial_count,
                0 AS full_count,
                ROUND(AVG(r.score), 2) AS average_score,
                100 AS zero_rate
            FROM {$responseTable} r
            LEFT JOIN assessment_master a ON a.assessment_id = r.{$assessmentColumn}
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$zeroWhere['sql']}
            GROUP BY r.checkpoint_id
            HAVING zero_facility_count >= ?
            ORDER BY zero_facility_count DESC, zero_count DESC, facility_count DESC
            LIMIT ? OFFSET ?
        ", $zeroWhere['types'] . 'iii', array_merge($zeroWhere['params'], [$minFacilities, $pagination['per_page'], $pagination['offset']]));

        foreach ($rows as &$row) {
            $id = (string)($row['checkpoint_id'] ?? '');
            $details = $meta[$id] ?? [];
            $row['indicator_type'] = 'ASSESSMENT';
            $row['indicator_name'] = $details['checkpoint'] ?? ('Checkpoint ' . $id);
            $row['department'] = $details['department_name'] ?? '';
            $row['area_of_concern'] = $details['concern_name'] ?? '';
            $row['standard'] = $details['standard'] ?? '';
            $row['weakness_rate'] = (float)($row['zero_rate'] ?? 0);
            $row['weakness_label'] = self::weaknessLabel((float)$row['zero_rate']);
            $row['download_key'] = $id;
        }
        unset($row);

        return [
            'summary' => [
                'indicators' => (int)($summary['indicators'] ?? 0),
                'responses' => (int)($summary['responses'] ?? 0),
                'facilities' => (int)($summary['facilities'] ?? 0)
            ],
            'pagination' => self::paginationMeta($pagination, (int)($total['row_count'] ?? 0)),
            'rows' => $rows
        ];
    }

    private static function performanceWeakIndicators(mysqli $con, array $filters, string $type, int $limit, int $minFacilities): array
    {
        if (!self::tableExists($con, 'performance_entries')) {
            return ['summary' => ['indicators' => 0, 'entries' => 0, 'facilities' => 0], 'rows' => []];
        }

        $where = self::facilityWhere($filters, 'f');
        $where['sql'] .= ' AND pe.indicator_type = ?';
        $where['types'] .= 's';
        $where['params'][] = $type;
        $meta = self::performanceIndicatorMap();

        $summary = self::one($con, "
            SELECT COUNT(DISTINCT pe.indicator_id) AS indicators,
                   COUNT(*) AS entries,
                   COUNT(DISTINCT pe.fac_id) AS facilities
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
        ", $where['types'], $where['params']);

        $rows = self::rows($con, "
            SELECT
                pe.indicator_type,
                pe.indicator_id,
                MAX(pe.indicator_code) AS indicator_code,
                MAX(pe.indicator_name) AS indicator_name,
                COUNT(*) AS entry_count,
                COUNT(DISTINCT pe.fac_id) AS facility_count,
                COUNT(DISTINCT CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'))) AS month_count,
                SUM(CASE WHEN pe.result_value <= 0 THEN 1 ELSE 0 END) AS zero_result_count,
                ROUND(AVG(pe.result_value), 2) AS average_result,
                ROUND(MIN(pe.result_value), 2) AS min_result,
                ROUND(MAX(pe.result_value), 2) AS max_result,
                ROUND((SUM(CASE WHEN pe.result_value <= 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS zero_rate
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
            GROUP BY pe.indicator_type, pe.indicator_id
            HAVING facility_count >= ?
            ORDER BY zero_rate DESC, average_result ASC, facility_count DESC
            LIMIT ?
        ", $where['types'] . 'ii', array_merge($where['params'], [$minFacilities, $limit]));

        foreach ($rows as &$row) {
            $key = strtoupper((string)($row['indicator_type'] ?? $type)) . ':' . (string)($row['indicator_id'] ?? '');
            $details = $meta[$key] ?? $meta[(string)($row['indicator_id'] ?? '')] ?? [];
            $row['indicator_name'] = $row['indicator_name'] ?: ($details['indicator_name'] ?? ('Indicator ' . ($row['indicator_id'] ?? '')));
            $row['indicator_code'] = $row['indicator_code'] ?: ($details['indicator_code'] ?? '');
            $row['weakness_rate'] = (float)($row['zero_rate'] ?? 0);
            $row['weakness_label'] = self::weaknessLabel((float)$row['zero_rate']);
        }
        unset($row);

        return [
            'summary' => [
                'indicators' => (int)($summary['indicators'] ?? 0),
                'entries' => (int)($summary['entries'] ?? 0),
                'facilities' => (int)($summary['facilities'] ?? 0)
            ],
            'rows' => $rows
        ];
    }

    private static function weaknessLabel(float $rate): string
    {
        if ($rate >= 80) {
            return 'Critical';
        }
        if ($rate >= 50) {
            return 'High';
        }
        if ($rate >= 25) {
            return 'Moderate';
        }
        return 'Watch';
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

    private static function csvRow($out, array $fields): void
    {
        fputcsv($out, $fields, ',', '"', '', "\r\n");
    }

    private static function responseTable(mysqli $con): string
    {
        if (self::tableExists($con, 'assessment_response')) {
            return 'assessment_response';
        }
        if (self::tableExists($con, 'assessment_cycle_response')) {
            return 'assessment_cycle_response';
        }
        return '';
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
                                'checkpoint' => (string)($checkpoint['Checkpoint'] ?? '')
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
        foreach ([__DIR__ . '/../config/performance/outcome.json', __DIR__ . '/../config/performance/kpi.json'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data)) {
                self::collectPerformanceIndicators($data, $map);
            }
        }

        return $map;
    }

    private static function collectPerformanceIndicators(array $node, array &$map): void
    {
        if (isset($node['indicator_id']) || isset($node['kpi_id']) || isset($node['outcome_id'])) {
            $id = (string)($node['indicator_id'] ?? $node['kpi_id'] ?? $node['outcome_id'] ?? '');
            $type = strtoupper((string)($node['indicator_type'] ?? (str_contains((string)($node['indicator_code'] ?? ''), 'KPI') ? 'KPI' : 'OUTCOME')));
            if ($id !== '') {
                $details = [
                    'indicator_code' => (string)($node['indicator_code'] ?? $node['kpi_code'] ?? $node['outcome_code'] ?? ''),
                    'indicator_name' => (string)($node['indicator_name'] ?? $node['kpi_name'] ?? $node['outcome_name'] ?? '')
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

    private static function facilityWhere(array $filters, string $alias): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];
        foreach ([
            'state_code' => "{$alias}.state_code",
            'division' => "{$alias}.division",
            'district' => "{$alias}.Dist_Name",
            'block' => "{$alias}.Block_Name",
            'facility_type' => "{$alias}.Health_facilty_type"
        ] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $types .= in_array($key, ['state_code', 'facility_type'], true) ? 'i' : 's';
                $params[] = in_array($key, ['state_code', 'facility_type'], true) ? (int)$filters[$key] : (string)$filters[$key];
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "({$alias}.fac_name LIKE ? OR CAST({$alias}.NIN_no AS CHAR) LIKE ? OR {$alias}.Dist_Name LIKE ? OR {$alias}.Block_Name LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }

        return ['sql' => 'WHERE ' . implode(' AND ', $where), 'types' => $types, 'params' => $params];
    }

    private static function pagination(array $filters, int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min($maxPerPage, max(10, (int)($filters['per_page'] ?? $defaultPerPage)));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage
        ];
    }

    private static function paginationMeta(array $pagination, int $totalRows): array
    {
        return [
            'page' => (int)$pagination['page'],
            'per_page' => (int)$pagination['per_page'],
            'total_rows' => $totalRows,
            'total_pages' => max(1, (int)ceil($totalRows / max(1, (int)$pagination['per_page'])))
        ];
    }

    private static function rows(mysqli $con, string $sql, string $types = '', array $params = []): array
    {
        $stmt = self::prepare($con, $sql, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    private static function one(mysqli $con, string $sql, string $types = '', array $params = []): array
    {
        $rows = self::rows($con, $sql, $types, $params);
        return $rows[0] ?? [];
    }

    private static function prepare(mysqli $con, string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Indicator analytics query prepare failed: ' . $con->error);
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

    private static function tableExists(mysqli $con, string $table): bool
    {
        $stmt = $con->prepare("SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
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

    private static function columnExists(mysqli $con, string $table, string $column): bool
    {
        $stmt = $con->prepare("SELECT COUNT(*) AS column_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
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
