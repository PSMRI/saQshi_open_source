<?php

require_once __DIR__ . '/../core/FrameworkEngine.php';

/** Coordinates exclusive assessor ownership of an assessment section/class. */
final class AssessmentSectionAssignmentService
{
    public static function ensureSchema(mysqli $con): void
    {
        $con->query("CREATE TABLE IF NOT EXISTS assessment_section_assignee (
            assignment_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            assessment_id BIGINT NOT NULL,
            fac_id_fk INT NOT NULL,
            dept_id INT NOT NULL,
            assessor_id BIGINT NOT NULL,
            assessment_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
            assigned_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            completed_on TIMESTAMP NULL DEFAULT NULL,
            released_on TIMESTAMP NULL DEFAULT NULL,
            active_assessor_claim_key TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE NULL END) STORED,
            UNIQUE KEY uq_assessment_section (assessment_id, dept_id),
            UNIQUE KEY uq_active_facility_assessor_claim (fac_id_fk, assessor_id, active_assessor_claim_key),
            KEY idx_section_assessor (assessor_id, status)
        )");

    }

    public static function claim(mysqli $con, int $assessmentId, int $facilityId, int $deptId, int $assessorId, string $date): array
    {
        self::ensureSchema($con);
        $date = $date !== '' ? $date : date('Y-m-d');
        self::enforceEducationReassessmentInterval($con, $facilityId, $deptId, $assessorId);
        $completed = $con->prepare("SELECT 1 FROM assessment_department WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND is_active = 1 AND status = 'COMPLETED' LIMIT 1");
        if ($completed) {
            $completed->bind_param('iii', $assessmentId, $facilityId, $deptId);
            $completed->execute();
            if ($completed->get_result()->fetch_assoc()) {
                throw new RuntimeException('This class/department is already completed. Activate the next available one.');
            }
        }
        // A cancelled/released attempt never consumes a round. Only a completed
        // class/department is a reassessment and therefore advances its round.
        $prior = $con->prepare("SELECT 1 FROM assessment_section_assignee asa JOIN assessment_master am ON am.assessment_id = asa.assessment_id WHERE asa.fac_id_fk = ? AND asa.dept_id = ? AND asa.assessment_id <> ? AND asa.status = 'COMPLETED' AND UPPER(am.status) = 'COMPLETED' LIMIT 1");
        $prior->bind_param('iii', $facilityId, $deptId, $assessmentId);
        $prior->execute();
        $isReassessment = (bool)$prior->get_result()->fetch_assoc();
        $stmt = $con->prepare("SELECT assessor_id, status FROM assessment_section_assignee WHERE fac_id_fk = ? AND dept_id = ? AND status = 'IN_PROGRESS' LIMIT 1");
        $stmt->bind_param('ii', $facilityId, $deptId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if ($existing && (int)$existing['assessor_id'] !== $assessorId) {
            throw new RuntimeException('This section is already assigned to another assessor.');
        }
        if ($existing && strtoupper((string)$existing['status']) === 'COMPLETED') {
            throw new RuntimeException('This section is already completed.');
        }
        // An assessor works on one class at a time for a school. Other mapped
        // assessors can claim the remaining available classes.
        $assessorActiveClaim = $con->prepare("SELECT dept_id FROM assessment_section_assignee WHERE fac_id_fk = ? AND assessor_id = ? AND dept_id <> ? AND status = 'IN_PROGRESS' LIMIT 1");
        $assessorActiveClaim->bind_param('iii', $facilityId, $assessorId, $deptId);
        $assessorActiveClaim->execute();
        if ($assessorActiveClaim->get_result()->fetch_assoc()) {
            throw new RuntimeException('Complete or cancel your current class before claiming another class.');
        }
        $stmt = $con->prepare("INSERT INTO assessment_section_assignee (assessment_id, fac_id_fk, dept_id, assessor_id, assessment_date, status)
            VALUES (?, ?, ?, ?, ?, 'IN_PROGRESS') ON DUPLICATE KEY UPDATE assessor_id = VALUES(assessor_id), assessment_date = VALUES(assessment_date), status = 'IN_PROGRESS', released_on = NULL");
        $stmt->bind_param('iiiis', $assessmentId, $facilityId, $deptId, $assessorId, $date);
        try {
            $executed = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                throw new RuntimeException('This class/department is already assigned to another active assessment. Refresh the page and select an available class.');
            }
            throw $e;
        }
        if (!$executed) {
            if ((int)$stmt->errno === 1062) {
                throw new RuntimeException('This class/department is already assigned to another active assessment. Refresh the page and select an available class.');
            }
            throw new RuntimeException('Unable to claim this class/department: ' . $stmt->error);
        }
        // The class's own history determines its round: first assessment is
        // Round 1; each later reassessment moves to the next shared round.
        $roundQuery = $con->prepare("SELECT COALESCE(MAX(fr.round_no), 0) round_no FROM assessment_section_assignee asa JOIN assessment_master am ON am.assessment_id = asa.assessment_id LEFT JOIN facility_assessment_round fr ON fr.round_id = am.round_id WHERE asa.fac_id_fk = ? AND asa.dept_id = ? AND asa.assessment_id <> ? AND asa.status = 'COMPLETED' AND UPPER(am.status) = 'COMPLETED'");
        $roundQuery->bind_param('iii', $facilityId, $deptId, $assessmentId);
        $roundQuery->execute();
        $roundNo = (int)($roundQuery->get_result()->fetch_assoc()['round_no'] ?? 0) + 1;
        $round = $con->prepare("SELECT round_id FROM facility_assessment_round WHERE fac_id = ? AND round_no = ? LIMIT 1");
        $round->bind_param('ii', $facilityId, $roundNo); $round->execute();
        $roundRow = $round->get_result()->fetch_assoc();
        if (!$roundRow) { $createRound = $con->prepare("INSERT INTO facility_assessment_round (fac_id, round_no, status) VALUES (?, ?, 'OPEN')"); $createRound->bind_param('ii', $facilityId, $roundNo); $createRound->execute(); $roundId = (int)$con->insert_id; }
        else $roundId = (int)$roundRow['round_id'];
        $updateRound = $con->prepare("UPDATE assessment_master SET round_id = ? WHERE assessment_id = ?");
        $updateRound->bind_param('ii', $roundId, $assessmentId); $updateRound->execute();
        $meta = $con->prepare("SELECT framework_code FROM assessment_master WHERE assessment_id = ? LIMIT 1");
        $meta->bind_param('i', $assessmentId); $meta->execute();
        $metaRow = $meta->get_result()->fetch_assoc() ?: [];
        $unitName = self::unitName(
            (string)($metaRow['framework_code'] ?? ''),
            self::facilityTypeFromMaster($facilityId),
            $deptId
        );
        $prefix = $isReassessment ? 'Reassessment' : 'New Assessment';
        $name = $prefix . ' - ' . $unitName . ' - ' . date('d M Y');
        $rename = $con->prepare("UPDATE assessment_master SET assessment_name = ? WHERE assessment_id = ?");
        if ($rename) { $rename->bind_param('si', $name, $assessmentId); $rename->execute(); }
        return ['assessment_id' => $assessmentId, 'dept_id' => $deptId, 'assessor_id' => $assessorId, 'status' => 'IN_PROGRESS', 'is_reassessment' => $isReassessment];
    }

    /** Prevents rapid repeat assessments of the same school class by one assessor. */
    private static function enforceEducationReassessmentInterval(mysqli $con, int $facilityId, int $deptId, int $assessorId): void
    {
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? (json_decode((string)file_get_contents($domainPath), true) ?: []) : [];
        if (($domain['profile_code'] ?? $domain['domain'] ?? '') !== 'education') return;

        $days = (int)($domain['assessment_policy']['reassessment_interval_days'] ?? 0);
        if ($days <= 0) return;

        $stmt = $con->prepare("SELECT COALESCE(asa.completed_on, am.completed_on) AS completed_on
            FROM assessment_section_assignee asa
            INNER JOIN assessment_master am ON am.assessment_id = asa.assessment_id
            WHERE asa.fac_id_fk = ? AND asa.dept_id = ? AND asa.assessor_id = ?
              AND asa.status = 'COMPLETED' AND UPPER(am.status) = 'COMPLETED'
            ORDER BY COALESCE(asa.completed_on, am.completed_on) DESC LIMIT 1");
        $stmt->bind_param('iii', $facilityId, $deptId, $assessorId);
        $stmt->execute();
        $last = $stmt->get_result()->fetch_assoc();
        $completedOn = (string)($last['completed_on'] ?? '');
        if ($completedOn === '') return;

        $eligibleAt = (new DateTimeImmutable($completedOn))->modify('+' . $days . ' days');
        if ($eligibleAt > new DateTimeImmutable('now')) {
            throw new RuntimeException('This class was already assessed by you on ' . $completedOn
                . '. It can be reassessed after ' . $eligibleAt->format('d M Y H:i') . ' (' . $days . ' day interval).');
        }
    }

    /** Get the facility type from the JSON master, which is authoritative for deployments without a facilities DB table. */
    private static function facilityTypeFromMaster(int $facilityId): int
    {
        $path = __DIR__ . '/../config/masters/facilities.json';
        $master = is_file($path) ? (json_decode((string)file_get_contents($path), true) ?: []) : [];

        foreach ($master as $state) foreach (($state['divisions'] ?? []) as $division) foreach (($division['districts'] ?? []) as $district) foreach (($district['blocks'] ?? []) as $block) foreach (($block['facilities'] ?? []) as $facility) {
            if ((int)($facility['fac_id'] ?? 0) === $facilityId) {
                return (int)($facility['fac_type_id'] ?? $facility['Health_facilty_type'] ?? 0);
            }
        }

        return 0;
    }

    /** Resolve the displayed assessment unit from the same master used by the activation screen. */
    private static function unitName(string $frameworkCode, int $facilityTypeId, int $deptId): string
    {
        $fallback = 'Class/Department ' . $deptId;
        $domainPath = __DIR__ . '/../config/domain.json';
        $domain = is_file($domainPath) ? (json_decode((string)file_get_contents($domainPath), true) ?: []) : [];
        if (($domain['profile_code'] ?? $domain['domain'] ?? '') === 'education') {
            $masterPath = __DIR__ . '/../config/masters/department.json';
            $master = is_file($masterPath) ? (json_decode((string)file_get_contents($masterPath), true) ?: []) : [];
            foreach (($master['education']['facility_types'][(string)$facilityTypeId] ?? []) as $unit) {
                if ((int)($unit['dept_id'] ?? 0) === $deptId) return (string)($unit['dept_name'] ?? $fallback);
            }
        }
        try {
            foreach (FrameworkEngine::load($frameworkCode)->getDepartments($facilityTypeId) as $unit) {
                if ((int)($unit['dept_id'] ?? $unit['fac_dept_id'] ?? 0) === $deptId) return (string)($unit['dept_name'] ?? $fallback);
            }
        } catch (Throwable $e) { }
        return $fallback;
    }

    public static function requireOwner(mysqli $con, int $assessmentId, int $deptId): void
    {
        $assessorId = (int)($_SESSION['assessor_id'] ?? 0);
        if ($assessorId <= 0) return;
        self::ensureSchema($con);
        $stmt = $con->prepare("SELECT assessor_id, status FROM assessment_section_assignee WHERE assessment_id = ? AND dept_id = ? LIMIT 1");
        $stmt->bind_param('ii', $assessmentId, $deptId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || (int)$row['assessor_id'] !== $assessorId || strtoupper((string)$row['status']) !== 'IN_PROGRESS') {
            Response::forbidden('Claim this class section before entering or updating its assessment.');
        }
    }

    public static function complete(mysqli $con, int $assessmentId, int $deptId): void
    {
        self::ensureSchema($con);
        $stmt = $con->prepare("UPDATE assessment_section_assignee SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE assessment_id = ? AND dept_id = ?");
        $stmt->bind_param('ii', $assessmentId, $deptId);
        $stmt->execute();

        // A parent assessment may have more than one active class. Close it
        // only after every active class is completed; this prevents a single
        // completed class from producing a misleading 38/76 COMPLETED row.
        $closeAssessment = $con->prepare("UPDATE assessment_master am
            SET status = 'COMPLETED', completed_on = COALESCE(completed_on, CURRENT_TIMESTAMP)
            WHERE am.assessment_id = ?
              AND am.status = 'ACTIVE'
              AND EXISTS (
                  SELECT 1
                  FROM assessment_department ad
                  WHERE ad.assessment_id = am.assessment_id
                    AND ad.is_active = 1
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM assessment_department ad
                  WHERE ad.assessment_id = am.assessment_id
                    AND ad.is_active = 1
                    AND UPPER(COALESCE(ad.status, '')) <> 'COMPLETED'
              )");
        $closeAssessment->bind_param('i', $assessmentId);
        $closeAssessment->execute();

        $round = $con->prepare("SELECT round_id, fac_id_fk, framework_code FROM assessment_master WHERE assessment_id = ? LIMIT 1");
        $round->bind_param('i', $assessmentId); $round->execute();
        $assessment = $round->get_result()->fetch_assoc() ?: [];
        $roundId = (int)($assessment['round_id'] ?? 0);
        if ($roundId > 0) {
            $facility = $con->prepare("SELECT Health_facilty_type FROM facilities WHERE fac_id = ? LIMIT 1");
            $facility->bind_param('i', $assessment['fac_id_fk']); $facility->execute();
            $typeId = (int)(($facility->get_result()->fetch_assoc() ?: [])['Health_facilty_type'] ?? 0);
            $expected = 0;
            try { $expected = count(FrameworkEngine::load((string)$assessment['framework_code'])->getDepartments($typeId)); } catch (Throwable $e) { }
            $completed = $con->prepare("SELECT COUNT(DISTINCT asa.dept_id) total FROM assessment_section_assignee asa JOIN assessment_master am ON am.assessment_id = asa.assessment_id WHERE am.round_id = ? AND asa.status = 'COMPLETED'");
            $completed->bind_param('i', $roundId); $completed->execute();
            if ($expected > 0 && (int)(($completed->get_result()->fetch_assoc() ?: [])['total'] ?? 0) >= $expected) {
                $close = $con->prepare("UPDATE facility_assessment_round SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE round_id = ?");
                $close->bind_param('i', $roundId); $close->execute();
            }
        }
    }
}
