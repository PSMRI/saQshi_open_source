<?php

/**
 * Authorisation rules for facility access to assessment cycles.
 */
final class AssessmentAccess
{
    /**
     * Assessor-led cycles are read-only to facility users. Assessor sessions
     * retain their normal edit capability for the facilities assigned to them.
     */
    public static function requireEditableByCurrentUser(mysqli $con, int $assessmentId, int $facilityId): void
    {
        $stmt = $con->prepare(
            "SELECT assigned_assessor_id, assessment_source
             FROM assessment_master
             WHERE assessment_id = ? AND fac_id_fk = ?
             LIMIT 1"
        );

        if (!$stmt) {
            Response::serverError('Assessment access check could not be prepared');
        }

        $stmt->bind_param('ii', $assessmentId, $facilityId);
        $stmt->execute();
        $assessment = $stmt->get_result()->fetch_assoc();

        if (!$assessment) {
            Response::error('Assessment not found for this facility');
        }

        $isAssessorCycle = (int)($assessment['assigned_assessor_id'] ?? 0) > 0
            || strtoupper((string)($assessment['assessment_source'] ?? '')) === 'STATE_ASSESSOR';
        $role = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['user_type'] ?? ''));
        $isAssessorSession = (int)($_SESSION['assessor_id'] ?? 0) > 0 || str_contains($role, 'assessor');

        if ($isAssessorCycle && !$isAssessorSession) {
            Response::forbidden('This assessor-led assessment is read-only for facility users. View its progress or reports instead.');
        }
    }
}
