<?php

/** Save several checklist responses atomically for one assessment department. */
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../service/ResponseTypeService.php';
require_once __DIR__ . '/../../core/AssessmentAccess.php';
require_once __DIR__ . '/../../core/ApiCache.php';
require_once __DIR__ . '/../../service/AssessmentSectionAssignmentService.php';

Security::requireMethod('POST');

try {
    $request = Security::jsonInput();
    $facId = SessionManager::facilityId();
    $userId = SessionManager::userId();
    $assessmentId = (int)($request['assessment_id'] ?? 0);
    $deptId = (int)($request['dept_id'] ?? 0);
    $responses = is_array($request['responses'] ?? null) ? $request['responses'] : [];

    if ($facId <= 0 || $userId <= 0) Response::error('Facility user session is required');
    if ($assessmentId <= 0 || $deptId <= 0) Response::validation(['assessment_id' => 'assessment_id and dept_id are required']);
    if (!$responses) Response::validation(['responses' => 'At least one response is required']);
    if (count($responses) > 250) Response::validation(['responses' => 'A maximum of 250 responses can be saved at one time']);

    AssessmentAccess::requireEditableByCurrentUser($con, $assessmentId, $facId);
    AssessmentSectionAssignmentService::requireOwner($con, $assessmentId, $deptId);
    ResponseTypeService::ensureSchema($con);

    $stmt = $con->prepare("SELECT framework_code FROM assessment_master WHERE assessment_id = ? AND fac_id_fk = ? AND status = 'ACTIVE' LIMIT 1");
    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    if (!$assessment) Response::error('Active assessment not found for this facility');

    $stmt = $con->prepare("SELECT status FROM assessment_department WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();
    $department = $stmt->get_result()->fetch_assoc();
    if (!$department) Response::error('Department is not activated for this assessment');
    if (($department['status'] ?? '') === 'COMPLETED') Response::error('Department already completed. Response cannot be changed');
    if (($department['status'] ?? '') !== 'IN_PROGRESS') Response::error('Please start department assessment before saving response');

    $stmt = $con->prepare('SELECT info_id FROM assessment_assessor_info WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? LIMIT 1');
    $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) Response::error('Please save assessor information before saving response');

    $engine = FrameworkEngine::load(trim((string)($assessment['framework_code'] ?? 'saqshi-nqas')) ?: 'saqshi-nqas');
    $con->begin_transaction();
    $saved = [];
    $lastCheckpointId = 0;
    $facilityTypeId = 0;

    $save = $con->prepare("INSERT INTO assessment_response (assessment_id, dept_id, checkpoint_id, response_value, response_type, response_json, score, max_score, score_status, remarks, evidence_url, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE response_value = VALUES(response_value), response_type = VALUES(response_type), response_json = VALUES(response_json), score = VALUES(score), max_score = VALUES(max_score), score_status = VALUES(score_status), remarks = VALUES(remarks), evidence_url = VALUES(evidence_url), updated_by = VALUES(updated_by), updated_on = CURRENT_TIMESTAMP");
    if (!$save) throw new RuntimeException('Response save prepare failed: ' . $con->error);

    foreach ($responses as $row) {
        if (!is_array($row)) throw new RuntimeException('Invalid response item');
        $checkpointId = (int)($row['checkpoint_id'] ?? 0);
        $checkpoint = $engine->getCheckpointById($checkpointId) ?? [];
        if ($checkpointId <= 0 || !$checkpoint) throw new RuntimeException('Invalid checkpoint selected');
        if ((int)($checkpoint['_fac_dept_id'] ?? $deptId) !== $deptId) throw new RuntimeException('Checkpoint does not belong to the selected department');
        $calculated = ResponseTypeService::evaluate($checkpoint, $row);
        $responseValue = (string)$calculated['response_value'];
        $responseType = (string)$calculated['response_type'];
        $responseJson = (string)$calculated['response_json'];
        $score = (float)$calculated['score'];
        $maxScore = (float)$calculated['max_score'];
        $scoreStatus = (string)$calculated['score_status'];
        $remarks = trim((string)($row['remarks'] ?? ''));
        $evidenceUrl = trim((string)($row['evidence_url'] ?? ''));
        $save->bind_param('iiisssddsssi', $assessmentId, $deptId, $checkpointId, $responseValue, $responseType, $responseJson, $score, $maxScore, $scoreStatus, $remarks, $evidenceUrl, $userId);
        if (!$save->execute()) throw new RuntimeException('Response save failed: ' . $save->error);
        ResponseTypeService::replaceFieldIndex($con, $assessmentId, $deptId, $checkpointId, $userId, $calculated['fields'] ?? []);
        $saved[] = ['checkpoint_id' => $checkpointId, 'response_value' => $responseValue, 'response_json' => json_decode($responseJson, true)];
        $lastCheckpointId = $checkpointId;
        $facilityTypeId = (int)($checkpoint['_fac_type_id'] ?? $facilityTypeId);
    }

    $progress = $con->prepare('UPDATE assessment_department SET current_checkpoint_id = ? WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ?');
    $progress->bind_param('iiii', $lastCheckpointId, $assessmentId, $facId, $deptId);
    if (!$progress->execute()) throw new RuntimeException('Department progress update failed: ' . $progress->error);

    $expected = $facilityTypeId > 0 ? $engine->getCheckpoints($facilityTypeId, $deptId) : [];
    $expectedIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int)($item['csqa_id'] ?? 0), $expected))));
    $savedIds = [];
    $stmt = $con->prepare('SELECT DISTINCT checkpoint_id FROM assessment_response WHERE assessment_id = ? AND dept_id = ?');
    $stmt->bind_param('ii', $assessmentId, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($item = $result->fetch_assoc()) $savedIds[] = (int)$item['checkpoint_id'];
    $completedCount = count(array_intersect($expectedIds, $savedIds));
    $departmentCompleted = count($expectedIds) > 0 && $completedCount >= count($expectedIds);
    $assessmentCompleted = false;

    if ($departmentCompleted) {
        $stmt = $con->prepare("UPDATE assessment_department SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND is_active = 1");
        $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
        if (!$stmt->execute()) throw new RuntimeException('Department completion update failed: ' . $stmt->error);
        $stmt = $con->prepare("UPDATE assessment_section_assignee SET status = 'COMPLETED', completed_on = COALESCE(completed_on, CURRENT_TIMESTAMP) WHERE assessment_id = ? AND fac_id_fk = ? AND dept_id = ? AND status = 'IN_PROGRESS'");
        $stmt->bind_param('iii', $assessmentId, $facId, $deptId);
        if (!$stmt->execute()) throw new RuntimeException('Assignee completion update failed: ' . $stmt->error);

        $hasAssessmentId = $con->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_department_status' AND COLUMN_NAME = 'assessment_id' LIMIT 1");
        $statusColumn = ($hasAssessmentId && $hasAssessmentId->fetch_assoc()) ? 'assessment_id' : 'ass_period_id';
        $stmt = $con->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN COALESCE(ad.status, 'NOT_STARTED') = 'COMPLETED' THEN 1 ELSE 0 END) AS completed FROM assessment_department_status ads LEFT JOIN assessment_department ad ON ad.assessment_id = ads.{$statusColumn} AND ad.fac_id_fk = ads.fac_id_fk AND ad.dept_id = ads.dept_id AND ad.is_active = 1 WHERE ads.{$statusColumn} = ? AND ads.fac_id_fk = ? AND ads.is_active = 1");
        $stmt->bind_param('ii', $assessmentId, $facId);
        $stmt->execute();
        $all = $stmt->get_result()->fetch_assoc() ?: [];
        if ((int)($all['total'] ?? 0) > 0 && (int)($all['total'] ?? 0) === (int)($all['completed'] ?? 0)) {
            $stmt = $con->prepare("UPDATE assessment_master SET status = 'COMPLETED', completed_on = CURRENT_TIMESTAMP WHERE assessment_id = ? AND fac_id_fk = ? AND status = 'ACTIVE'");
            $stmt->bind_param('ii', $assessmentId, $facId);
            if (!$stmt->execute()) throw new RuntimeException('Assessment completion update failed: ' . $stmt->error);
            $assessmentCompleted = $stmt->affected_rows > 0;
        }
    }

    $con->commit();
    ApiCache::forget('assessment:list:facility:' . $facId);
    Response::success('Responses saved successfully', ['responses' => $saved, 'saved_count' => count($saved), 'lifecycle' => ['department_completed' => $departmentCompleted, 'assessment_completed' => $assessmentCompleted]]);
} catch (Throwable $e) {
    if (isset($con) && $con instanceof mysqli) { try { $con->rollback(); } catch (Throwable $ignored) {} }
    Response::serverError($e->getMessage());
}
