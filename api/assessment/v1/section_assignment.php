<?php
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessmentSectionAssignmentService.php';
require_once __DIR__ . '/../../service/DepartmentStatusService.php';
if (!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')), ['GET', 'POST'], true)) {
    Response::error('Method not allowed', null, 405);
}
try {
    $facId = SessionManager::facilityId();
    $assessorId = (int)($_SESSION['assessor_id'] ?? 0);
    if ($facId <= 0 || $assessorId <= 0) Response::forbidden('An assessor session is required.');
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? Security::jsonInput() : $_GET;
    $assessmentId = (int)($input['assessment_id'] ?? 0);
    if ($assessmentId <= 0) Response::validation(['assessment_id' => 'Assessment ID is required.']);
    AssessmentSectionAssignmentService::ensureSchema($con);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Class locks apply across every active assessor assessment for this
        // school, not only the assessment currently open in this session.
        $stmt = $con->prepare("SELECT dept_id, assessor_id, assessment_date, status, completed_on FROM assessment_section_assignee WHERE fac_id_fk = ? AND (status = 'IN_PROGRESS' OR (assessment_id = ? AND assessor_id = ? AND status = 'COMPLETED'))");
        $stmt->bind_param('iii', $facId, $assessmentId, $assessorId); $stmt->execute();
        Response::success('Section assignments loaded', ['assessor_id' => $assessorId, 'assignments' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }
    $deptId = (int)($input['dept_id'] ?? 0);
    if ($deptId <= 0) Response::validation(['dept_id' => 'Class section is required.']);
    $result = AssessmentSectionAssignmentService::claim($con, $assessmentId, $facId, $deptId, $assessorId, (string)($input['assessment_date'] ?? ''));
    (new DepartmentStatusService($con))->saveStatus(['fac_id'=>$facId,'ass_period'=>$assessmentId,'dept_id'=>$deptId,'is_active'=>1,'user_id'=>SessionManager::userId()]);
    // A newly created assessor record is only a pending class-selection
    // draft. It becomes active at the point a class is successfully claimed.
    $activate = $con->prepare("UPDATE assessment_master SET status = 'ACTIVE' WHERE assessment_id = ? AND fac_id_fk = ? AND assigned_assessor_id = ? AND status = 'PENDING'");
    $activate->bind_param('iii', $assessmentId, $facId, $assessorId);
    $activate->execute();
    Response::success('Class section assigned to you', $result);
} catch (Throwable $e) { Response::serverError($e->getMessage()); }
