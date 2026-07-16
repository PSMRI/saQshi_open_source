<?php

/**
 * DynamicAssessmentService.php
 * -------------------------------------------------------
 * SaQshi Configurable Assessment Engine
 *
 * Hierarchy:
 * Facility + Period + Framework + Instance = Assessment Cycle
 * One Cycle can have multiple Departments
 * One Department can have multiple Checklist Responses
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../core/FrameworkEngine.php';

/**
 * Provides dynamic assessment service behavior for SaQshi API workflows.
 */
class DynamicAssessmentService
{
    private mysqli $db;

    /**
     * Handles construct processing for this API workflow.
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Handles create cycle processing for this API workflow.
     */
    public function createCycle(array $data): array
    {
        try {
            $sql = "
                INSERT INTO assessment_cycle
                    (fac_id_fk, ass_period_id, framework_code, instance_no, status, created_by)
                VALUES (?, ?, ?, ?, 'DRAFT', ?)
                ON DUPLICATE KEY UPDATE
                    updated_on = CURRENT_TIMESTAMP
            ";

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param(
                "iisii",
                $data['fac_id'],
                $data['ass_period'],
                $data['framework_code'],
                $data['instance_no'],
                $data['user_id']
            );

            $stmt->execute();

            $cycleId = $stmt->insert_id;

            if ($cycleId === 0) {
                $cycleId = $this->getCycleId(
                    (int)$data['fac_id'],
                    (int)$data['ass_period'],
                    $data['framework_code'],
                    (int)$data['instance_no']
                );
            }

            return $this->success("Assessment cycle created", [
                "cycle_id"       => (int)$cycleId,
                "fac_id"         => (int)$data['fac_id'],
                "ass_period"     => (int)$data['ass_period'],
                "framework_code" => $data['framework_code'],
                "instance_no"    => (int)$data['instance_no']
            ]);

        } catch (Throwable $e) {
            return $this->error("Cycle creation failed: " . $e->getMessage());
        }
    }

    /**
     * Handles add departments processing for this API workflow.
     */
    public function addDepartments(int $cycleId, array $departments): array
    {
        if (empty($departments)) {
            return $this->error("departments array is required");
        }

        $this->db->begin_transaction();

        try {
            $saved = [];

            foreach ($departments as $dept) {
                if (!isset($dept['dept_id'])) {
                    throw new Exception("dept_id is required");
                }

                $deptId = (int)$dept['dept_id'];
                $isActive = isset($dept['is_active']) ? (int)$dept['is_active'] : 1;

                $sql = "
                    INSERT INTO assessment_cycle_department
                        (cycle_id, dept_id, is_active)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_active = VALUES(is_active),
                        updated_on = CURRENT_TIMESTAMP
                ";

                $stmt = $this->db->prepare($sql);

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->db->error);
                }

                $stmt->bind_param("iii", $cycleId, $deptId, $isActive);
                $stmt->execute();

                $saved[] = [
                    "dept_id" => $deptId,
                    "is_active" => $isActive
                ];
            }

            $this->db->commit();

            return $this->success("Departments added to cycle", [
                "cycle_id" => $cycleId,
                "departments" => $saved
            ]);

        } catch (Throwable $e) {
            $this->db->rollback();
            return $this->error("Department save failed: " . $e->getMessage());
        }
    }

    /**
     * Handles save response processing for this API workflow.
     */
    public function saveResponse(array $data): array
    {
        $this->db->begin_transaction();

        try {
            if (!$this->isDepartmentActive((int)$data['cycle_id'], (int)$data['dept_id'])) {
                throw new Exception("Department is inactive for this assessment cycle");
            }

            $score = $this->calculateCheckpointScore(
                $data['framework_code'],
                (int)$data['checkpoint_id'],
                $data['response_value']
            );

            $sql = "
                INSERT INTO assessment_cycle_response
                    (
                        cycle_id,
                        dept_id,
                        checkpoint_id,
                        response_value,
                        score,
                        remarks,
                        evidence_url,
                        updated_by
                    )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    response_value = VALUES(response_value),
                    score = VALUES(score),
                    remarks = VALUES(remarks),
                    evidence_url = VALUES(evidence_url),
                    updated_by = VALUES(updated_by),
                    updated_on = CURRENT_TIMESTAMP
            ";

            $remarks = $data['remarks'] ?? null;
            $evidenceUrl = $data['evidence_url'] ?? null;

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param(
                "iiisdssi",
                $data['cycle_id'],
                $data['dept_id'],
                $data['checkpoint_id'],
                $data['response_value'],
                $score,
                $remarks,
                $evidenceUrl,
                $data['user_id']
            );

            $stmt->execute();

            $this->markDepartmentInProgress(
                (int)$data['cycle_id'],
                (int)$data['dept_id']
            );

            $this->markCycleInProgress(
                (int)$data['cycle_id']
            );

            $this->db->commit();

            return $this->success("Response saved", [
                "cycle_id"      => (int)$data['cycle_id'],
                "dept_id"       => (int)$data['dept_id'],
                "checkpoint_id" => (int)$data['checkpoint_id'],
                "score"         => $score
            ]);

        } catch (Throwable $e) {
            $this->db->rollback();
            return $this->error("Response save failed: " . $e->getMessage());
        }
    }

    /**
     * Handles get cycle processing for this API workflow.
     */
    public function getCycle(int $cycleId): array
    {
        try {
            $sql = "
                SELECT *
                FROM assessment_cycle
                WHERE cycle_id = ?
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param("i", $cycleId);
            $stmt->execute();

            $cycle = $stmt->get_result()->fetch_assoc();

            if (!$cycle) {
                return $this->error("Cycle not found");
            }

            $deptSql = "
                SELECT *
                FROM assessment_cycle_department
                WHERE cycle_id = ?
                ORDER BY dept_id
            ";

            $stmt = $this->db->prepare($deptSql);
            $stmt->bind_param("i", $cycleId);
            $stmt->execute();

            $departments = [];

            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $departments[] = [
                    "dept_id"      => (int)$row['dept_id'],
                    "is_active"    => (int)$row['is_active'],
                    "status"       => $row['status'],
                    "started_on"   => $row['started_on'],
                    "completed_on" => $row['completed_on']
                ];
            }

            return $this->success("Cycle fetched", [
                "cycle" => [
                    "cycle_id"       => (int)$cycle['cycle_id'],
                    "fac_id"         => (int)$cycle['fac_id_fk'],
                    "ass_period"     => (int)$cycle['ass_period_id'],
                    "framework_code" => $cycle['framework_code'],
                    "instance_no"    => (int)$cycle['instance_no'],
                    "status"         => $cycle['status'],
                    "created_by"     => (int)$cycle['created_by'],
                    "created_on"     => $cycle['created_on']
                ],
                "departments" => $departments
            ]);

        } catch (Throwable $e) {
            return $this->error("Cycle fetch failed: " . $e->getMessage());
        }
    }

    /**
     * Handles get responses processing for this API workflow.
     */
    public function getResponses(int $cycleId, ?int $deptId = null): array
    {
        try {
            if ($deptId !== null) {
                $sql = "
                    SELECT *
                    FROM assessment_cycle_response
                    WHERE cycle_id = ?
                      AND dept_id = ?
                    ORDER BY checkpoint_id
                ";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ii", $cycleId, $deptId);
            } else {
                $sql = "
                    SELECT *
                    FROM assessment_cycle_response
                    WHERE cycle_id = ?
                    ORDER BY dept_id, checkpoint_id
                ";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("i", $cycleId);
            }

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->execute();
            $res = $stmt->get_result();

            $responses = [];

            while ($row = $res->fetch_assoc()) {
                $responses[] = [
                    "response_id"    => (int)$row['response_id'],
                    "cycle_id"       => (int)$row['cycle_id'],
                    "dept_id"        => (int)$row['dept_id'],
                    "checkpoint_id"  => (int)$row['checkpoint_id'],
                    "response_value" => $row['response_value'],
                    "score"          => (float)$row['score'],
                    "remarks"        => $row['remarks'],
                    "evidence_url"   => $row['evidence_url'],
                    "updated_by"     => $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
                    "updated_on"     => $row['updated_on']
                ];
            }

            return $this->success("Responses fetched", $responses);

        } catch (Throwable $e) {
            return $this->error("Response fetch failed: " . $e->getMessage());
        }
    }

    /**
     * Handles complete department processing for this API workflow.
     */
    public function completeDepartment(int $cycleId, int $deptId): array
    {
        try {
            $sql = "
                UPDATE assessment_cycle_department
                SET status = 'COMPLETED',
                    completed_on = CURRENT_TIMESTAMP
                WHERE cycle_id = ?
                  AND dept_id = ?
                  AND is_active = 1
            ";

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                return $this->error("Prepare failed: " . $this->db->error);
            }

            $stmt->bind_param("ii", $cycleId, $deptId);
            $stmt->execute();

            return $this->success("Department completed", [
                "cycle_id" => $cycleId,
                "dept_id"  => $deptId
            ]);

        } catch (Throwable $e) {
            return $this->error("Department completion failed: " . $e->getMessage());
        }
    }

    /**
     * Handles complete cycle processing for this API workflow.
     */
    public function completeCycle(int $cycleId): array
    {
        try {
            $sqlCheck = "
                SELECT COUNT(*) AS pending
                FROM assessment_cycle_department
                WHERE cycle_id = ?
                  AND is_active = 1
                  AND status <> 'COMPLETED'
            ";

            $stmt = $this->db->prepare($sqlCheck);
            $stmt->bind_param("i", $cycleId);
            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            if ((int)$row['pending'] > 0) {
                return $this->error("Cannot complete cycle. Active departments are pending.");
            }

            $sql = "
                UPDATE assessment_cycle
                SET status = 'COMPLETED'
                WHERE cycle_id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $cycleId);
            $stmt->execute();

            return $this->success("Assessment cycle completed", [
                "cycle_id" => $cycleId
            ]);

        } catch (Throwable $e) {
            return $this->error("Cycle completion failed: " . $e->getMessage());
        }
    }

    /**
     * Handles calculate cycle score processing for this API workflow.
     */
    public function calculateCycleScore(int $cycleId): array
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) AS total_responses,
                    COALESCE(SUM(score), 0) AS obtained_score
                FROM assessment_cycle_response
                WHERE cycle_id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $cycleId);
            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            return $this->success("Cycle score calculated", [
                "cycle_id"        => $cycleId,
                "total_responses" => (int)$row['total_responses'],
                "obtained_score"  => (float)$row['obtained_score']
            ]);

        } catch (Throwable $e) {
            return $this->error("Score calculation failed: " . $e->getMessage());
        }
    }

    /**
     * Handles calculate checkpoint score processing for this API workflow.
     */
    private function calculateCheckpointScore(
        string $frameworkCode,
        int $checkpointId,
        mixed $responseValue
    ): float {
        $engine = FrameworkEngine::load($frameworkCode);

        $checkpoint = $engine->getCheckpointById($checkpointId);

        if (!$checkpoint) {
            return 0;
        }

        if (!isset($checkpoint['options']) || !is_array($checkpoint['options'])) {
            return is_numeric($responseValue) ? (float)$responseValue : 0;
        }

        foreach ($checkpoint['options'] as $option) {
            if ((string)$option['value'] === (string)$responseValue) {
                return (float)($option['score'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Handles is department active processing for this API workflow.
     */
    private function isDepartmentActive(int $cycleId, int $deptId): bool
    {
        $sql = "
            SELECT is_active
            FROM assessment_cycle_department
            WHERE cycle_id = ?
              AND dept_id = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $cycleId, $deptId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        return $row && (int)$row['is_active'] === 1;
    }

    /**
     * Handles mark department in progress processing for this API workflow.
     */
    private function markDepartmentInProgress(int $cycleId, int $deptId): void
    {
        $sql = "
            UPDATE assessment_cycle_department
            SET status = 'IN_PROGRESS',
                started_on = IFNULL(started_on, CURRENT_TIMESTAMP)
            WHERE cycle_id = ?
              AND dept_id = ?
              AND status = 'NOT_STARTED'
        ";

        $stmt = $this->db->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ii", $cycleId, $deptId);
            $stmt->execute();
        }
    }

    /**
     * Handles mark cycle in progress processing for this API workflow.
     */
    private function markCycleInProgress(int $cycleId): void
    {
        $sql = "
            UPDATE assessment_cycle
            SET status = 'IN_PROGRESS'
            WHERE cycle_id = ?
              AND status = 'DRAFT'
        ";

        $stmt = $this->db->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $cycleId);
            $stmt->execute();
        }
    }

    /**
     * Handles get cycle id processing for this API workflow.
     */
    private function getCycleId(
        int $facId,
        int $assPeriod,
        string $frameworkCode,
        int $instanceNo
    ): int {
        $sql = "
            SELECT cycle_id
            FROM assessment_cycle
            WHERE fac_id_fk = ?
              AND ass_period_id = ?
              AND framework_code = ?
              AND instance_no = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iisi", $facId, $assPeriod, $frameworkCode, $instanceNo);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        return $row ? (int)$row['cycle_id'] : 0;
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
