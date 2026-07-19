<?php

/**
 * DepartmentStatusService.php
 * -------------------------------------------------------
 * Runtime department activation/deactivation service
 * for facility + assessment period.
 */

class DepartmentStatusService
{
    private mysqli $db;
    private ?string $assessmentColumn = null;

    /**
     * Handles construct processing for this API workflow.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Save or update department active status
     */
    public function saveStatus(array $data): array
    {
        try {
            $assessmentColumn = $this->assessmentColumn();

            $sql = "
                INSERT INTO assessment_department_status
                    (fac_id_fk, {$assessmentColumn}, dept_id, is_active, activated_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    is_active = VALUES(is_active),
                    activated_by = VALUES(activated_by),
                    updated_on = CURRENT_TIMESTAMP
            ";

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param(
                "iiiii",
                $data['fac_id'],
                $data['ass_period'],
                $data['dept_id'],
                $data['is_active'],
                $data['user_id']
            );

            $stmt->execute();

            return $this->success("Department status saved", [
                "fac_id"        => (int)$data['fac_id'],
                "assessment_id" => (int)$data['ass_period'],
                "ass_period"    => (int)$data['ass_period'],
                "dept_id"       => (int)$data['dept_id'],
                "is_active"     => (int)$data['is_active']
            ]);

        } catch (Throwable $e) {
            return $this->error("Save failed: " . $e->getMessage());
        }
    }

    /**
     * Save multiple department statuses
     */
    public function saveBulkStatus(array $data): array
    {
        if (!isset($data['departments']) || !is_array($data['departments'])) {
            return $this->error("departments array is required");
        }

        $this->db->begin_transaction();

        try {
            $saved = [];
            $assessmentColumn = $this->assessmentColumn();

            foreach ($data['departments'] as $department) {
                if (!isset($department['dept_id'], $department['is_active'])) {
                    throw new Exception("Each department must have dept_id and is_active");
                }

                $payload = [
                    "fac_id"     => (int)$data['fac_id'],
                    "ass_period" => (int)$data['ass_period'],
                    "dept_id"    => (int)$department['dept_id'],
                    "is_active"  => (int)$department['is_active'],
                    "user_id"    => (int)$data['user_id']
                ];

                $sql = "
                    INSERT INTO assessment_department_status
                        (fac_id_fk, {$assessmentColumn}, dept_id, is_active, activated_by)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_active = VALUES(is_active),
                        activated_by = VALUES(activated_by),
                        updated_on = CURRENT_TIMESTAMP
                ";

                $stmt = $this->db->prepare($sql);

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->db->error);
                }

                $stmt->bind_param(
                    "iiiii",
                    $payload['fac_id'],
                    $payload['ass_period'],
                    $payload['dept_id'],
                    $payload['is_active'],
                    $payload['user_id']
                );

                $stmt->execute();

                $saved[] = [
                    "dept_id"   => $payload['dept_id'],
                    "is_active" => $payload['is_active']
                ];
            }

            $this->db->commit();

            return $this->success("Department statuses saved", [
                "fac_id"        => (int)$data['fac_id'],
                "assessment_id" => (int)$data['ass_period'],
                "ass_period"    => (int)$data['ass_period'],
                "departments"   => $saved
            ]);

        } catch (Throwable $e) {
            $this->db->rollback();
            return $this->error("Bulk save failed: " . $e->getMessage());
        }
    }

    /**
     * Get department status list for facility + assessment period
     */
    public function getStatusList(int $facId, int $assPeriod): array
    {
        try {
            $assessmentColumn = $this->assessmentColumn();

            $sql = "
                SELECT
                    dept_id,
                    is_active,
                    activated_by,
                    activated_on,
                    updated_on
                FROM assessment_department_status
                WHERE fac_id_fk = ?
                  AND {$assessmentColumn} = ?
                ORDER BY dept_id
            ";

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param("ii", $facId, $assPeriod);
            $stmt->execute();

            $res = $stmt->get_result();

            $data = [];

            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    "dept_id"      => (int)$row['dept_id'],
                    "is_active"    => (int)$row['is_active'],
                    "activated_by" => $row['activated_by'] !== null ? (int)$row['activated_by'] : null,
                    "activated_on" => $row['activated_on'],
                    "updated_on"   => $row['updated_on']
                ];
            }

            return $this->success("Department status fetched", $data);

        } catch (Throwable $e) {
            return $this->error("Fetch failed: " . $e->getMessage());
        }
    }

    /**
     * Check if department is active
     */
    public function isDepartmentActive(int $facId, int $assPeriod, int $deptId): bool
    {
        $assessmentColumn = $this->assessmentColumn();

        $sql = "
            SELECT is_active
            FROM assessment_department_status
            WHERE fac_id_fk = ?
              AND {$assessmentColumn} = ?
              AND dept_id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("iii", $facId, $assPeriod, $deptId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return false;
        }

        return (int)$row['is_active'] === 1;
    }

    /**
     * Returns the assessment reference column used by the installed schema.
     */
    private function assessmentColumn(): string
    {
        if ($this->assessmentColumn !== null) {
            return $this->assessmentColumn;
        }

        if ($this->columnExists('assessment_id')) {
            $this->assessmentColumn = 'assessment_id';
            return $this->assessmentColumn;
        }

        $this->assessmentColumn = 'ass_period_id';
        return $this->assessmentColumn;
    }

    /**
     * Checks whether a column exists on assessment_department_status.
     */
    private function columnExists(string $column): bool
    {
        $sql = "
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'assessment_department_status'
              AND COLUMN_NAME = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $column);
        $stmt->execute();

        return (bool)$stmt->get_result()->fetch_assoc();
    }

    /**
     * Handles success processing for this API workflow.
     */
    private function success(string $message, mixed $data = null): array
    {
        return [
            "status"  => "success",
            "message" => $message,
            "data"    => $data
        ];
    }

    /**
     * Handles error processing for this API workflow.
     */
    private function error(string $message): array
    {
        if ($this->isSensitiveError($message)) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::log($message, ['service' => self::class]);
                $message = ErrorHandler::friendlyMessage();
            } else {
                error_log('[SaQshi API Error] ' . $message);
                $message = 'Something went wrong while processing your request. Please try again.';
            }
        }

        return [
            "status"  => "error",
            "message" => $message,
            "data"    => null
        ];
    }

    /**
     * Detects low-level system/database messages that must not be returned to users.
     */
    private function isSensitiveError(string $message): bool
    {
        return (bool) preg_match(
            '/prepare failed|SQLSTATE|mysqli|syntax error|duplicate entry|unknown column|table .* doesn.?t exist|cannot add or update|foreign key|data too long|you have an error in your sql|access denied/i',
            $message
        );
    }
}
