<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State Monitoring Services
 * StateDashboardService.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

require_once __DIR__ . '/CertificationService.php';
require_once __DIR__ . '/CertificationExpiryService.php';
require_once __DIR__ . '/PerformanceService.php';
require_once __DIR__ . '/../core/FrameworkEngine.php';

/**
 * Provides state dashboard service behavior for SaQshi API workflows.
 */
class StateDashboardService
{
    /**
     * Handles require state role processing for this API workflow.
     */
    public static function requireStateRole(): void
    {
        if (!in_array((int)SessionManager::roleId(), [4, 5, 8, 9], true)) {
            Response::forbidden('Monitoring is available only for active State, Regional, District or Block role users.');
        }
    }

    /**
     * Handles apply monitoring scope processing for this API workflow.
     */
    public static function applyMonitoringScope(array $filters): array
    {
        $roleId = (int)SessionManager::roleId();
        $user = SessionManager::user();
        $scope = self::monitoringScopeForUser($user);

        if ($roleId === 9) {
            $filters['_scope_level'] = 'STATE';
            $filters['_scope_label'] = 'State';
            return $filters;
        }

        if (($scope['level'] ?? '') === 'REGIONAL') {
            $filters['division'] = (string)$scope['division'];
        } elseif (($scope['level'] ?? '') === 'DISTRICT') {
            $filters['district'] = (string)$scope['district'];
        } elseif (($scope['level'] ?? '') === 'BLOCK') {
            $filters['district'] = (string)$scope['district'];
            $filters['block'] = (string)$scope['block'];
        } else {
            $filters['district'] = '__NO_MONITORING_SCOPE__';
        }

        $filters['_scope_level'] = (string)($scope['level'] ?? 'UNKNOWN');
        $filters['_scope_label'] = (string)($scope['label'] ?? 'Restricted');
        return $filters;
    }

    /**
     * Handles monitoring scope for user processing for this API workflow.
     */
    private static function monitoringScopeForUser(array $user): array
    {
        $roleId = (int)($user['role_id'] ?? 0);

        if ($roleId === 9) {
            return ['level' => 'STATE', 'label' => 'State'];
        }

        $divisionId = (int)($user['division_id'] ?? 0);
        $districtId = (int)($user['dist_id'] ?? 0);
        $blockId = (int)($user['block_id'] ?? 0);

        $matchedDivision = '';
        $matchedDistrict = '';
        $matchedBlock = '';

        foreach (self::facilitiesFromJson() as $facility) {
            if ($roleId === 5 && $divisionId > 0 && (int)($facility['division_id'] ?? 0) === $divisionId) {
                $matchedDivision = (string)($facility['division'] ?? '');
                break;
            }

            if ($roleId === 4 && $districtId > 0 && (int)($facility['dist_id'] ?? 0) === $districtId) {
                $matchedDistrict = (string)($facility['Dist_Name'] ?? '');
                break;
            }

            if ($roleId === 8 && $blockId > 0 && (int)($facility['block_id'] ?? 0) === $blockId) {
                $matchedDistrict = (string)($facility['Dist_Name'] ?? '');
                $matchedBlock = (string)($facility['Block_Name'] ?? '');
                break;
            }
        }

        if ($roleId === 5 && $matchedDivision !== '') {
            return ['level' => 'REGIONAL', 'label' => $matchedDivision, 'division' => $matchedDivision];
        }

        if ($roleId === 4 && $matchedDistrict !== '') {
            return ['level' => 'DISTRICT', 'label' => $matchedDistrict, 'district' => $matchedDistrict];
        }

        if ($roleId === 8 && $matchedBlock !== '') {
            return ['level' => 'BLOCK', 'label' => $matchedBlock, 'district' => $matchedDistrict, 'block' => $matchedBlock];
        }

        return ['level' => 'UNKNOWN', 'label' => 'Restricted'];
    }

    /**
     * Handles dashboard processing for this API workflow.
     */
    public static function dashboard(mysqli $con, array $filters = []): array
    {
        return [
            'filters' => self::normalizeFilters($filters),
            'facility_category' => self::safeSection('facility_category', fn() => self::facilityCategory($con, $filters), ['total_facilities' => 0, 'facility_types' => [], 'districts' => []]),
            'certification_summary' => self::safeSection('certification_summary', fn() => self::certificationSummary($con, $filters), ['total' => 0, 'status' => [], 'map_points' => []]),
            'assessment_summary' => self::safeSection('assessment_summary', fn() => self::assessmentProgress($con, $filters, true), ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0]),
            'cqi_summary' => self::safeSection('cqi_summary', fn() => self::cqiSummary($con, $filters), ['total_action_plans' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'rows' => []]),
            'performance_summary' => self::safeSection('performance_summary', fn() => self::performanceSummary($con, $filters), ['months' => [], 'kpi_submitted' => 0, 'outcome_submitted' => 0]),
            'current_month_status' => self::safeSection('current_month_status', fn() => self::currentMonthStatus($con, $filters), ['month' => (int)date('n'), 'year' => (int)date('Y'), 'assessment' => [], 'performance' => []]),
            'attention' => self::safeSection('attention', fn() => self::latestAssessmentAttention($con, $filters)['rows'] ?? [], [])
        ];
    }

    /**
     * Handles safe section processing for this API workflow.
     */
    private static function safeSection(string $section, callable $callback, array $fallback): array
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log('State dashboard section failed: ' . $section, [
                    'error' => $e->getMessage()
                ]);
            }

            $fallback['_error'] = 'Unable to load ' . str_replace('_', ' ', $section) . ' right now.';
            return $fallback;
        }
    }

    /**
     * Handles facility category processing for this API workflow.
     */
    public static function facilityCategory(mysqli $con, array $filters = []): array
    {
        $facilities = self::filteredFacilities($filters);
        $total = count($facilities);
        $typeCounts = [];
        $districtCounts = [];

        foreach ($facilities as $facility) {
            $type = self::facilityTypeLabel($facility);
            $district = trim((string)($facility['Dist_Name'] ?? $facility['district'] ?? 'Unknown'));
            $block = trim((string)($facility['Block_Name'] ?? $facility['block'] ?? 'Unknown'));

            $typeCounts[$type !== '' ? $type : 'Unknown'] = ($typeCounts[$type !== '' ? $type : 'Unknown'] ?? 0) + 1;
            $districtName = $district !== '' ? $district : 'Unknown';
            $blockName = $block !== '' ? $block : 'Unknown';

            if (!isset($districtCounts[$districtName])) {
                $districtCounts[$districtName] = [
                    'count' => 0,
                    'blocks' => []
                ];
            }
            if (!isset($districtCounts[$districtName]['blocks'][$blockName])) {
                $districtCounts[$districtName]['blocks'][$blockName] = [
                    'count' => 0,
                    'facility_types' => []
                ];
            }

            $districtCounts[$districtName]['count']++;
            $districtCounts[$districtName]['blocks'][$blockName]['count']++;
            $districtCounts[$districtName]['blocks'][$blockName]['facility_types'][$type] =
                ($districtCounts[$districtName]['blocks'][$blockName]['facility_types'][$type] ?? 0) + 1;
        }

        arsort($typeCounts);
        uasort($districtCounts, fn($a, $b) => ($b['count'] <=> $a['count']));

        $facilityTypes = [];
        foreach ($typeCounts as $type => $count) {
            $facilityTypes[] = [
                'facility_type' => $type,
                'count' => $count,
                'percentage' => self::percent((float)$count, (float)$total)
            ];
        }

        $districts = [];
        foreach ($districtCounts as $district => $details) {
            $blocks = [];
            uasort($details['blocks'], fn($a, $b) => ($b['count'] <=> $a['count']));

            foreach ($details['blocks'] as $block => $blockDetails) {
                arsort($blockDetails['facility_types']);
                $blockTypes = [];

                foreach ($blockDetails['facility_types'] as $type => $count) {
                    $blockTypes[] = [
                        'facility_type' => $type,
                        'count' => (int)$count
                    ];
                }

                $blocks[] = [
                    'block' => $block,
                    'count' => (int)$blockDetails['count'],
                    'facility_types' => $blockTypes
                ];
            }

            $districts[] = [
                'district' => $district,
                'count' => (int)$details['count'],
                'percentage' => self::percent((float)$details['count'], (float)$total),
                'blocks' => $blocks
            ];
        }

        return [
            'total_facilities' => (int)$total,
            'facility_types' => $facilityTypes,
            'districts' => $districts
        ];
    }

    /**
     * Handles facility type label processing for this API workflow.
     */
    private static function facilityTypeLabel(array $facility): string
    {
        $direct = trim((string)(
            $facility['facilities_type'] ??
            $facility['facility_type_name'] ??
            $facility['facility_type'] ??
            ''
        ));

        if ($direct !== '' && !ctype_digit($direct)) {
            return $direct;
        }

        $typeId = (int)($facility['Health_facilty_type'] ?? $facility['fac_type_id'] ?? $direct);
        $typeMap = self::facilityTypeMap();

        if ($typeId > 0 && isset($typeMap[$typeId])) {
            return $typeMap[$typeId];
        }

        return $direct !== '' ? $direct : 'Unknown';
    }

    /**
     * Handles facility type map processing for this API workflow.
     */
    private static function facilityTypeMap(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];
        $path = __DIR__ . '/../config/masters/facility_types.json';

        if (!is_file($path)) {
            return $map;
        }

        $rows = json_decode((string)file_get_contents($path), true);
        if (!is_array($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            $id = (int)($row['fac_type_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $label = trim((string)($row['fac'] ?? $row['facilities_type'] ?? ''));
            if ($label !== '') {
                $map[$id] = $label;
            }
        }

        return $map;
    }

    /**
     * Handles certification summary processing for this API workflow.
     */
    public static function certificationSummary(mysqli $con, array $filters = []): array
    {
        $facilities = self::filteredFacilities($filters);
        $allRows = !empty($filters['_all_facilities']);
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = $allRows ? max(1, count($facilities)) : min(100, max(10, (int)($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        $statusFilter = strtoupper(trim((string)($filters['status'] ?? '')));
        $facilitiesByNin = [];
        $facilitiesById = [];
        foreach ($facilities as $facility) {
            $nin = self::normalizeNin($facility['NIN_no'] ?? $facility['fac_nin'] ?? '');
            if ($nin !== '') {
                $facilitiesByNin[$nin] = $facility;
            }
            $id = (int)($facility['fac_id'] ?? 0);
            if ($id > 0) {
                $facilitiesById[$id] = $facility;
            }
        }

        $latestByKey = [];
        $historyWarning = null;

        try {
            $hasHistoryTable = self::tableExists($con, 'certification_history');
        } catch (Throwable $e) {
            $hasHistoryTable = false;
            $historyWarning = $e->getMessage();
        }

        if ($hasHistoryTable) {
            try {
                $historyRows = self::rows($con, "
                    SELECT
                        history_id,
                        certification_id,
                        fac_id_fk,
                        fac_nin,
                        old_data_json,
                        new_data_json,
                        action_type,
                        action_by,
                        action_on
                    FROM certification_history
                    ORDER BY history_id DESC
                    LIMIT 5000
                ");

                foreach ($historyRows as $row) {
                    $nin = self::normalizeNin($row['fac_nin'] ?? '');
                    $key = $nin !== '' ? 'NIN:' . $nin : 'FAC:' . (int)($row['fac_id_fk'] ?? 0);
                    if ($key !== 'FAC:0' && !isset($latestByKey[$key])) {
                        $latestByKey[$key] = $row;
                    }
                }
            } catch (Throwable $e) {
                $latestByKey = [];
                $historyWarning = $e->getMessage();

                if (class_exists('ErrorHandler')) {
                    ErrorHandler::log('State certification history query failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $statusCounts = [];
        $points = [];
        $facilityRows = [];
        $matchedRows = 0;

        foreach ($facilities as $facility) {
            $facId = (int)($facility['fac_id'] ?? 0);
            $nin = self::normalizeNin($facility['NIN_no'] ?? $facility['nin_no'] ?? '');
            $history = $latestByKey['NIN:' . $nin] ?? $latestByKey['FAC:' . $facId] ?? null;
            $payload = $history ? self::decodeJsonObject($history['new_data_json'] ?? '') : [];
            $status = self::certStatusFromPayload($payload);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $raw = isset($payload['raw']) && is_array($payload['raw']) ? $payload['raw'] : [];

            $row = [
                'fac_id' => $facId,
                'fac_name' => (string)($facility['fac_name'] ?? ''),
                'lat' => $facility['lat'] ?? null,
                'longit' => $facility['longit'] ?? $facility['longi'] ?? null,
                'district' => (string)($facility['Dist_Name'] ?? $facility['district'] ?? ''),
                'block' => (string)($facility['Block_Name'] ?? ''),
                'division' => (string)($facility['division'] ?? ''),
                'facility_type' => (string)($facility['facilities_type'] ?? $facility['facility_type'] ?? ''),
                'fac_nin' => $nin,
                'status' => $status,
                'certification_type' => strtoupper((string)($payload['certification_type'] ?? $payload['cert_type'] ?? $raw['cert_type'] ?? '')),
                'assessment_mode' => strtoupper((string)($payload['assessment_mode'] ?? $payload['ass_mod'] ?? $raw['ass_mod'] ?? '')),
                'certification_date' => $payload['certification_date'] ?? $payload['date_of_ass'] ?? $raw['date_of_ass'] ?? null,
                'applied_date' => $payload['applied_date'] ?? $raw['applied_date'] ?? null,
                'valid_from' => $payload['valid_from'] ?? $payload['cert_issue'] ?? $raw['cert_issue'] ?? null,
                'valid_to' => $payload['valid_to'] ?? $payload['validity'] ?? $raw['validity'] ?? null,
                'renewal_status' => $payload['renewal_status'] ?? null,
                'score' => isset($payload['score']) ? (float)$payload['score'] : null,
                'remarks' => $payload['remarks'] ?? $payload['cert_detailscol'] ?? $raw['cert_detailscol'] ?? null,
                'last_action' => $history['action_type'] ?? null,
                'last_updated_on' => $history['action_on'] ?? null
            ];

            if (self::certificationRowMatches($row, $search, $statusFilter)) {
                if ($allRows || ($matchedRows >= $offset && count($facilityRows) < $perPage)) {
                    $facilityRows[] = $row;
                }
                $matchedRows++;
            }
            $points[] = $row;
        }

        arsort($statusCounts);
        $rows = [];
        foreach ($statusCounts as $status => $count) {
            $rows[] = [
                'status' => $status,
                'count' => $count,
                'percentage' => self::percent((float)$count, (float)count($facilities))
            ];
        }

        $response = [
            'total' => array_sum(array_values($statusCounts)),
            'status' => $rows,
            'facilities' => $facilityRows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_rows' => $matchedRows,
                'total_pages' => max(1, (int)ceil($matchedRows / $perPage))
            ],
            'map_points' => array_slice($points, 0, 500)
        ];

        if ($historyWarning) {
            $response['_warning'] = 'Certification history could not be fully loaded. Showing facility list with available/default status.';
        }

        return $response;
    }

    /**
     * Handles certification row matches processing for this API workflow.
     */
    private static function certificationRowMatches(array $row, string $search, string $statusFilter): bool
    {
        if ($statusFilter !== '' && strtoupper((string)($row['status'] ?? '')) !== $statusFilter) {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            $row['fac_name'] ?? '',
            $row['fac_nin'] ?? '',
            $row['division'] ?? '',
            $row['district'] ?? '',
            $row['block'] ?? '',
            $row['facility_type'] ?? '',
            $row['status'] ?? '',
            $row['renewal_status'] ?? ''
        ]));

        return str_contains($haystack, $search);
    }

    /**
     * Handles certification map processing for this API workflow.
     */
    public static function certificationMap(mysqli $con, array $filters = []): array
    {
        $filters['_all_facilities'] = true;
        $summary = self::certificationSummary($con, $filters);
        $coordinates = self::facilityCoordinatesFromDb($con, $filters);
        $points = [];
        $statusCounts = [];
        $typeCounts = [
            'STATE' => 0,
            'NATIONAL' => 0,
            'UNKNOWN' => 0
        ];

        foreach (($summary['facilities'] ?? []) as $facility) {
            $facId = (int)($facility['fac_id'] ?? 0);
            $nin = self::normalizeNin($facility['fac_nin'] ?? $facility['NIN_no'] ?? '');
            $coordinate = $coordinates['id'][$facId] ?? ($nin !== '' ? ($coordinates['nin'][$nin] ?? null) : null);

            if (!$coordinate) {
                continue;
            }

            $status = self::normalizeCertStatus($facility['status'] ?? '') ?: 'NOT CERTIFIED';
            if (!in_array($status, ['CERTIFIED', 'CONDITIONAL'], true) && empty($filters['all_status'])) {
                continue;
            }

            $certType = strtoupper(trim((string)($facility['certification_type'] ?? '')));
            $certType = in_array($certType, ['STATE', 'NATIONAL'], true) ? $certType : 'UNKNOWN';

            $lat = (float)$coordinate['lat'];
            $lng = (float)$coordinate['longit'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) {
                continue;
            }

            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $typeCounts[$certType] = ($typeCounts[$certType] ?? 0) + 1;
            $points[] = [
                'fac_id' => $facId,
                'fac_name' => $facility['fac_name'] ?: ($coordinate['fac_name'] ?? ''),
                'fac_nin' => $nin,
                'facility_type' => $facility['facility_type'] ?: ($coordinate['facility_type'] ?? ''),
                'district' => $facility['district'] ?: ($coordinate['district'] ?? ''),
                'block' => $facility['block'] ?: ($coordinate['block'] ?? ''),
                'status' => $status,
                'score' => $facility['score'] ?? null,
                'certification_type' => $certType,
                'certification_date' => $facility['certification_date'] ?? null,
                'valid_to' => $facility['valid_to'] ?? null,
                'lat' => $lat,
                'longit' => $lng
            ];
        }

        return [
            'map_config' => self::mapConfig($filters),
            'status' => array_map(
                fn($status, $count) => ['status' => $status, 'count' => $count],
                array_keys($statusCounts),
                array_values($statusCounts)
            ),
            'certification_categories' => [
                ['type' => 'STATE', 'count' => $typeCounts['STATE'] ?? 0],
                ['type' => 'NATIONAL', 'count' => $typeCounts['NATIONAL'] ?? 0],
                ['type' => 'UNKNOWN', 'count' => $typeCounts['UNKNOWN'] ?? 0]
            ],
            'map_points' => $points,
            'total_points' => count($points),
            'total_facilities' => $summary['total'] ?? 0
        ];
    }

    /**
     * Handles update certification status processing for this API workflow.
     */
    public static function updateCertificationStatus(mysqli $con, array $payload): array
    {
        $facId = (int)($payload['fac_id'] ?? 0);
        $nin = self::normalizeNin($payload['fac_nin'] ?? $payload['nin_no'] ?? '');
        $status = self::normalizeCertStatus($payload['status'] ?? $payload['Cert_status'] ?? '');
        $remarks = trim((string)($payload['remarks'] ?? ''));
        $certificationType = strtoupper(trim((string)($payload['certification_type'] ?? $payload['cert_type'] ?? '')));
        $assessmentMode = strtoupper(trim((string)($payload['assessment_mode'] ?? $payload['ass_mod'] ?? '')));
        $certificationDate = self::dateOrEmpty($payload['certification_date'] ?? $payload['date_of_ass'] ?? '');
        $appliedDate = self::dateOrEmpty($payload['applied_date'] ?? '');
        $score = ($payload['score'] ?? '') !== '' && is_numeric($payload['score'])
            ? round((float)$payload['score'], 2)
            : null;
        $config = CertificationService::config();

        if ($facId <= 0 && $nin === '') {
            throw new InvalidArgumentException('Facility ID or NIN is required.');
        }

        if ($status === '') {
            throw new InvalidArgumentException('Certification status is required.');
        }

        if ($certificationType === '') {
            throw new InvalidArgumentException('Certification type is required.');
        }

        if (!in_array($certificationType, $config['types'] ?? [], true)) {
            throw new InvalidArgumentException('Certification type must be STATE or NATIONAL.');
        }

        if (!in_array($assessmentMode, $config['assessment_modes'] ?? [], true)) {
            throw new InvalidArgumentException('Assessment mode must be PHYSICAL or VIRTUAL.');
        }

        if ($certificationDate === '') {
            throw new InvalidArgumentException('Certification date is required.');
        }

        if ($certificationDate > date('Y-m-d')) {
            throw new InvalidArgumentException('Certification date cannot be a future date.');
        }

        if ($appliedDate !== '' && $appliedDate > $certificationDate) {
            throw new InvalidArgumentException('Applied date cannot be greater than certification date.');
        }

        if ($score === null || $score < 0 || $score > 100) {
            throw new InvalidArgumentException('Score must be between 0 and 100.');
        }

        $validity = CertificationExpiryService::calculateValidTo($status, $certificationDate, $config);
        $renewalStatus = CertificationExpiryService::renewalStatus($validity['valid_to'], (int)($config['renewal_due_days'] ?? 90));

        $facility = [];
        if ($facId > 0) {
            $facility = self::facilitiesById()[$facId] ?? [];
        }
        if (!$facility && $nin !== '') {
            foreach (self::facilitiesFromJson() as $candidate) {
                if (self::normalizeNin($candidate['NIN_no'] ?? $candidate['nin_no'] ?? '') === $nin) {
                    $facility = $candidate;
                    $facId = (int)($candidate['fac_id'] ?? 0);
                    break;
                }
            }
        }

        if (!$facility) {
            throw new InvalidArgumentException('Facility not found in facilities.json.');
        }

        $nin = $nin !== '' ? $nin : self::normalizeNin($facility['NIN_no'] ?? $facility['nin_no'] ?? '');
        $old = self::latestCertificationHistory($con, $facId, $nin);
        $oldPayload = $old ? self::decodeJsonObject($old['new_data_json'] ?? '') : null;
        $oldPayloadData = $oldPayload ?: [];
        $newPayload = array_merge($oldPayloadData, [
            'certification_id' => $oldPayloadData['certification_id'] ?? $old['certification_id'] ?? null,
            'fac_id' => $facId,
            'fac_nin' => $nin,
            'fac_name' => $facility['fac_name'] ?? '',
            'fac_type' => $facility['facilities_type'] ?? $facility['facility_type'] ?? '',
            'district' => $facility['Dist_Name'] ?? '',
            'block' => $facility['Block_Name'] ?? '',
            'facilities_type' => $facility['facilities_type'] ?? '',
            'Cert_status' => $status,
            'status' => $status,
            'certification_type' => $certificationType,
            'assessment_mode' => $assessmentMode,
            'ass_mod' => $assessmentMode,
            'certification_date' => $certificationDate,
            'applied_date' => $appliedDate !== '' ? $appliedDate : null,
            'date_of_ass' => $certificationDate,
            'validity_years' => $validity['validity_years'],
            'valid_from' => $validity['valid_from'],
            'valid_to' => $validity['valid_to'],
            'validity' => $validity['valid_to'],
            'renewal_status' => $renewalStatus,
            'score' => $score,
            'remarks' => $remarks,
            'updated_by_state_admin' => true,
            'updated_on' => date('Y-m-d H:i:s')
        ]);

        $stmt = $con->prepare("
            INSERT INTO certification_history
            (certification_id, fac_id_fk, fac_nin, old_data_json, new_data_json, action_type, action_by)
            VALUES (?,?,?,?,?,?,?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Certification history prepare failed: ' . $con->error);
        }

        $certificationId = isset($old['certification_id']) ? (int)$old['certification_id'] : null;
        $oldJson = $oldPayload ? json_encode($oldPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $newJson = json_encode($newPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $action = 'STATE_STATUS_UPDATE';
        $userId = SessionManager::userId();
        $stmt->bind_param('iissssi', $certificationId, $facId, $nin, $oldJson, $newJson, $action, $userId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Certification status update failed: ' . $stmt->error);
        }

        Event::dispatch('state.certification.status_updated', [
            'fac_id' => $facId,
            'fac_nin' => $nin,
            'status' => $status,
            'user_id' => $userId
        ]);

        return [
            'fac_id' => $facId,
            'fac_nin' => $nin,
            'status' => $status,
            'history_id' => $con->insert_id
        ];
    }

    /**
     * Handles assessment progress processing for this API workflow.
     */
    public static function assessmentProgress(mysqli $con, array $filters = [], bool $summaryOnly = false): array
    {
        if (!self::tableExists($con, 'assessment_master')) {
            return $summaryOnly ? ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0] : ['rows' => []];
        }

        $where = self::assessmentWhere($con, $filters);
        $statusRows = self::rows($con, "
            SELECT UPPER(COALESCE(a.status, 'UNKNOWN')) AS status, COUNT(*) AS count
            FROM assessment_master a
            {$where['sql']}
            GROUP BY UPPER(COALESCE(a.status, 'UNKNOWN'))
        ", $where['types'], $where['params']);

        $summary = ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0, 'other' => 0];
        foreach ($statusRows as $row) {
            $status = strtolower((string)($row['status'] ?? 'other'));
            $count = (int)($row['count'] ?? 0);
            $summary['total'] += $count;
            if (array_key_exists($status, $summary)) {
                $summary[$status] += $count;
            } else {
                $summary['other'] += $count;
            }
        }

        if ($summaryOnly) {
            return $summary;
        }

        $pagination = self::pagination($filters);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];
        $detailTotal = self::one($con, "
            SELECT COUNT(*) AS row_count
            FROM assessment_master a
            {$where['sql']}
        ", $where['types'], $where['params']);

        $rows = self::rows($con, "
            SELECT
                a.fac_id_fk,
                a.assessment_id,
                a.assessment_name,
                a.framework_code,
                a.start_date,
                a.end_date,
                a.status
            FROM assessment_master a
            {$where['sql']}
            ORDER BY a.assessment_id DESC
            LIMIT ? OFFSET ?
        ", $where['types'] . 'ii', array_merge($where['params'], [$perPage, $offset]));

        $facilitiesById = self::facilitiesById();
        $assessmentIds = [];
        $facilityLatest = [];

        foreach ($rows as &$row) {
            $assessmentId = (int)($row['assessment_id'] ?? 0);
            $facId = (int)($row['fac_id_fk'] ?? 0);
            if ($assessmentId > 0) {
                $assessmentIds[] = $assessmentId;
            }
            if ($facId > 0 && !isset($facilityLatest[$facId])) {
                $facilityLatest[$facId] = $assessmentId;
            }

            $facility = $facilitiesById[(int)($row['fac_id_fk'] ?? 0)] ?? [];
            $row['division'] = $facility['division'] ?? '';
            $row['district'] = $facility['Dist_Name'] ?? '';
            $row['block'] = $facility['Block_Name'] ?? '';
            $row['fac_name'] = $facility['fac_name'] ?? '';
            $row['NIN_no'] = $facility['NIN_no'] ?? $facility['nin_no'] ?? '';
        }
        unset($row);

        $assessmentIds = array_values(array_unique(array_filter($assessmentIds)));
        $deptMap = self::assessmentDepartmentProgress($con, $assessmentIds);
        $responseMap = self::assessmentResponseProgress($con, $assessmentIds);
        $actionPlanMap = self::assessmentActionPlanProgress($con, $assessmentIds);
        $engineCache = [];

        foreach ($rows as &$row) {
            $assessmentId = (int)($row['assessment_id'] ?? 0);
            $facId = (int)($row['fac_id_fk'] ?? 0);
            $facility = $facilitiesById[$facId] ?? [];
            $facTypeId = (int)($facility['fac_type_id'] ?? $facility['Health_facilty_type'] ?? 0);
            $deptProgress = $deptMap[$assessmentId] ?? [
                'dept_ids' => [],
                'total_departments' => 0,
                'completed_departments' => 0
            ];
            $responseProgress = $responseMap[$assessmentId] ?? [
                'checkpoint_done' => 0,
                'obtained_score' => 0,
                'final_obtained_score' => 0,
                'revised_checkpoints' => 0
            ];
            $actionProgress = $actionPlanMap[$assessmentId] ?? [
                'total_action_plans' => 0,
                'completed_action_plans' => 0
            ];
            $scoreBase = self::assessmentScoreBase(
                (string)($row['framework_code'] ?: 'saqshi-nqas'),
                $facTypeId,
                $deptProgress['dept_ids'],
                $engineCache
            );
            $totalCheckpoints = (int)$scoreBase['total_checkpoints'];
            $checkpointDone = (int)$responseProgress['checkpoint_done'];
            $totalScore = (float)$scoreBase['total_score'];
            $originalObtained = (float)$responseProgress['obtained_score'];
            $finalObtained = (float)$responseProgress['final_obtained_score'];
            $totalActionPlans = (int)$actionProgress['total_action_plans'];
            $completedActionPlans = (int)$actionProgress['completed_action_plans'];

            $row['is_latest'] = $facId > 0 && ($facilityLatest[$facId] ?? 0) === $assessmentId;
            $row['total_departments'] = (int)$deptProgress['total_departments'];
            $row['completed_departments'] = (int)$deptProgress['completed_departments'];
            $row['pending_departments'] = max(0, (int)$deptProgress['total_departments'] - (int)$deptProgress['completed_departments']);
            $row['checkpoint_done'] = $checkpointDone;
            $row['checkpoint_left'] = max(0, $totalCheckpoints - $checkpointDone);
            $row['total_checkpoints'] = $totalCheckpoints;
            $row['total_action_plans'] = $totalActionPlans;
            $row['completed_action_plans'] = $completedActionPlans;
            $row['pending_action_plans'] = max(0, $totalActionPlans - $completedActionPlans);
            $row['obtained_score'] = round($originalObtained, 2);
            $row['final_obtained_score'] = round($finalObtained, 2);
            $row['total_score'] = round($totalScore, 2);
            $row['score_percent'] = $totalScore > 0 ? round(($finalObtained / $totalScore) * 100, 2) : 0;
            $row['baseline_score_percent'] = $totalScore > 0 ? round(($originalObtained / $totalScore) * 100, 2) : 0;
            $row['revised_checkpoints'] = (int)$responseProgress['revised_checkpoints'];
        }
        unset($row);

        return [
            'summary' => $summary,
            'pagination' => self::paginationMeta($pagination, (int)($detailTotal['row_count'] ?? 0)),
            'rows' => $rows
        ];
    }

    /**
     * Handles assessment department progress processing for this API workflow.
     */
    private static function assessmentDepartmentProgress(mysqli $con, array $assessmentIds): array
    {
        if (!$assessmentIds) {
            return [];
        }

        $map = [];
        $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
        $types = str_repeat('i', count($assessmentIds));

        if (self::tableExists($con, 'assessment_department_status')) {
            $departmentStatusColumn = self::departmentStatusAssessmentColumn($con);

            $rows = self::rows($con, "
                SELECT {$departmentStatusColumn} AS assessment_id, dept_id
                FROM assessment_department_status
                WHERE is_active = 1
                  AND {$departmentStatusColumn} IN ({$placeholders})
            ", $types, $assessmentIds);

            foreach ($rows as $row) {
                $assessmentId = (int)($row['assessment_id'] ?? 0);
                $deptId = (int)($row['dept_id'] ?? 0);
                if ($assessmentId <= 0 || $deptId <= 0) {
                    continue;
                }
                $map[$assessmentId]['dept_ids'][$deptId] = $deptId;
            }
        }

        if (self::tableExists($con, 'assessment_department')) {
            $rows = self::rows($con, "
                SELECT assessment_id, dept_id, UPPER(COALESCE(status, '')) AS status
                FROM assessment_department
                WHERE assessment_id IN ({$placeholders})
            ", $types, $assessmentIds);

            foreach ($rows as $row) {
                $assessmentId = (int)($row['assessment_id'] ?? 0);
                $deptId = (int)($row['dept_id'] ?? 0);
                if ($assessmentId <= 0 || $deptId <= 0) {
                    continue;
                }
                $map[$assessmentId]['dept_ids'][$deptId] = $deptId;
                if (($row['status'] ?? '') === 'COMPLETED') {
                    $map[$assessmentId]['completed_dept_ids'][$deptId] = $deptId;
                }
            }
        }

        foreach ($map as $assessmentId => $details) {
            $map[$assessmentId]['dept_ids'] = array_values($details['dept_ids'] ?? []);
            $map[$assessmentId]['total_departments'] = count($map[$assessmentId]['dept_ids']);
            $map[$assessmentId]['completed_departments'] = count($details['completed_dept_ids'] ?? []);
        }

        return $map;
    }

    /**
     * Handles assessment response progress processing for this API workflow.
     */
    private static function assessmentResponseProgress(mysqli $con, array $assessmentIds): array
    {
        if (!$assessmentIds) {
            return [];
        }

        $responseTable = self::tableExists($con, 'assessment_response')
            ? 'assessment_response'
            : (self::tableExists($con, 'assessment_cycle_response') ? 'assessment_cycle_response' : '');

        if ($responseTable === '') {
            return [];
        }

        $assessmentColumn = self::columnExists($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
        if (!self::columnExists($con, $responseTable, $assessmentColumn)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
        $types = str_repeat('i', count($assessmentIds));
        $actionJoin = '';
        $finalScore = 'r.score';
        $revisedFlag = '0';

        if (self::tableExists($con, 'assessment_action_plan')) {
            $actionJoin = "
                LEFT JOIN assessment_action_plan ap
                  ON ap.assessment_id = r.{$assessmentColumn}
                 AND ap.dept_id = r.dept_id
                 AND ap.checkpoint_id = r.checkpoint_id
            ";
            $finalScore = 'COALESCE(ap.revised_score, r.score)';
            $revisedFlag = 'CASE WHEN ap.revised_score IS NOT NULL THEN 1 ELSE 0 END';
        }

        $rows = self::rows($con, "
            SELECT
                r.{$assessmentColumn} AS assessment_id,
                COUNT(DISTINCT r.checkpoint_id) AS checkpoint_done,
                ROUND(COALESCE(SUM(r.score), 0), 2) AS obtained_score,
                ROUND(COALESCE(SUM({$finalScore}), 0), 2) AS final_obtained_score,
                SUM({$revisedFlag}) AS revised_checkpoints
            FROM {$responseTable} r
            {$actionJoin}
            WHERE r.{$assessmentColumn} IN ({$placeholders})
            GROUP BY r.{$assessmentColumn}
        ", $types, $assessmentIds);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['assessment_id']] = [
                'checkpoint_done' => (int)($row['checkpoint_done'] ?? 0),
                'obtained_score' => (float)($row['obtained_score'] ?? 0),
                'final_obtained_score' => (float)($row['final_obtained_score'] ?? 0),
                'revised_checkpoints' => (int)($row['revised_checkpoints'] ?? 0)
            ];
        }

        return $map;
    }

    /**
     * Handles assessment action plan progress processing for this API workflow.
     */
    private static function assessmentActionPlanProgress(mysqli $con, array $assessmentIds): array
    {
        if (!$assessmentIds || !self::tableExists($con, 'assessment_action_plan')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
        $types = str_repeat('i', count($assessmentIds));
        $rows = self::rows($con, "
            SELECT
                assessment_id,
                COUNT(*) AS total_action_plans,
                SUM(CASE WHEN UPPER(COALESCE(status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS completed_action_plans
            FROM assessment_action_plan
            WHERE assessment_id IN ({$placeholders})
            GROUP BY assessment_id
        ", $types, $assessmentIds);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['assessment_id']] = [
                'total_action_plans' => (int)($row['total_action_plans'] ?? 0),
                'completed_action_plans' => (int)($row['completed_action_plans'] ?? 0)
            ];
        }

        return $map;
    }

    /**
     * Handles assessment score base processing for this API workflow.
     */
    private static function assessmentScoreBase(string $frameworkCode, int $facTypeId, array $deptIds, array &$engineCache): array
    {
        if ($facTypeId <= 0 || !$deptIds) {
            return ['total_checkpoints' => 0, 'total_score' => 0];
        }

        try {
            if (!isset($engineCache[$frameworkCode])) {
                $engineCache[$frameworkCode] = FrameworkEngine::load($frameworkCode);
            }

            $totalCheckpoints = 0;
            $totalScore = 0.0;

            foreach (array_unique(array_map('intval', $deptIds)) as $deptId) {
                if ($deptId <= 0) {
                    continue;
                }

                $seen = [];
                foreach ($engineCache[$frameworkCode]->getCheckpoints($facTypeId, $deptId) as $checkpoint) {
                    $checkpointId = (string)($checkpoint['csqa_id'] ?? '');
                    if ($checkpointId === '' || isset($seen[$checkpointId])) {
                        continue;
                    }

                    $seen[$checkpointId] = true;
                    $totalCheckpoints++;
                    $totalScore += self::checkpointMaxScore($checkpoint);
                }
            }

            return ['total_checkpoints' => $totalCheckpoints, 'total_score' => $totalScore];
        } catch (Throwable $e) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log('State assessment score base failed', [
                    'framework' => $frameworkCode,
                    'facility_type' => $facTypeId,
                    'error' => $e->getMessage()
                ]);
            }

            return ['total_checkpoints' => 0, 'total_score' => 0];
        }
    }

    /**
     * Handles checkpoint max score processing for this API workflow.
     */
    private static function checkpointMaxScore(array $checkpoint): float
    {
        $options = $checkpoint['response']['options'] ?? [];
        if (!is_array($options) || !$options) {
            return 2.0;
        }

        $scores = array_map(fn($option) => (float)($option['score'] ?? 0), $options);
        $max = max($scores);

        return $max > 0 ? $max : 2.0;
    }

    /**
     * Handles cqi summary processing for this API workflow.
     */
    public static function cqiSummary(mysqli $con, array $filters = []): array
    {
        if (!self::tableExists($con, 'assessment_action_plan')) {
            return ['facilities_with_action_plan' => 0, 'total_action_plans' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'rows' => []];
        }

        $where = self::actionPlanWhere($con, $filters);
        $today = date('Y-m-d');
        $pagination = self::pagination($filters);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];
        $row = self::one($con, "
            SELECT
                COUNT(*) AS facilities_with_action_plan,
                COALESCE(SUM(facility_total_action_plans), 0) AS total_action_plans,
                SUM(CASE WHEN facility_pending = 0 THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN facility_pending > 0 THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN facility_overdue > 0 THEN 1 ELSE 0 END) AS overdue
            FROM (
                SELECT
                    a.fac_id_fk,
                    COUNT(*) AS facility_total_action_plans,
                    SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS facility_pending,
                    SUM(CASE WHEN ap.target_date IS NOT NULL AND ap.target_date < ? AND UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS facility_overdue
                FROM assessment_action_plan ap
                LEFT JOIN assessment_master a ON a.assessment_id = ap.assessment_id
                LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
                {$where['sql']}
                GROUP BY a.fac_id_fk
            ) facility_cqi
        ", 's' . $where['types'], array_merge([$today], $where['params']));

        $detailTotal = self::one($con, "
            SELECT COUNT(*) AS row_count
            FROM (
                SELECT a.fac_id_fk, a.assessment_id
                FROM assessment_action_plan ap
                LEFT JOIN assessment_master a ON a.assessment_id = ap.assessment_id
                LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
                {$where['sql']}
                GROUP BY a.fac_id_fk, a.assessment_id
            ) grouped_cqi
        ", $where['types'], $where['params']);

        $rows = self::rows($con, "
            SELECT
                a.fac_id_fk,
                f.fac_name,
                f.Dist_Name AS district,
                f.Block_Name AS block,
                a.assessment_id,
                a.assessment_name,
                a.status AS assessment_status,
                COUNT(*) AS total_action_plans,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN ap.target_date IS NOT NULL AND ap.target_date < ? AND UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS overdue,
                MIN(ap.target_date) AS next_target_date,
                MAX(ap.updated_on) AS last_updated_on
            FROM assessment_action_plan ap
            LEFT JOIN assessment_master a ON a.assessment_id = ap.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            {$where['sql']}
            GROUP BY
                a.fac_id_fk,
                f.fac_name,
                f.Dist_Name,
                f.Block_Name,
                a.assessment_id,
                a.assessment_name,
                a.status
            ORDER BY f.Dist_Name, f.fac_name, a.assessment_id DESC
            LIMIT ? OFFSET ?
        ", 's' . $where['types'] . 'ii', array_merge([$today], $where['params'], [$perPage, $offset]));

        return [
            'facilities_with_action_plan' => (int)($row['facilities_with_action_plan'] ?? 0),
            'total_action_plans' => (int)($row['total_action_plans'] ?? 0),
            'completed' => (int)($row['completed'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
            'pagination' => self::paginationMeta($pagination, (int)($detailTotal['row_count'] ?? 0)),
            'rows' => $rows
        ];
    }

    /**
     * Handles performance summary processing for this API workflow.
     */
    public static function performanceSummary(mysqli $con, array $filters = []): array
    {
        if (!self::tableExists($con, 'performance_entries')) {
            return ['summary' => ['facilities' => 0, 'performance_entries' => 0, 'submitted_months' => 0, 'completed' => 0, 'in_progress' => 0, 'kpi_entries' => 0, 'outcome_entries' => 0], 'pagination' => self::paginationMeta(self::pagination($filters), 0), 'rows' => []];
        }

        $where = self::performanceWhere($con, $filters);
        $pagination = self::pagination($filters);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];

        $summary = self::one($con, "
            SELECT
                COUNT(DISTINCT pe.fac_id) AS facilities,
                SUM(CASE WHEN pe.indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN pe.indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(*) AS performance_entries,
                COUNT(DISTINCT CONCAT(pe.fac_id, '-', pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'))) AS submitted_months
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
        ", $where['types'], $where['params']);

        $months = self::rows($con, "
            SELECT
                CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0')) AS period,
                SUM(CASE WHEN pe.indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN pe.indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(*) AS performance_entries,
                COUNT(DISTINCT pe.fac_id) AS facilities
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
            GROUP BY pe.entry_year, pe.entry_month
            ORDER BY pe.entry_year DESC, pe.entry_month DESC
            LIMIT 12
        ", $where['types'], $where['params']);

        $total = self::one($con, "
            SELECT COUNT(*) AS row_count
            FROM (
                SELECT pe.fac_id
                FROM performance_entries pe
                LEFT JOIN facilities f ON f.fac_id = pe.fac_id
                {$where['sql']}
                GROUP BY pe.fac_id
            ) facility_performance
        ", $where['types'], $where['params']);

        $rows = self::rows($con, "
            SELECT
                pe.fac_id,
                COALESCE(f.fac_name, '') AS fac_name,
                COALESCE(f.Dist_Name, '') AS district,
                COALESCE(f.Block_Name, '') AS block,
                f.Health_facilty_type AS facility_type_id,
                SUM(CASE WHEN pe.indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN pe.indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(*) AS performance_entries,
                COUNT(DISTINCT CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'))) AS months_submitted,
                MAX(CONCAT(pe.entry_year, '-', LPAD(pe.entry_month, 2, '0'))) AS latest_month
            FROM performance_entries pe
            LEFT JOIN facilities f ON f.fac_id = pe.fac_id
            {$where['sql']}
            GROUP BY pe.fac_id, f.fac_name, f.Dist_Name, f.Block_Name, f.Health_facilty_type
            ORDER BY f.Dist_Name, f.Block_Name, f.fac_name
            LIMIT ? OFFSET ?
        ", $where['types'] . 'ii', array_merge($where['params'], [$perPage, $offset]));

        $facilityIds = array_values(array_filter(array_map(fn($row) => (int)($row['fac_id'] ?? 0), $rows)));
        $details = self::performanceDetailsForFacilities($con, $facilityIds, $filters);

        foreach ($rows as &$row) {
            $facilityTypeId = (int)($row['facility_type_id'] ?? 0);
            $rule = PerformanceService::facilityTypeRule($facilityTypeId);
            $effectiveType = PerformanceService::effectivePerformanceType($facilityTypeId);
            $latestSubmitted = self::performanceLatestSubmittedCount($con, (int)($row['fac_id'] ?? 0), $effectiveType, (string)($row['latest_month'] ?? ''));
            $expected = PerformanceService::configuredIndicatorCount($effectiveType, $facilityTypeId);
            $row['facility_type'] = self::facilityTypeMap()[$facilityTypeId] ?? (string)$facilityTypeId;
            $row['rule'] = $rule;
            $row['effective_indicator_type'] = $effectiveType;
            $row['effective_indicator_label'] = $effectiveType === 'OUTCOME' && !empty($rule['outcome_treated_as_kpi']) ? 'Outcome as KPI' : $effectiveType;
            $row['submitted_months'] = (int)($row['months_submitted'] ?? 0);
            $row['latest_submitted_indicators'] = $latestSubmitted;
            $row['expected_indicators'] = $expected;
            $row['completion_status'] = ($expected > 0 && $latestSubmitted >= $expected) ? 'COMPLETED' : 'IN_PROGRESS';
            $row['details'] = $details[(int)($row['fac_id'] ?? 0)] ?? [];
        }
        unset($row);

        $completed = 0;
        $inProgress = 0;
        foreach ($rows as $row) {
            if (($row['completion_status'] ?? '') === 'COMPLETED') {
                $completed++;
            } else {
                $inProgress++;
            }
        }

        return [
            'summary' => [
                'facilities' => (int)($summary['facilities'] ?? 0),
                'performance_entries' => (int)($summary['performance_entries'] ?? 0),
                'submitted_months' => (int)($summary['submitted_months'] ?? 0),
                'completed' => $completed,
                'in_progress' => $inProgress,
                'kpi_entries' => (int)($summary['kpi_entries'] ?? 0),
                'outcome_entries' => (int)($summary['outcome_entries'] ?? 0)
            ],
            'months' => $months,
            'kpi_submitted' => (int)($summary['kpi_entries'] ?? 0),
            'outcome_submitted' => (int)($summary['outcome_entries'] ?? 0),
            'pagination' => self::paginationMeta($pagination, (int)($total['row_count'] ?? 0)),
            'rows' => $rows
        ];
    }

    /**
     * Handles performance details for facilities processing for this API workflow.
     */
    private static function performanceDetailsForFacilities(mysqli $con, array $facilityIds, array $filters): array
    {
        if (!$facilityIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($facilityIds), '?'));
        $types = str_repeat('i', count($facilityIds));
        $params = $facilityIds;
        $extraWhere = '';

        if (($filters['month'] ?? '') !== '') {
            $extraWhere .= ' AND pe.entry_month = ?';
            $types .= 'i';
            $params[] = (int)$filters['month'];
        }
        if (($filters['year'] ?? '') !== '') {
            $extraWhere .= ' AND pe.entry_year = ?';
            $types .= 'i';
            $params[] = (int)$filters['year'];
        }

        $rows = self::rows($con, "
            SELECT
                pe.fac_id,
                pe.indicator_type,
                pe.dept_id,
                pe.entry_month,
                pe.entry_year,
                COUNT(*) AS entries
            FROM performance_entries pe
            WHERE pe.fac_id IN ({$placeholders})
            {$extraWhere}
            GROUP BY pe.fac_id, pe.indicator_type, pe.dept_id, pe.entry_year, pe.entry_month
            ORDER BY pe.fac_id, pe.entry_year DESC, pe.entry_month DESC, pe.indicator_type, pe.dept_id
            LIMIT 2000
        ", $types, $params);

        $map = [];
        foreach ($rows as $row) {
            $facId = (int)($row['fac_id'] ?? 0);
            $deptId = (int)($row['dept_id'] ?? 0);
            $type = strtoupper((string)($row['indicator_type'] ?? ''));
            $map[$facId][] = [
                'indicator_type' => $type,
                'department_id' => $deptId,
                'department_name' => $deptId > 0 ? PerformanceService::departmentName($deptId) : '-',
                'period' => sprintf('%04d-%02d', (int)($row['entry_year'] ?? 0), (int)($row['entry_month'] ?? 0)),
                'month' => (int)($row['entry_month'] ?? 0),
                'year' => (int)($row['entry_year'] ?? 0),
                'entries' => (int)($row['entries'] ?? 0)
            ];
        }

        return $map;
    }

    /**
     * Handles performance latest submitted count processing for this API workflow.
     */
    private static function performanceLatestSubmittedCount(mysqli $con, int $facilityId, string $indicatorType, string $period): int
    {
        if ($facilityId <= 0 || $period === '') {
            return 0;
        }

        [$year, $month] = array_pad(explode('-', $period), 2, 0);
        $year = (int)$year;
        $month = (int)$month;

        if ($year <= 0 || $month <= 0) {
            return 0;
        }

        return (int)self::scalar($con, "
            SELECT COUNT(DISTINCT indicator_id)
            FROM performance_entries
            WHERE fac_id = ?
              AND indicator_type = ?
              AND entry_year = ?
              AND entry_month = ?
        ", 'isii', [$facilityId, strtoupper($indicatorType), $year, $month]);
    }

    /**
     * Handles facility detail processing for this API workflow.
     */
    public static function facilityDetail(mysqli $con, int $facilityId): array
    {
        if ($facilityId <= 0) {
            return [];
        }

        $facility = self::facilitiesById()[$facilityId] ?? [];
        $assessments = self::tableExists($con, 'assessment_master')
            ? self::rows($con, "SELECT * FROM assessment_master WHERE fac_id_fk = ? ORDER BY assessment_id DESC LIMIT 50", 'i', [$facilityId])
            : [];
        $performance = self::tableExists($con, 'performance_entries')
            ? self::rows($con, "SELECT indicator_type, entry_month, entry_year, COUNT(*) AS entries FROM performance_entries WHERE fac_id = ? GROUP BY indicator_type, entry_year, entry_month ORDER BY entry_year DESC, entry_month DESC LIMIT 24", 'i', [$facilityId])
            : [];
        $assessmentSummary = self::facilityAssessmentSummary($con, $facilityId);
        $performanceSummary = self::facilityPerformanceSummary($con, $facilityId);
        $cqiSummary = self::facilityCqiSummary($con, $facilityId);

        return [
            'facility' => $facility,
            'summary' => [
                'assessments' => $assessmentSummary,
                'performance' => $performanceSummary,
                'cqi' => $cqiSummary
            ],
            'assessments' => $assessments,
            'performance' => $performance
        ];
    }

    /**
     * Handles facility hierarchy processing for this API workflow.
     */
    public static function facilityHierarchy(array $filters = []): array
    {
        $states = [];
        $stateIndex = [];
        $facilities = self::filteredFacilities($filters);

        foreach ($facilities as $facility) {
            $stateName = trim((string)($facility['state_name'] ?? 'State'));
            $divisionName = trim((string)($facility['division'] ?? 'Division'));
            $districtName = trim((string)($facility['Dist_Name'] ?? $facility['district'] ?? 'District'));
            $blockName = trim((string)($facility['Block_Name'] ?? $facility['block'] ?? 'Block'));
            $facilityId = (int)($facility['fac_id'] ?? 0);

            if ($stateName === '') {
                $stateName = 'State';
            }
            if ($divisionName === '') {
                $divisionName = 'Division';
            }
            if ($districtName === '') {
                $districtName = 'District';
            }
            if ($blockName === '') {
                $blockName = 'Block';
            }

            if (!isset($stateIndex[$stateName])) {
                $stateIndex[$stateName] = count($states);
                $states[] = [
                    'name' => $stateName,
                    'count' => 0,
                    'divisions' => [],
                    '_division_index' => []
                ];
            }

            $stateKey = $stateIndex[$stateName];
            $states[$stateKey]['count']++;

            if (!isset($states[$stateKey]['_division_index'][$divisionName])) {
                $states[$stateKey]['_division_index'][$divisionName] = count($states[$stateKey]['divisions']);
                $states[$stateKey]['divisions'][] = [
                    'name' => $divisionName,
                    'count' => 0,
                    'districts' => [],
                    '_district_index' => []
                ];
            }

            $divisionKey = $states[$stateKey]['_division_index'][$divisionName];
            $states[$stateKey]['divisions'][$divisionKey]['count']++;

            if (!isset($states[$stateKey]['divisions'][$divisionKey]['_district_index'][$districtName])) {
                $states[$stateKey]['divisions'][$divisionKey]['_district_index'][$districtName] = count($states[$stateKey]['divisions'][$divisionKey]['districts']);
                $states[$stateKey]['divisions'][$divisionKey]['districts'][] = [
                    'name' => $districtName,
                    'count' => 0,
                    'blocks' => [],
                    '_block_index' => []
                ];
            }

            $districtKey = $states[$stateKey]['divisions'][$divisionKey]['_district_index'][$districtName];
            $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['count']++;

            if (!isset($states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['_block_index'][$blockName])) {
                $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['_block_index'][$blockName] = count($states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['blocks']);
                $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['blocks'][] = [
                    'name' => $blockName,
                    'count' => 0,
                    'facilities' => []
                ];
            }

            $blockKey = $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['_block_index'][$blockName];
            $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['blocks'][$blockKey]['count']++;
            $states[$stateKey]['divisions'][$divisionKey]['districts'][$districtKey]['blocks'][$blockKey]['facilities'][] = [
                'fac_id' => $facilityId,
                'fac_name' => (string)($facility['fac_name'] ?? ''),
                'nin' => (string)($facility['NIN_no'] ?? $facility['nin_no'] ?? ''),
                'facility_type' => self::facilityTypeLabel($facility)
            ];
        }

        foreach ($states as &$state) {
            unset($state['_division_index']);
            foreach ($state['divisions'] as &$division) {
                unset($division['_district_index']);
                foreach ($division['districts'] as &$district) {
                    unset($district['_block_index']);
                }
                unset($district);
            }
            unset($division);
        }
        unset($state);

        return [
            'total_facilities' => count($facilities),
            'states' => $states
        ];
    }

    /**
     * Handles facility assessment summary processing for this API workflow.
     */
    private static function facilityAssessmentSummary(mysqli $con, int $facilityId): array
    {
        $empty = ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0, 'in_progress' => 0, 'other' => 0];
        if (!self::tableExists($con, 'assessment_master')) {
            return $empty;
        }

        $rows = self::rows($con, "
            SELECT UPPER(COALESCE(status, 'UNKNOWN')) AS status, COUNT(*) AS count
            FROM assessment_master
            WHERE fac_id_fk = ?
            GROUP BY UPPER(COALESCE(status, 'UNKNOWN'))
        ", 'i', [$facilityId]);

        foreach ($rows as $row) {
            $status = strtolower(str_replace(' ', '_', (string)($row['status'] ?? 'other')));
            $count = (int)($row['count'] ?? 0);
            $empty['total'] += $count;
            if (isset($empty[$status])) {
                $empty[$status] += $count;
            } else {
                $empty['other'] += $count;
            }
        }

        return $empty;
    }

    /**
     * Handles facility performance summary processing for this API workflow.
     */
    private static function facilityPerformanceSummary(mysqli $con, int $facilityId): array
    {
        $empty = ['kpi_entries' => 0, 'outcome_entries' => 0, 'months' => 0, 'latest_period' => null];
        if (!self::tableExists($con, 'performance_entries')) {
            return $empty;
        }

        $row = self::one($con, "
            SELECT
                SUM(CASE WHEN indicator_type = 'KPI' THEN 1 ELSE 0 END) AS kpi_entries,
                SUM(CASE WHEN indicator_type = 'OUTCOME' THEN 1 ELSE 0 END) AS outcome_entries,
                COUNT(DISTINCT CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS months,
                MAX(CONCAT(entry_year, '-', LPAD(entry_month, 2, '0'))) AS latest_period
            FROM performance_entries
            WHERE fac_id = ?
        ", 'i', [$facilityId]);

        return [
            'kpi_entries' => (int)($row['kpi_entries'] ?? 0),
            'outcome_entries' => (int)($row['outcome_entries'] ?? 0),
            'months' => (int)($row['months'] ?? 0),
            'latest_period' => $row['latest_period'] ?? null
        ];
    }

    /**
     * Handles facility cqi summary processing for this API workflow.
     */
    private static function facilityCqiSummary(mysqli $con, int $facilityId): array
    {
        $empty = ['total_action_plans' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0, 'open_gaps' => 0];
        if (!self::tableExists($con, 'assessment_action_plan') || !self::tableExists($con, 'assessment_master')) {
            return $empty;
        }

        $row = self::one($con, "
            SELECT
                COUNT(*) AS total_action_plans,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN ap.target_date IS NOT NULL AND ap.target_date < CURDATE() AND UPPER(COALESCE(ap.status, '')) NOT IN ('COMPLETED','CLOSED') THEN 1 ELSE 0 END) AS overdue
            FROM assessment_action_plan ap
            INNER JOIN assessment_master a ON a.assessment_id = ap.assessment_id
            WHERE a.fac_id_fk = ?
        ", 'i', [$facilityId]);

        return [
            'total_action_plans' => (int)($row['total_action_plans'] ?? 0),
            'completed' => (int)($row['completed'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'overdue' => (int)($row['overdue'] ?? 0),
            'open_gaps' => (int)($row['pending'] ?? 0)
        ];
    }

    /**
     * Handles resolve facility id processing for this API workflow.
     */
    public static function resolveFacilityId(mysqli $con, string $search): int
    {
        $search = trim($search);
        if ($search === '') {
            return 0;
        }

        if (ctype_digit($search)) {
            $id = (int)$search;
            if (isset(self::facilitiesById()[$id])) {
                return $id;
            }
        }

        $matches = self::filteredFacilities(['search' => $search]);
        if ($matches) {
            return (int)($matches[0]['fac_id'] ?? 0);
        }

        if (!self::tableExists($con, 'facilities')) {
            return 0;
        }

        $like = '%' . $search . '%';
        return (int)self::scalar($con, "
            SELECT fac_id
            FROM facilities
            WHERE fac_name LIKE ?
               OR CAST(NIN_no AS CHAR) LIKE ?
               OR CAST(fac_id AS CHAR) LIKE ?
            ORDER BY fac_name
            LIMIT 1
        ", 'sss', [$like, $like, $like]);
    }

    /**
     * Handles users processing for this API workflow.
     */
    public static function users(mysqli $con, array $filters = []): array
    {
        if (!self::tableExists($con, 's_user')) {
            return ['summary' => ['active' => 0, 'inactive' => 0], 'pagination' => ['page' => 1, 'per_page' => 50, 'total_rows' => 0, 'total_pages' => 1], 'rows' => []];
        }

        $pagination = self::pagination($filters);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id_fk = ?';
            $types .= 'i';
            $params[] = (int)$filters['role_id'];
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(u.u_name LIKE ? OR CAST(u.u_id AS CHAR) LIKE ? OR r.role_name LIKE ? OR f.fac_name LIKE ? OR f.Dist_Name LIKE ? OR CAST(f.NIN_no AS CHAR) LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'ssssss';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $summary = self::one($con, "
            SELECT
                SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN u.is_active <> 1 THEN 1 ELSE 0 END) AS inactive
            FROM s_user u
            LEFT JOIN u_role r ON r.role_id = u.role_id_fk
            LEFT JOIN facilities f ON f.fac_id = u.fac_id_fk
            {$sqlWhere}
        ", $types, $params);
        $total = self::one($con, "
            SELECT COUNT(*) AS row_count
            FROM s_user u
            LEFT JOIN u_role r ON r.role_id = u.role_id_fk
            LEFT JOIN facilities f ON f.fac_id = u.fac_id_fk
            {$sqlWhere}
        ", $types, $params);
        $rows = self::rows($con, "
            SELECT u.u_id, u.u_name, u.role_id_fk, r.role_name, u.is_active, u.fac_id_fk, f.fac_name, f.Dist_Name AS district
            FROM s_user u
            LEFT JOIN u_role r ON r.role_id = u.role_id_fk
            LEFT JOIN facilities f ON f.fac_id = u.fac_id_fk
            {$sqlWhere}
            ORDER BY u.u_id DESC
            LIMIT ? OFFSET ?
        ", $types . 'ii', array_merge($params, [$perPage, $offset]));

        return [
            'summary' => $summary,
            'pagination' => self::paginationMeta($pagination, (int)($total['row_count'] ?? 0)),
            'rows' => $rows
        ];
    }

    /**
     * Handles update user status processing for this API workflow.
     */
    public static function updateUserStatus(mysqli $con, int $userId, int $isActive): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID is required.');
        }

        if (!self::tableExists($con, 's_user')) {
            throw new RuntimeException('User table is not available.');
        }

        $row = self::one($con, "SELECT u_id, u_name, is_active FROM s_user WHERE u_id = ? LIMIT 1", 'i', [$userId]);
        if (!$row) {
            throw new InvalidArgumentException('User not found.');
        }

        $status = $isActive === 1 ? 1 : 0;
        $stmt = $con->prepare("UPDATE s_user SET is_active = ? WHERE u_id = ?");
        if (!$stmt) {
            throw new RuntimeException('User status update prepare failed.');
        }

        $stmt->bind_param('ii', $status, $userId);
        $stmt->execute();

        Event::dispatch('state.user.status_updated', [
            'target_user_id' => $userId,
            'is_active' => $status,
            'action_by' => SessionManager::userId()
        ]);

        return [
            'u_id' => $userId,
            'u_name' => (string)($row['u_name'] ?? ''),
            'is_active' => $status
        ];
    }

    /**
     * Handles attention processing for this API workflow.
     */
    public static function attention(mysqli $con, array $filters = []): array
    {
        $items = [];
        $cqi = self::cqiSummary($con, $filters);
        if (($cqi['overdue'] ?? 0) > 0) {
            $items[] = ['type' => 'CQI', 'label' => 'Overdue action plans', 'count' => (int)$cqi['overdue']];
        }
        $assessment = self::assessmentProgress($con, $filters, true);
        if (($assessment['active'] ?? 0) > 0) {
            $items[] = ['type' => 'Assessment', 'label' => 'Active assessments', 'count' => (int)$assessment['active']];
        }
        return $items;
    }

    /**
     * Handles current month status processing for this API workflow.
     */
    public static function currentMonthStatus(mysqli $con, array $filters = []): array
    {
        $month = (int)($filters['month'] ?? date('n'));
        $year = (int)($filters['year'] ?? date('Y'));
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        if ($year < 2000) {
            $year = (int)date('Y');
        }

        $assessment = [
            'started' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'period' => sprintf('%04d-%02d', $year, $month)
        ];

        if (self::tableExists($con, 'assessment_master')) {
            $where = self::assessmentWhere($con, $filters);
            $startedColumn = self::columnExists($con, 'assessment_master', 'start_date') ? 'start_date' : null;
            $completedColumn = self::columnExists($con, 'assessment_master', 'completed_on') ? 'completed_on' : null;

            if ($startedColumn !== null) {
                $assessment['started'] = (int)self::scalar($con, "
                    SELECT COUNT(*)
                    FROM assessment_master a
                    {$where['sql']}
                      AND MONTH(a.{$startedColumn}) = ?
                      AND YEAR(a.{$startedColumn}) = ?
                ", $where['types'] . 'ii', array_merge($where['params'], [$month, $year]));

                $assessment['in_progress'] = (int)self::scalar($con, "
                    SELECT COUNT(*)
                    FROM assessment_master a
                    {$where['sql']}
                      AND UPPER(COALESCE(a.status, '')) IN ('ACTIVE','IN_PROGRESS')
                      AND MONTH(a.{$startedColumn}) = ?
                      AND YEAR(a.{$startedColumn}) = ?
                ", $where['types'] . 'ii', array_merge($where['params'], [$month, $year]));
            }

            if ($completedColumn !== null) {
                $assessment['completed'] = (int)self::scalar($con, "
                    SELECT COUNT(*)
                    FROM assessment_master a
                    {$where['sql']}
                      AND UPPER(COALESCE(a.status, '')) = 'COMPLETED'
                      AND MONTH(a.{$completedColumn}) = ?
                      AND YEAR(a.{$completedColumn}) = ?
                ", $where['types'] . 'ii', array_merge($where['params'], [$month, $year]));
            } elseif (self::columnExists($con, 'assessment_master', 'end_date')) {
                $assessment['completed'] = (int)self::scalar($con, "
                    SELECT COUNT(*)
                    FROM assessment_master a
                    {$where['sql']}
                      AND UPPER(COALESCE(a.status, '')) = 'COMPLETED'
                      AND MONTH(a.end_date) = ?
                      AND YEAR(a.end_date) = ?
                ", $where['types'] . 'ii', array_merge($where['params'], [$month, $year]));
            }
        }

        $performance = [
            'kpi_filled' => 0,
            'outcome_filled' => 0,
            'performance_entries' => 0,
            'period' => sprintf('%04d-%02d', $year, $month)
        ];

        if (self::tableExists($con, 'performance_entries') && self::tableExists($con, 'facilities')) {
            $where = self::facilityWhere($con, $filters);
            $where['sql'] .= ' AND pe.entry_month = ? AND pe.entry_year = ?';
            $where['types'] .= 'ii';
            $where['params'][] = $month;
            $where['params'][] = $year;

            $row = self::one($con, "
                SELECT
                    COUNT(DISTINCT CASE WHEN pe.indicator_type = 'KPI' THEN pe.fac_id END) AS kpi_filled,
                    COUNT(DISTINCT CASE WHEN pe.indicator_type = 'OUTCOME' THEN pe.fac_id END) AS outcome_filled,
                    COUNT(*) AS performance_entries
                FROM performance_entries pe
                LEFT JOIN facilities f ON f.fac_id = pe.fac_id
                {$where['sql']}
            ", $where['types'], $where['params']);

            $performance['kpi_filled'] = (int)($row['kpi_filled'] ?? 0);
            $performance['outcome_filled'] = (int)($row['outcome_filled'] ?? 0);
            $performance['performance_entries'] = (int)($row['performance_entries'] ?? 0);
        }

        return [
            'month' => $month,
            'year' => $year,
            'period' => sprintf('%04d-%02d', $year, $month),
            'assessment' => $assessment,
            'performance' => $performance
        ];
    }

    /**
     * Handles latest assessment attention processing for this API workflow.
     */
    public static function latestAssessmentAttention(mysqli $con, array $filters = []): array
    {
        $empty = [
            'summary' => [
                'latest_assessment_facilities' => 0,
                'low_score_facilities' => 0,
                'threshold_percent' => 50,
                'lowest_score_percent' => 0,
                'average_score_percent' => 0
            ],
            'rows' => []
        ];

        if (!self::tableExists($con, 'assessment_master') || !self::tableExists($con, 'facilities')) {
            return $empty;
        }

        $responseTable = self::tableExists($con, 'assessment_response')
            ? 'assessment_response'
            : (self::tableExists($con, 'assessment_cycle_response') ? 'assessment_cycle_response' : '');
        if ($responseTable === '') {
            return $empty;
        }

        $assessmentColumn = self::columnExists($con, $responseTable, 'assessment_id') ? 'assessment_id' : 'cycle_id';
        $where = self::facilityWhere($con, $filters);
        $actionJoin = '';
        $scoreExpression = 'r.score';
        if (self::tableExists($con, 'assessment_action_plan')) {
            $actionJoin = "
                LEFT JOIN assessment_action_plan ap
                  ON ap.assessment_id = r.{$assessmentColumn}
                 AND ap.dept_id = r.dept_id
                 AND ap.checkpoint_id = r.checkpoint_id
            ";
            $scoreExpression = 'COALESCE(ap.revised_score, r.score)';
        }

        $scoreSql = "
            CASE
                WHEN COUNT(DISTINCT r.checkpoint_id) > 0
                THEN ROUND((COALESCE(SUM({$scoreExpression}), 0) / (COUNT(DISTINCT r.checkpoint_id) * 2)) * 100, 2)
                ELSE 0
            END
        ";

        $baseSql = "
            FROM assessment_master a
            INNER JOIN (
                SELECT fac_id_fk, MAX(assessment_id) AS assessment_id
                FROM assessment_master
                WHERE fac_id_fk IS NOT NULL
                GROUP BY fac_id_fk
            ) latest ON latest.assessment_id = a.assessment_id
            LEFT JOIN facilities f ON f.fac_id = a.fac_id_fk
            LEFT JOIN {$responseTable} r ON r.{$assessmentColumn} = a.assessment_id
            {$actionJoin}
            {$where['sql']}
            GROUP BY
                a.assessment_id,
                a.assessment_name,
                a.status,
                a.fac_id_fk,
                f.fac_name,
                f.Dist_Name,
                f.Block_Name,
                f.NIN_no
        ";

        $summary = self::one($con, "
            SELECT
                COUNT(*) AS latest_assessment_facilities,
                SUM(CASE WHEN score_percent < 50 THEN 1 ELSE 0 END) AS low_score_facilities,
                MIN(score_percent) AS lowest_score_percent,
                ROUND(AVG(score_percent), 2) AS average_score_percent
            FROM (
                SELECT {$scoreSql} AS score_percent
                {$baseSql}
            ) latest_scores
        ", $where['types'], $where['params']);

        $rows = self::rows($con, "
            SELECT *
            FROM (
                SELECT
                    a.fac_id_fk,
                    COALESCE(f.fac_name, CONCAT('Facility ', a.fac_id_fk)) AS fac_name,
                    COALESCE(f.Dist_Name, '') AS district,
                    COALESCE(f.Block_Name, '') AS block,
                    COALESCE(CAST(f.NIN_no AS CHAR), '') AS nin_no,
                    a.assessment_id,
                    a.assessment_name,
                    a.status,
                    COUNT(DISTINCT r.checkpoint_id) AS checkpoint_done,
                    {$scoreSql} AS score_percent
                {$baseSql}
            ) latest_scores
            WHERE score_percent < 50
            ORDER BY score_percent ASC, checkpoint_done ASC, assessment_id DESC
            LIMIT 10
        ", $where['types'], $where['params']);

        return [
            'summary' => [
                'latest_assessment_facilities' => (int)($summary['latest_assessment_facilities'] ?? 0),
                'low_score_facilities' => (int)($summary['low_score_facilities'] ?? 0),
                'threshold_percent' => 50,
                'lowest_score_percent' => round((float)($summary['lowest_score_percent'] ?? 0), 2),
                'average_score_percent' => round((float)($summary['average_score_percent'] ?? 0), 2)
            ],
            'rows' => $rows
        ];
    }

    /**
     * Handles normalize filters processing for this API workflow.
     */
    private static function normalizeFilters(array $filters): array
    {
        return [
            'state_code' => (string)($filters['state_code'] ?? ''),
            'division' => (string)($filters['division'] ?? ''),
            'district' => (string)($filters['district'] ?? ''),
            'block' => (string)($filters['block'] ?? ''),
            'facility_type' => (string)($filters['facility_type'] ?? ''),
            'search' => trim((string)($filters['search'] ?? '')),
            'month' => (string)($filters['month'] ?? ''),
            'year' => (string)($filters['year'] ?? '')
        ];
    }

    /**
     * Handles pagination processing for this API workflow.
     */
    private static function pagination(array $filters, int $defaultPerPage = 50, int $maxPerPage = 100): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min($maxPerPage, max(10, (int)($filters['per_page'] ?? $defaultPerPage)));

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage
        ];
    }

    /**
     * Handles pagination meta processing for this API workflow.
     */
    private static function paginationMeta(array $pagination, int $totalRows): array
    {
        return [
            'page' => (int)$pagination['page'],
            'per_page' => (int)$pagination['per_page'],
            'total_rows' => $totalRows,
            'total_pages' => max(1, (int)ceil($totalRows / max(1, (int)$pagination['per_page'])))
        ];
    }

    /**
     * Handles facilities from json processing for this API workflow.
     */
    private static function facilitiesFromJson(): array
    {
        static $facilities = null;

        if ($facilities !== null) {
            return $facilities;
        }

        $path = __DIR__ . '/../config/masters/facilities.json';

        if (!is_file($path)) {
            $facilities = [];
            return $facilities;
        }

        $data = json_decode((string)file_get_contents($path), true);

        if (!is_array($data)) {
            $facilities = [];
            return $facilities;
        }

        $rows = [];

        foreach ($data as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            if (!is_array($facility)) {
                                continue;
                            }

                            $facility['state_id'] = $facility['state_id'] ?? $state['state_id'] ?? null;
                            $facility['state_code'] = $facility['state_code'] ?? $state['state_code'] ?? $state['state_id'] ?? null;
                            $facility['state_name'] = $facility['state_name'] ?? $state['state_name'] ?? '';
                            $facility['division_id'] = $facility['division_id'] ?? $division['division_id'] ?? null;
                            $facility['division'] = $facility['division'] ?? $division['division_name'] ?? $division['division'] ?? '';
                            $facility['dist_id'] = $facility['dist_id'] ?? $district['dist_id'] ?? null;
                            $facility['Dist_Name'] = $facility['Dist_Name'] ?? $district['dist_name'] ?? $district['Dist_Name'] ?? '';
                            $facility['block_id'] = $facility['block_id'] ?? $block['block_id'] ?? null;
                            $facility['Block_Name'] = $facility['Block_Name'] ?? $block['block_name'] ?? $block['Block_Name'] ?? '';
                            $facility['NIN_no'] = $facility['NIN_no'] ?? $facility['nin_no'] ?? null;
                            $facility['Health_facilty_type'] = $facility['Health_facilty_type'] ?? $facility['fac_type_id'] ?? null;

                            $rows[] = $facility;
                        }
                    }
                }
            }
        }

        $facilities = $rows;
        return $facilities;
    }

    /**
     * Handles filtered facilities processing for this API workflow.
     */
    private static function filteredFacilities(array $filters): array
    {
        $facilities = self::facilitiesFromJson();

        return array_values(array_filter($facilities, function (array $facility) use ($filters): bool {
            if (($filters['state_code'] ?? '') !== '') {
                $stateNeedle = (string)$filters['state_code'];
                if (
                    (string)($facility['state_code'] ?? '') !== $stateNeedle &&
                    (string)($facility['state_id'] ?? '') !== $stateNeedle &&
                    strcasecmp((string)($facility['state_name'] ?? ''), $stateNeedle) !== 0
                ) {
                    return false;
                }
            }

            foreach ([
                'division' => 'division',
                'district' => 'Dist_Name',
                'block' => 'Block_Name'
            ] as $filterKey => $facilityKey) {
                if (($filters[$filterKey] ?? '') !== '' && strcasecmp((string)($facility[$facilityKey] ?? ''), (string)$filters[$filterKey]) !== 0) {
                    return false;
                }
            }

            if (($filters['facility_type'] ?? '') !== '') {
                $needle = (string)$filters['facility_type'];
                if (
                    (string)($facility['Health_facilty_type'] ?? '') !== $needle &&
                    (string)($facility['fac_type_id'] ?? '') !== $needle &&
                    strcasecmp((string)($facility['facilities_type'] ?? ''), $needle) !== 0
                ) {
                    return false;
                }
            }

            $search = strtolower(trim((string)($filters['search'] ?? '')));
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $facility['fac_name'] ?? '',
                    $facility['NIN_no'] ?? '',
                    $facility['nin_no'] ?? '',
                    $facility['fac_id'] ?? '',
                    $facility['Dist_Name'] ?? '',
                    $facility['Block_Name'] ?? ''
                ]));

                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Handles facilities by id processing for this API workflow.
     */
    private static function facilitiesById(): array
    {
        $indexed = [];

        foreach (self::facilitiesFromJson() as $facility) {
            $id = (int)($facility['fac_id'] ?? 0);

            if ($id > 0) {
                $indexed[$id] = $facility;
            }
        }

        return $indexed;
    }

    /**
     * Handles facility coordinates from db processing for this API workflow.
     */
    private static function facilityCoordinatesFromDb(mysqli $con, array $filters = []): array
    {
        $indexed = ['id' => [], 'nin' => []];

        if (!self::tableExists($con, 'facilities')) {
            return $indexed;
        }

        $where = self::facilityWhere($con, $filters);
        $rows = self::rows($con, "
            SELECT
                fac_id,
                NIN_no,
                fac_name,
                Dist_Name AS district,
                Block_Name AS block,
                Health_facilty_type AS facility_type,
                lat,
                longit
            FROM facilities f
            {$where['sql']}
              AND lat IS NOT NULL
              AND longit IS NOT NULL
        ", $where['types'], $where['params']);

        foreach ($rows as $row) {
            $facId = (int)($row['fac_id'] ?? 0);
            $nin = self::normalizeNin($row['NIN_no'] ?? '');

            if ($facId > 0) {
                $indexed['id'][$facId] = $row;
            }
            if ($nin !== '') {
                $indexed['nin'][$nin] = $row;
            }
        }

        return $indexed;
    }

    /**
     * Handles map boundary processing for this API workflow.
     */
    public static function mapBoundary(mixed $state): array
    {
        $config = self::mapMasterConfig();
        $selected = self::selectedMapState(['state_code' => $state], $config);
        $source = (string)($selected['source'] ?? '');

        if ($source === '') {
            throw new InvalidArgumentException('Map boundary source is not configured.');
        }

        $path = self::mapSourcePath($source);
        if (!$path || !is_file($path)) {
            throw new InvalidArgumentException('Configured map boundary file was not found.');
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Configured map boundary file is not valid JSON.');
        }

        if (($data['type'] ?? '') === 'FeatureCollection') {
            return $data;
        }

        if (($data['type'] ?? '') === 'Topology') {
            return self::topologyToFeatureCollection($data);
        }

        throw new InvalidArgumentException('Map boundary JSON must be GeoJSON FeatureCollection or Topology.');
    }

    /**
     * Handles map config processing for this API workflow.
     */
    private static function mapConfig(array $filters = []): array
    {
        $config = self::mapMasterConfig();
        $settings = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $selected = self::selectedMapState($filters, $config);
        $stateKey = (string)($selected['_key'] ?? ($config['default_state'] ?? ''));

        return [
            'state_key' => $stateKey,
            'state_label' => $selected['label'] ?? $stateKey,
            'center' => $selected['center'] ?? $settings['center'] ?? [26.8467, 80.9462],
            'zoom' => (int)($selected['zoom'] ?? $settings['zoom'] ?? 7),
            'min_zoom' => (int)($settings['min_zoom'] ?? 5),
            'max_zoom' => (int)($settings['max_zoom'] ?? 18),
            'tile_url' => $settings['tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => $settings['attribution'] ?? '&copy; OpenStreetMap contributors',
            'boundary_url' => '/api/state/v1/boundary.php?state=' . rawurlencode($stateKey),
            'boundary_source_url' => $selected['source'] ?? null,
            'boundary_type' => $selected['source_type'] ?? null
        ];
    }

    /**
     * Handles map master config processing for this API workflow.
     */
    private static function mapMasterConfig(): array
    {
        $path = __DIR__ . '/../config/masters/map_config.json';
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        return is_array($data) ? $data : [];
    }

    /**
     * Handles selected map state processing for this API workflow.
     */
    private static function selectedMapState(array $filters, array $config): array
    {
        $states = is_array($config['states'] ?? null) ? $config['states'] : [];
        $needles = array_filter(array_map(
            fn($value) => self::mapKey((string)$value),
            [
                $filters['state'] ?? '',
                $filters['state_code'] ?? '',
                $filters['state_id'] ?? '',
                $filters['state_name'] ?? ''
            ]
        ));

        foreach ($states as $key => $state) {
            $aliases = array_merge([$key, $state['label'] ?? ''], $state['aliases'] ?? []);
            $normalizedAliases = array_map(fn($alias) => self::mapKey((string)$alias), $aliases);

            foreach ($needles as $needle) {
                if (in_array($needle, $normalizedAliases, true)) {
                    $state['_key'] = (string)$key;
                    return $state;
                }
            }
        }

        $default = (string)($config['default_state'] ?? array_key_first($states));
        $state = $states[$default] ?? reset($states) ?: [];
        $state['_key'] = $default !== '' ? $default : (string)array_key_first($states);
        return $state;
    }

    /**
     * Handles map key processing for this API workflow.
     */
    private static function mapKey(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($value))) ?? '';
    }

    /**
     * Handles map source path processing for this API workflow.
     */
    private static function mapSourcePath(string $source): ?string
    {
        $root = dirname(__DIR__, 2);
        $relative = ltrim(str_replace(['\\', '..'], ['/', ''], $source), '/');
        $path = $root . '/' . $relative;
        $real = realpath($path);
        $allowed = [
            realpath($root . '/api/config/masters'),
            realpath($root . '/assets/map')
        ];

        foreach ($allowed as $base) {
            if ($real && $base && str_starts_with($real, $base)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Handles topology to feature collection processing for this API workflow.
     */
    private static function topologyToFeatureCollection(array $topology): array
    {
        $features = [];
        $objects = is_array($topology['objects'] ?? null) ? $topology['objects'] : [];

        foreach ($objects as $object) {
            foreach (self::topologyGeometries($object) as $geometry) {
                $converted = self::topologyGeometry($geometry, $topology);
                if ($converted) {
                    $features[] = [
                        'type' => 'Feature',
                        'properties' => is_array($geometry['properties'] ?? null) ? $geometry['properties'] : [],
                        'geometry' => $converted
                    ];
                }
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * Handles topology geometries processing for this API workflow.
     */
    private static function topologyGeometries(array $object): array
    {
        if (($object['type'] ?? '') === 'GeometryCollection') {
            return is_array($object['geometries'] ?? null) ? $object['geometries'] : [];
        }

        return [$object];
    }

    /**
     * Handles topology geometry processing for this API workflow.
     */
    private static function topologyGeometry(array $geometry, array $topology): ?array
    {
        $type = $geometry['type'] ?? '';
        $arcs = $geometry['arcs'] ?? [];

        if ($type === 'Polygon') {
            return [
                'type' => 'Polygon',
                'coordinates' => array_map(fn($ring) => self::topologyLine($ring, $topology), $arcs)
            ];
        }

        if ($type === 'MultiPolygon') {
            return [
                'type' => 'MultiPolygon',
                'coordinates' => array_map(
                    fn($polygon) => array_map(fn($ring) => self::topologyLine($ring, $topology), $polygon),
                    $arcs
                )
            ];
        }

        return null;
    }

    /**
     * Handles topology line processing for this API workflow.
     */
    private static function topologyLine(array $arcRefs, array $topology): array
    {
        $line = [];

        foreach ($arcRefs as $position => $arcRef) {
            $arc = self::topologyArc((int)$arcRef, $topology);
            if ($position > 0 && $arc) {
                array_shift($arc);
            }
            $line = array_merge($line, $arc);
        }

        return $line;
    }

    /**
     * Handles topology arc processing for this API workflow.
     */
    private static function topologyArc(int $arcRef, array $topology): array
    {
        $index = $arcRef < 0 ? ~$arcRef : $arcRef;
        $rawArc = $topology['arcs'][$index] ?? [];
        $scale = $topology['transform']['scale'] ?? [1, 1];
        $translate = $topology['transform']['translate'] ?? [0, 0];
        $x = 0;
        $y = 0;
        $points = [];

        foreach ($rawArc as $point) {
            $x += (float)($point[0] ?? 0);
            $y += (float)($point[1] ?? 0);
            $points[] = [
                ($x * (float)$scale[0]) + (float)$translate[0],
                ($y * (float)$scale[1]) + (float)$translate[1]
            ];
        }

        return $arcRef < 0 ? array_reverse($points) : $points;
    }

    /**
     * Handles normalize nin processing for this API workflow.
     */
    private static function normalizeNin(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string)$value) ?? '';
    }

    /**
     * Handles date or empty processing for this API workflow.
     */
    private static function dateOrEmpty(mixed $value): string
    {
        $date = trim((string)$value);
        if ($date === '') {
            return '';
        }

        $time = strtotime($date);
        return $time ? date('Y-m-d', $time) : '';
    }

    /**
     * Handles decode json object processing for this API workflow.
     */
    private static function decodeJsonObject(mixed $value): array
    {
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Handles cert status from payload processing for this API workflow.
     */
    private static function certStatusFromPayload(array $payload): string
    {
        return self::normalizeCertStatus(
            $payload['Cert_status'] ??
            $payload['cert_status'] ??
            $payload['certification_status'] ??
            $payload['status'] ??
            ''
        ) ?: 'NOT CERTIFIED';
    }

    /**
     * Handles normalize cert status processing for this API workflow.
     */
    private static function normalizeCertStatus(mixed $value): string
    {
        $status = strtoupper(trim((string)$value));

        if ($status === '') {
            return '';
        }

        $map = [
            'CERTIFIED' => 'CERTIFIED',
            'CONDITIONAL' => 'CONDITIONAL',
            'CONDITIONALLY CERTIFIED' => 'CONDITIONAL',
            'EXPIRED' => 'EXPIRED',
            'EXPIRING SOON' => 'EXPIRING SOON',
            'NOT CERTIFIED' => 'NOT CERTIFIED',
            'NOT_CERTIFIED' => 'NOT CERTIFIED',
            'PENDING' => 'PENDING',
            'APPLIED' => 'APPLIED'
        ];

        return $map[$status] ?? $status;
    }

    /**
     * Handles latest certification history processing for this API workflow.
     */
    private static function latestCertificationHistory(mysqli $con, int $facId, string $nin): ?array
    {
        if (!self::tableExists($con, 'certification_history')) {
            return null;
        }

        if ($nin !== '') {
            $stmt = $con->prepare("
                SELECT *
                FROM certification_history
                WHERE fac_nin = ?
                ORDER BY history_id DESC
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('s', $nin);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
        }

        if ($facId > 0) {
            $stmt = $con->prepare("
                SELECT *
                FROM certification_history
                WHERE fac_id_fk = ?
                ORDER BY history_id DESC
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('i', $facId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
        }

        return null;
    }

    /**
     * Handles facility where processing for this API workflow.
     */
    private static function facilityWhere(mysqli $con, array $filters): array
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        foreach ([
            'state_code' => 'f.state_code',
            'division' => 'f.division',
            'district' => 'f.Dist_Name',
            'block' => 'f.Block_Name',
            'facility_type' => 'f.Health_facilty_type'
        ] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $types .= $key === 'facility_type' || $key === 'state_code' ? 'i' : 's';
                $params[] = $key === 'facility_type' || $key === 'state_code' ? (int)$filters[$key] : (string)$filters[$key];
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(f.fac_name LIKE ? OR CAST(f.NIN_no AS CHAR) LIKE ? OR CAST(f.fac_id AS CHAR) LIKE ? OR f.Dist_Name LIKE ? OR f.Block_Name LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return ['sql' => 'WHERE ' . implode(' AND ', $where), 'types' => $types, 'params' => $params];
    }

    /**
     * Handles cert where processing for this API workflow.
     */
    private static function certWhere(mysqli $con, array $filters): array
    {
        return self::facilityWhere($con, $filters);
    }

    /**
     * Handles assessment where processing for this API workflow.
     */
    private static function assessmentWhere(mysqli $con, array $filters): array
    {
        $ids = array_map(
            fn($facility) => (int)($facility['fac_id'] ?? 0),
            self::filteredFacilities($filters)
        );
        $ids = array_values(array_filter(array_unique($ids)));

        if (!$ids) {
            return ['sql' => 'WHERE 1=0', 'types' => '', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return [
            'sql' => "WHERE a.fac_id_fk IN ({$placeholders})",
            'types' => str_repeat('i', count($ids)),
            'params' => $ids
        ];
    }

    /**
     * Handles action plan where processing for this API workflow.
     */
    private static function actionPlanWhere(mysqli $con, array $filters): array
    {
        return self::facilityWhere($con, $filters);
    }

    /**
     * Handles performance where processing for this API workflow.
     */
    private static function performanceWhere(mysqli $con, array $filters): array
    {
        $where = self::facilityWhere($con, $filters);
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
        return $where;
    }

    /**
     * Handles table exists processing for this API workflow.
     */
    private static function tableExists(mysqli $con, string $table): bool
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
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['table_count'] ?? 0) > 0;
    }

    /**
     * Handles column exists processing for this API workflow.
     */
    private static function columnExists(mysqli $con, string $table, string $column): bool
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
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['column_count'] ?? 0) > 0;
    }

    /**
     * Returns department-status assessment reference column for the installed schema.
     */
    private static function departmentStatusAssessmentColumn(mysqli $con): string
    {
        return self::columnExists($con, 'assessment_department_status', 'assessment_id')
            ? 'assessment_id'
            : 'ass_period_id';
    }

    /**
     * Handles facility type id column processing for this API workflow.
     */
    private static function facilityTypeIdColumn(mysqli $con): ?string
    {
        if (!self::tableExists($con, 'facilities_type')) {
            return null;
        }

        foreach (['fac_type_id', 'facilities_type_id', 'f_id', 'id'] as $column) {
            if (self::columnExists($con, 'facilities_type', $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Handles cert facility column processing for this API workflow.
     */
    private static function certFacilityColumn(mysqli $con): string
    {
        foreach (['fac_id_fk', 'fac_id'] as $column) {
            if (self::columnExists($con, 'cert_details', $column)) {
                return $column;
            }
        }

        return 'fac_id';
    }

    /**
     * Handles scalar processing for this API workflow.
     */
    private static function scalar(mysqli $con, string $sql, string $types = '', array $params = []): mixed
    {
        $row = self::one($con, $sql, $types, $params);
        return $row ? reset($row) : 0;
    }

    /**
     * Handles one processing for this API workflow.
     */
    private static function one(mysqli $con, string $sql, string $types = '', array $params = []): array
    {
        $rows = self::rows($con, $sql, $types, $params);
        return $rows[0] ?? [];
    }

    /**
     * Handles rows processing for this API workflow.
     */
    private static function rows(mysqli $con, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('State query prepare failed: ' . $con->error);
        }
        if ($types !== '' && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Handles percent processing for this API workflow.
     */
    private static function percent(float $value, float $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }
}
