<?php

/**
 * action_plan_save.php
 * -------------------------------------------------------
 * Save or update user action plan for gap checkpoint.
 *
 * Method:
 * POST
 *
 * Body:
 * {
 *   "assessment_id": 1,
 *   "dept_id": 25,
 *   "checkpoint_id": 21070,
 *   "system_action_plan": "Suggested plan from JSON",
 *   "user_action_plan": "User custom plan",
 *   "achievability": "ACHIEVABLE",
 *   "responsible_person": "Dr. Amit",
 *   "priority": "HIGH",
 *   "target_date": "2026-07-25",
 *   "status": "OPEN"
 * }
 * -------------------------------------------------------
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('POST');

try {

    $request = Security::jsonInput();

    $facId  = SessionManager::facilityId();
    $userId = SessionManager::userId();

    if ($facId <= 0) {
        Response::error('Facility not assigned to logged-in user');
    }

    if ($userId <= 0) {
        Response::error('User session not found');
    }

    $assessmentId = isset($request['assessment_id']) ? (int)$request['assessment_id'] : 0;
    $deptId = isset($request['dept_id']) ? (int)$request['dept_id'] : 0;
    $checkpointId = isset($request['checkpoint_id']) ? (int)$request['checkpoint_id'] : 0;

    $systemActionPlan = trim((string)($request['system_action_plan'] ?? ''));
    $userActionPlan = trim((string)($request['user_action_plan'] ?? ''));

    $achievability = strtoupper(trim((string)($request['achievability'] ?? 'ACHIEVABLE')));
    $responsiblePerson = trim((string)($request['responsible_person'] ?? ''));
    $priority = strtoupper(trim((string)($request['priority'] ?? 'MEDIUM')));
    $targetDate = trim((string)($request['target_date'] ?? ''));
    $status = strtoupper(trim((string)($request['status'] ?? 'OPEN')));

    if ($assessmentId <= 0) {
        Response::validation(['assessment_id' => 'assessment_id is required']);
    }

    if ($deptId <= 0) {
        Response::validation(['dept_id' => 'dept_id is required']);
    }

    if ($checkpointId <= 0) {
        Response::validation(['checkpoint_id' => 'checkpoint_id is required']);
    }

    $allowedAchievability = ['ACHIEVABLE', 'NON_ACHIEVABLE'];

    if (!in_array($achievability, $allowedAchievability, true)) {
        Response::validation([
            'achievability' => 'Invalid achievability'
        ]);
    }

    $allowedPriority = ['LOW', 'MEDIUM', 'HIGH'];

    if (!in_array($priority, $allowedPriority, true)) {
        Response::validation([
            'priority' => 'Invalid priority'
        ]);
    }

    $allowedStatus = ['OPEN', 'IN_PROGRESS', 'COMPLETED'];

    if (!in_array($status, $allowedStatus, true)) {
        Response::validation([
            'status' => 'Invalid status'
        ]);
    }

    if ($achievability === 'ACHIEVABLE') {

        if ($responsiblePerson === '') {
            Response::validation([
                'responsible_person' => 'responsible_person is required for achievable action plan'
            ]);
        }

        if ($targetDate === '') {
            Response::validation([
                'target_date' => 'target_date is required for achievable action plan'
            ]);
        }

        if (!strtotime($targetDate)) {
            Response::validation([
                'target_date' => 'Invalid target_date'
            ]);
        }

    } else {

        $responsiblePerson = null;
        $targetDate = null;
        $priority = 'LOW';
    }

    /*
     * 1. Validate assessment belongs to facility
     */
    $sqlAssessment = "
        SELECT assessment_id, status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlAssessment);

    if (!$stmt) {
        Response::serverError('Assessment prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();

    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Assessment not found for this facility');
    }

    /*
     * 2. Validate department activated
     */
    $sqlDept = "
        SELECT assessment_dept_id AS id
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND dept_id = ?
          AND is_active = 1
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlDept);

    if (!$stmt) {
        Response::serverError('Department prepare failed: ' . $con->error);
    }

    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();

    $dept = $stmt->get_result()->fetch_assoc();

    if (!$dept) {
        Response::error('Department is not activated for this assessment');
    }

    /*
     * 3. Validate gap response exists score 0 or 1
     */
    $cycleId = $assessmentId;

    $sqlGap = "
        SELECT response_id, score
        FROM assessment_response
        WHERE assessment_id = ?
          AND dept_id = ?
          AND checkpoint_id = ?
          AND score < 2
        LIMIT 1
    ";

    $stmt = $con->prepare($sqlGap);

    if (!$stmt) {
        Response::serverError('Gap response prepare failed: ' . $con->error);
    }

    $stmt->bind_param(
        'iii',
        $cycleId,
        $deptId,
        $checkpointId
    );

    $stmt->execute();

    $gap = $stmt->get_result()->fetch_assoc();

    if (!$gap) {
        Response::error('Action plan can be created only for score 0 or 1 checkpoints');
    }

    /*
     * 4. Save / update action plan
     */
    $sqlSave = "
        INSERT INTO assessment_action_plan
            (
                assessment_id,
                dept_id,
                checkpoint_id,
                system_action_plan,
                user_action_plan,
                achievability,
                responsible_person,
                priority,
                target_date,
                status,
                created_by,
                updated_by
            )
        VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ON DUPLICATE KEY UPDATE
            system_action_plan = VALUES(system_action_plan),
            user_action_plan = VALUES(user_action_plan),
            achievability = VALUES(achievability),
            responsible_person = VALUES(responsible_person),
            priority = VALUES(priority),
            target_date = VALUES(target_date),
            status = VALUES(status),
            updated_by = VALUES(updated_by),
            updated_on = CURRENT_TIMESTAMP
    ";

    $stmt = $con->prepare($sqlSave);

    if (!$stmt) {
        Response::serverError('Action plan save prepare failed: ' . $con->error);
    }

        $stmt->bind_param(
        'iiisssssssii',
        $assessmentId,
        $deptId,
        $checkpointId,
        $systemActionPlan,
        $userActionPlan,
        $achievability,
        $responsiblePerson,
        $priority,
        $targetDate,
        $status,
        $userId,
        $userId
    );
    if (!$stmt->execute()) {
        Response::serverError('Action plan save failed: ' . $stmt->error);
    }

    /*
     * 5. Store reusable facility suggestion for future assessments.
     */
    $sqlLibrary = "
        CREATE TABLE IF NOT EXISTS assessment_action_plan_library (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            checkpoint_id INT NOT NULL,
            framework_code VARCHAR(100) NULL,
            fac_id INT NOT NULL,
            fac_name VARCHAR(255) NULL,
            source_assessment_id BIGINT NOT NULL,
            source_dept_id INT NOT NULL,
            user_action_plan TEXT NOT NULL,
            created_by INT NULL,
            created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_checkpoint (checkpoint_id),
            INDEX idx_fac_checkpoint (fac_id, checkpoint_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$con->query($sqlLibrary)) {
        Response::serverError('Action plan library prepare failed: ' . $con->error);
    }

    $frameworkCode = '';
    $facName = '';

    $stmtMeta = $con->prepare("
        SELECT assessment_name, framework_code
        FROM assessment_master
        WHERE assessment_id = ?
        LIMIT 1
    ");

    if ($stmtMeta) {
        $stmtMeta->bind_param('i', $assessmentId);
        $stmtMeta->execute();
        $meta = $stmtMeta->get_result()->fetch_assoc();
        $frameworkCode = (string)($meta['framework_code'] ?? '');
    }

    $facilityJsonPath = __DIR__ . '/../../config/masters/facilities.json';

    if (file_exists($facilityJsonPath)) {
        $states = json_decode(file_get_contents($facilityJsonPath), true);

        if (is_array($states)) {
            foreach ($states as $state) {
                foreach (($state['divisions'] ?? []) as $division) {
                    foreach (($division['districts'] ?? []) as $district) {
                        foreach (($district['blocks'] ?? []) as $block) {
                            foreach (($block['facilities'] ?? []) as $facility) {
                                if ((int)($facility['fac_id'] ?? 0) === $facId) {
                                    $facName = (string)($facility['fac_name'] ?? '');
                                    break 5;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($userActionPlan !== '') {
        $stmtLibrary = $con->prepare("
            INSERT INTO assessment_action_plan_library
                (
                    checkpoint_id,
                    framework_code,
                    fac_id,
                    fac_name,
                    source_assessment_id,
                    source_dept_id,
                    user_action_plan,
                    created_by
                )
            SELECT ?, ?, ?, ?, ?, ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1
                FROM assessment_action_plan_library
                WHERE checkpoint_id = ?
                  AND fac_id = ?
                  AND user_action_plan = ?
                LIMIT 1
            )
        ");

        if (!$stmtLibrary) {
            Response::serverError('Action plan library save prepare failed: ' . $con->error);
        }

        $stmtLibrary->bind_param(
            'isisiisiiis',
            $checkpointId,
            $frameworkCode,
            $facId,
            $facName,
            $assessmentId,
            $deptId,
            $userActionPlan,
            $userId,
            $checkpointId,
            $facId,
            $userActionPlan
        );

        if (!$stmtLibrary->execute()) {
            Response::serverError('Action plan library save failed: ' . $stmtLibrary->error);
        }
    }

    Event::dispatch('gap.action_plan.saved', [
        'assessment_id' => $assessmentId,
        'dept_id' => $deptId,
        'checkpoint_id' => $checkpointId,
        'fac_id' => $facId,
        'score' => (float)$gap['score'],
        'achievability' => $achievability,
        'priority' => $priority,
        'status' => $status,
        'updated_by' => $userId
    ]);

    Response::success(
        'Action plan saved successfully',
        [
            'assessment_id' => $assessmentId,
            'dept_id' => $deptId,
            'checkpoint_id' => $checkpointId,
            'score' => (float)$gap['score'],
            'action_plan' => [
                'system_action_plan' => $systemActionPlan,
                'user_action_plan' => $userActionPlan,
                'achievability' => $achievability,
                'responsible_person' => $responsiblePerson,
                'priority' => $priority,
                'target_date' => $targetDate,
                'status' => $status,
                'updated_by' => $userId
            ]
        ]
    );

} catch (Throwable $e) {

    Response::serverError($e->getMessage());
}
