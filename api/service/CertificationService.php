<?php

require_once __DIR__ . '/CertificationValidator.php';
require_once __DIR__ . '/CertificationExpiryService.php';

/**
 * Provides certification service behavior for SaQshi API workflows.
 */
class CertificationService
{
    /**
     * Handles config processing for this API workflow.
     */
    public static function config(): array
    {
        $path = __DIR__ . '/../config/certification/certification.json';
        $data = file_exists($path) ? json_decode(file_get_contents($path), true) : [];

        return is_array($data) ? $data : [];
    }

    /**
     * Handles ensure tables processing for this API workflow.
     */
    public static function ensureTables(mysqli $con): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS cert_details (
                id BIGINT NOT NULL AUTO_INCREMENT,
                dist VARCHAR(150) NULL,
                block VARCHAR(150) NULL,
                fac_name VARCHAR(255) NULL,
                fac_type VARCHAR(100) NULL,
                cert_type VARCHAR(30) NOT NULL,
                cert_detailscol TEXT NULL,
                applied_date DATE NULL,
                cert_issue DATE NULL,
                validity DATE NULL,
                score DECIMAL(6,2) NULL,
                lat DECIMAL(11,8) NULL,
                longi DECIMAL(11,8) NULL,
                dist_id INT NULL,
                block_id INT NULL,
                fac_id INT NULL,
                fac_nin BIGINT NULL,
                Cert_status VARCHAR(60) NOT NULL,
                ass_mod VARCHAR(30) NOT NULL,
                date_of_ass DATE NOT NULL,
                state_id INT NULL,
                created_by INT NULL,
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_by INT NULL,
                updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cert_fac_nin (fac_nin),
                KEY idx_cert_fac_id (fac_id),
                KEY idx_cert_type (cert_type),
                KEY idx_cert_validity (validity)
            )
        ";

        if (!$con->query($sql)) {
            throw new RuntimeException('Unable to create cert_details table: ' . $con->error);
        }

        self::ensureColumn($con, 'cert_details', 'applied_date', 'DATE NULL AFTER cert_detailscol');

        $historySql = "
            CREATE TABLE IF NOT EXISTS certification_history (
                history_id BIGINT NOT NULL AUTO_INCREMENT,
                certification_id BIGINT NULL,
                fac_id_fk INT NULL,
                fac_nin BIGINT NULL,
                old_data_json LONGTEXT NULL,
                new_data_json LONGTEXT NULL,
                action_type VARCHAR(30) NOT NULL,
                action_by INT NULL,
                action_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (history_id),
                KEY idx_cert_history_record (certification_id),
                KEY idx_cert_history_fac (fac_id_fk, fac_nin)
            )
        ";

        if (!$con->query($historySql)) {
            throw new RuntimeException('Unable to create certification_history table: ' . $con->error);
        }
    }

    /**
     * Handles current processing for this API workflow.
     */
    public static function current(mysqli $con, array $filters): ?array
    {
        $rows = self::list($con, $filters + ['limit' => 1]);
        return $rows[0] ?? null;
    }

    /**
     * Handles list processing for this API workflow.
     */
    public static function list(mysqli $con, array $filters = []): array
    {
        self::ensureTables($con);

        $where = [];
        $params = [];
        $types = '';

        foreach (['fac_nin' => 'i', 'fac_id' => 'i', 'dist_id' => 'i', 'block_id' => 'i'] as $key => $type) {
            if (!empty($filters[$key])) {
                $where[] = $key . ' = ?';
                $params[] = (int)$filters[$key];
                $types .= $type;
            }
        }

        if (!empty($filters['certification_type']) || !empty($filters['cert_type'])) {
            $where[] = 'UPPER(cert_type) = ?';
            $params[] = strtoupper((string)($filters['certification_type'] ?? $filters['cert_type']));
            $types .= 's';
        }

        if (!empty($filters['status']) || !empty($filters['Cert_status'])) {
            $where[] = 'UPPER(Cert_status) = ?';
            $params[] = CertificationExpiryService::normalizeStatus((string)($filters['status'] ?? $filters['Cert_status']));
            $types .= 's';
        }

        $sql = 'SELECT * FROM cert_details';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY date_of_ass DESC, id DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int)$filters['limit']);
        }

        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Certification list prepare failed: ' . $con->error);
        }

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = self::present($row);
        }

        return $rows;
    }

    /**
     * Handles save processing for this API workflow.
     */
    public static function save(mysqli $con, array $payload): array
    {
        self::ensureTables($con);
        $payload = self::withAssignedFacility($payload);
        $config = self::config();
        $errors = CertificationValidator::validatePayload($payload, $config);

        if ($errors) {
            throw new InvalidArgumentException(json_encode($errors));
        }

        $type = strtoupper((string)($payload['certification_type'] ?? $payload['cert_type']));
        $status = CertificationExpiryService::normalizeStatus((string)($payload['status'] ?? $payload['Cert_status']));
        $date = (string)($payload['certification_date'] ?? $payload['date_of_ass']);
        $mode = strtoupper((string)($payload['assessment_mode'] ?? $payload['ass_mod']));
        $validity = CertificationExpiryService::calculateValidTo($status, $date, $config);

        if ($type === 'NATIONAL' && !empty($config['national_requires_state'])) {
            $stateRows = self::list($con, [
                'fac_nin' => $payload['fac_nin'] ?? null,
                'fac_id' => $payload['fac_id'] ?? null,
                'certification_type' => 'STATE',
                'limit' => 1
            ]);

            if (!$stateRows) {
                throw new DomainException('National certification requires an existing State certification.');
            }
        }

        $stmt = $con->prepare("
            INSERT INTO cert_details
            (dist, block, fac_name, fac_type, cert_type, cert_detailscol, applied_date, cert_issue, validity,
             score, lat, longi, dist_id, block_id, fac_id, fac_nin, Cert_status, ass_mod,
             date_of_ass, state_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Certification save prepare failed: ' . $con->error);
        }

        $dist = $payload['dist'] ?? null;
        $block = $payload['block'] ?? null;
        $facName = $payload['fac_name'] ?? null;
        $facType = $payload['fac_type'] ?? null;
        $remarks = $payload['remarks'] ?? $payload['cert_detailscol'] ?? null;
        $appliedDate = ($payload['applied_date'] ?? '') !== '' ? $payload['applied_date'] : null;
        $certIssue = $payload['cert_issue'] ?? $validity['valid_from'];
        $validTo = $validity['valid_to'];
        $score = (float)$payload['score'];
        $lat = isset($payload['lat']) ? (float)$payload['lat'] : null;
        $longi = isset($payload['longi']) ? (float)$payload['longi'] : null;
        $distId = isset($payload['dist_id']) ? (int)$payload['dist_id'] : null;
        $blockId = isset($payload['block_id']) ? (int)$payload['block_id'] : null;
        $facId = isset($payload['fac_id']) ? (int)$payload['fac_id'] : null;
        $facNin = isset($payload['fac_nin']) ? (int)$payload['fac_nin'] : null;
        $stateId = isset($payload['state_id']) ? (int)$payload['state_id'] : null;
        $stmt->bind_param(
            'sssssssssdddiiiisssi',
            $dist,
            $block,
            $facName,
            $facType,
            $type,
            $remarks,
            $appliedDate,
            $certIssue,
            $validTo,
            $score,
            $lat,
            $longi,
            $distId,
            $blockId,
            $facId,
            $facNin,
            $status,
            $mode,
            $date,
            $stateId
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Certification save failed: ' . $stmt->error);
        }

        $id = (int)$stmt->insert_id;
        $row = self::findById($con, $id);
        $userId = self::currentUserId();
        self::audit($con, $id, $facId, $facNin, null, $row, 'CREATE', $userId);

        return $row;
    }

    /**
     * Handles update processing for this API workflow.
     */
    public static function update(mysqli $con, int $id, array $payload): array
    {
        self::ensureTables($con);
        $payload = self::withAssignedFacility($payload);
        $current = self::rawById($con, $id);

        if (!$current) {
            throw new OutOfBoundsException('Certification record not found.');
        }

        $merged = array_merge($current, $payload);
        $merged['certification_type'] = $payload['certification_type'] ?? $payload['cert_type'] ?? $current['cert_type'];
        $merged['certification_date'] = $payload['certification_date'] ?? $payload['date_of_ass'] ?? $current['date_of_ass'];
        $merged['applied_date'] = $payload['applied_date'] ?? $current['applied_date'] ?? '';
        $merged['assessment_mode'] = $payload['assessment_mode'] ?? $payload['ass_mod'] ?? $current['ass_mod'];
        $merged['status'] = $payload['status'] ?? $payload['Cert_status'] ?? $current['Cert_status'];

        $config = self::config();
        $errors = CertificationValidator::validatePayload($merged, $config);
        if ($errors) {
            throw new InvalidArgumentException(json_encode($errors));
        }

        $type = strtoupper((string)$merged['certification_type']);
        $status = CertificationExpiryService::normalizeStatus((string)$merged['status']);
        $date = (string)$merged['certification_date'];
        $mode = strtoupper((string)$merged['assessment_mode']);
        $validity = CertificationExpiryService::calculateValidTo($status, $date, $config);
        $remarks = $payload['remarks'] ?? $payload['cert_detailscol'] ?? $current['cert_detailscol'];
        $score = (float)$merged['score'];
        $validTo = $validity['valid_to'];
        $userId = self::currentUserId();
        $appliedDate = ($merged['applied_date'] ?? '') !== '' ? $merged['applied_date'] : null;

        $stmt = $con->prepare("
            UPDATE cert_details
            SET cert_type = ?, Cert_status = ?, date_of_ass = ?, ass_mod = ?,
                score = ?, validity = ?, cert_issue = ?, cert_detailscol = ?, applied_date = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new RuntimeException('Certification update prepare failed: ' . $con->error);
        }

        $certIssue = $payload['cert_issue'] ?? $validity['valid_from'];
        $stmt->bind_param('ssssdssssi', $type, $status, $date, $mode, $score, $validTo, $certIssue, $remarks, $appliedDate, $id);

        if (!$stmt->execute()) {
            throw new RuntimeException('Certification update failed: ' . $stmt->error);
        }

        $row = self::findById($con, $id);
        self::audit($con, $id, (int)($current['fac_id'] ?? 0), (int)($current['fac_nin'] ?? 0), $current, $row, 'UPDATE', $userId);

        return $row;
    }

    /**
     * Handles dashboard processing for this API workflow.
     */
    public static function dashboard(mysqli $con, array $filters): array
    {
        $rows = self::list($con, $filters);
        $latest = [];
        foreach ($rows as $row) {
            $type = $row['certification_type'];
            if (!isset($latest[$type])) {
                $latest[$type] = $row;
            }
        }

        $nextExpiry = null;
        foreach ($latest as $row) {
            if ($row['valid_to'] && (!$nextExpiry || $row['valid_to'] < $nextExpiry['valid_to'])) {
                $nextExpiry = $row;
            }
        }

        return [
            'state_status' => $latest['STATE']['status'] ?? 'NOT_STARTED',
            'national_status' => $latest['NATIONAL']['status'] ?? 'NOT_STARTED',
            'next_expiry' => $nextExpiry,
            'records_count' => count($rows),
            'latest' => $latest,
            'renewal_status' => $nextExpiry['renewal_status'] ?? 'NOT_AVAILABLE'
        ];
    }

    /**
     * Handles history processing for this API workflow.
     */
    public static function history(mysqli $con, array $filters): array
    {
        return self::list($con, $filters);
    }

    /**
     * Handles find by id processing for this API workflow.
     */
    public static function findById(mysqli $con, int $id): array
    {
        $row = self::rawById($con, $id);
        if (!$row) {
            throw new OutOfBoundsException('Certification record not found.');
        }

        return self::present($row);
    }

    /**
     * Handles raw by id processing for this API workflow.
     */
    private static function rawById(mysqli $con, int $id): ?array
    {
        self::ensureTables($con);
        $stmt = $con->prepare('SELECT * FROM cert_details WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return $row ?: null;
    }

    /**
     * Handles present processing for this API workflow.
     */
    private static function present(array $row): array
    {
        $validTo = $row['validity'] ?? null;
        $dueDays = (int)(self::config()['renewal_due_days'] ?? 90);
        $status = CertificationExpiryService::normalizeStatus((string)($row['Cert_status'] ?? ''));
        $years = null;

        try {
            $years = CertificationExpiryService::calculateValidTo($status, (string)$row['date_of_ass'], self::config())['validity_years'];
        } catch (Throwable $e) {
            $years = null;
        }

        return [
            'certification_id' => (int)($row['id'] ?? 0),
            'fac_id' => isset($row['fac_id']) ? (int)$row['fac_id'] : null,
            'fac_nin' => isset($row['fac_nin']) ? (int)$row['fac_nin'] : null,
            'fac_name' => $row['fac_name'] ?? null,
            'fac_type' => $row['fac_type'] ?? null,
            'certification_type' => strtoupper((string)($row['cert_type'] ?? '')),
            'certification_date' => $row['date_of_ass'] ?? null,
            'applied_date' => $row['applied_date'] ?? null,
            'assessment_mode' => strtoupper((string)($row['ass_mod'] ?? '')),
            'score' => isset($row['score']) ? (float)$row['score'] : null,
            'status' => $status,
            'validity_years' => $years,
            'valid_from' => $row['cert_issue'] ?? $row['date_of_ass'] ?? null,
            'valid_to' => $validTo,
            'renewal_status' => CertificationExpiryService::renewalStatus($validTo, $dueDays),
            'remarks' => $row['cert_detailscol'] ?? null,
            'district' => $row['dist'] ?? null,
            'block' => $row['block'] ?? null,
            'raw' => $row
        ];
    }

    /**
     * Handles audit processing for this API workflow.
     */
    private static function audit(mysqli $con, int $id, ?int $facId, ?int $facNin, ?array $old, array $new, string $action, int $userId): void
    {
        $stmt = $con->prepare("
            INSERT INTO certification_history
            (certification_id, fac_id_fk, fac_nin, old_data_json, new_data_json, action_type, action_by)
            VALUES (?,?,?,?,?,?,?)
        ");

        if (!$stmt) {
            return;
        }

        $oldJson = $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $newJson = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->bind_param('iiisssi', $id, $facId, $facNin, $oldJson, $newJson, $action, $userId);
        $stmt->execute();
    }

    /**
     * Handles ensure column processing for this API workflow.
     */
    private static function ensureColumn(mysqli $con, string $table, string $column, string $definition): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            throw new InvalidArgumentException('Invalid schema identifier');
        }

        $tableEscaped = '`' . str_replace('`', '``', $table) . '`';
        $columnEscaped = '`' . str_replace('`', '``', $column) . '`';
        $columnLike = $con->real_escape_string($column);
        $result = $con->query("SHOW COLUMNS FROM {$tableEscaped} LIKE '{$columnLike}'");

        if ($result && $result->num_rows > 0) {
            return;
        }

        if (!$con->query("ALTER TABLE {$tableEscaped} ADD COLUMN {$columnEscaped} {$definition}")) {
            throw new RuntimeException("Unable to add {$column} column: " . $con->error);
        }
    }

    /**
     * Handles current user id processing for this API workflow.
     */
    private static function currentUserId(): int
    {
        return (int)($_SESSION['u_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['uid'] ?? 0);
    }

    /**
     * Handles session facility id processing for this API workflow.
     */
    private static function sessionFacilityId(): int
    {
        return (int)($_SESSION['fac_id'] ?? 0);
    }

    /**
     * Handles with assigned facility processing for this API workflow.
     */
    private static function withAssignedFacility(array $payload): array
    {
        $facId = (int)($payload['fac_id'] ?? 0);
        if ($facId <= 0) {
            $facId = self::sessionFacilityId();
            if ($facId > 0) {
                $payload['fac_id'] = $facId;
            }
        }

        if ($facId <= 0) {
            return $payload;
        }

        $facility = self::facilityFromJson($facId);
        if (!$facility) {
            return $payload;
        }

        $defaults = [
            'fac_name' => $facility['fac_name'] ?? '',
            'fac_nin' => $facility['nin_no'] ?? $facility['NIN_no'] ?? '',
            'fac_type' => $facility['facilities_type'] ?? $facility['fac_type_id'] ?? '',
            'dist' => $facility['dist_name'] ?? $facility['Dist_Name'] ?? '',
            'block' => $facility['block_name'] ?? $facility['Block_Name'] ?? '',
            'dist_id' => $facility['dist_id'] ?? null,
            'block_id' => $facility['block_id'] ?? null,
            'state_id' => $facility['state_id'] ?? null
        ];

        foreach ($defaults as $key => $value) {
            if ((!isset($payload[$key]) || $payload[$key] === '') && $value !== null && $value !== '') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Handles facility from json processing for this API workflow.
     */
    private static function facilityFromJson(int $facId): ?array
    {
        $path = __DIR__ . '/../config/masters/facilities.json';
        if ($facId <= 0 || !file_exists($path)) {
            return null;
        }

        $states = json_decode(file_get_contents($path), true);
        if (!is_array($states)) {
            return null;
        }

        foreach ($states as $state) {
            foreach (($state['divisions'] ?? []) as $division) {
                foreach (($division['districts'] ?? []) as $district) {
                    foreach (($district['blocks'] ?? []) as $block) {
                        foreach (($block['facilities'] ?? []) as $facility) {
                            if ((int)($facility['fac_id'] ?? 0) === $facId) {
                                return array_merge($facility, [
                                    'state_id' => $state['state_id'] ?? null,
                                    'dist_id' => $district['dist_id'] ?? null,
                                    'dist_name' => $district['dist_name'] ?? '',
                                    'block_id' => $block['block_id'] ?? null,
                                    'block_name' => $block['block_name'] ?? ''
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
