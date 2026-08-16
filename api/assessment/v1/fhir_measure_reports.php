<?php

/** Read-only FHIR R4 projection of the logged-in facility's assessment summaries. */
require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

function fhirAssessmentDate(?string $value): ?string
{
    return (!$value || strtotime($value) === false) ? null : gmdate('Y-m-d', strtotime($value));
}

function fhirAssessmentStatus(string $status): string
{
    return match (strtoupper(trim($status))) {
        'COMPLETED' => 'complete',
        'ACTIVE', 'IN_PROGRESS', 'IN PROGRESS' => 'in-progress',
        'CANCELLED', 'CLOSED' => 'cancelled',
        default => 'pending'
    };
}

try {
    $facilityId = SessionManager::facilityId();
    if ($facilityId <= 0) Response::forbidden('A facility-scoped session is required.');
    $assessmentId = isset($_GET['assessment_id']) ? max(0, (int)$_GET['assessment_id']) : 0;
    $sql = "SELECT a.assessment_id, a.assessment_name, a.framework_code, a.status, a.start_date, a.end_date, a.assessment_source,
                   COALESCE(r.answered, 0) answered, COALESCE(r.score, 0) score
            FROM assessment_master a
            LEFT JOIN (SELECT assessment_id, COUNT(*) answered, ROUND(COALESCE(SUM(score), 0), 2) score FROM assessment_response GROUP BY assessment_id) r ON r.assessment_id = a.assessment_id
            WHERE a.fac_id_fk = ?" . ($assessmentId > 0 ? ' AND a.assessment_id = ?' : '') . ' ORDER BY a.assessment_id DESC LIMIT 100';
    $stmt = $con->prepare($sql);
    if (!$stmt) throw new RuntimeException('Could not prepare FHIR assessment export.');
    if ($assessmentId > 0) $stmt->bind_param('ii', $facilityId, $assessmentId); else $stmt->bind_param('i', $facilityId);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bundle = ['resourceType' => 'Bundle', 'id' => 'saqshi-facility-' . $facilityId . '-assessment-reports', 'type' => 'searchset', 'timestamp' => gmdate('c'), 'total' => count($records), 'entry' => []];
    foreach ($records as $row) {
        $id = (int)$row['assessment_id'];
        $report = [
            'resourceType' => 'MeasureReport', 'id' => 'saqshi-assessment-' . $id,
            'meta' => ['profile' => ['http://hl7.org/fhir/StructureDefinition/MeasureReport']],
            'status' => fhirAssessmentStatus((string)$row['status']), 'type' => 'individual',
            'measure' => 'Measure/saqshi-' . rawurlencode(strtolower((string)($row['framework_code'] ?: 'assessment'))),
            'subject' => ['reference' => 'Organization/saqshi-facility-' . $facilityId], 'date' => gmdate('c'),
            'period' => array_filter(['start' => fhirAssessmentDate($row['start_date']), 'end' => fhirAssessmentDate($row['end_date'])]),
            'group' => [['code' => ['text' => 'Assessment summary'], 'population' => [['code' => ['text' => 'answered-checkpoints'], 'count' => (int)$row['answered']]], 'measureScore' => ['value' => (float)$row['score']]]],
            'extension' => [
                ['url' => 'https://saqshi.org/fhir/StructureDefinition/assessment-name', 'valueString' => (string)$row['assessment_name']],
                ['url' => 'https://saqshi.org/fhir/StructureDefinition/assessment-status', 'valueCode' => (string)$row['status']],
                ['url' => 'https://saqshi.org/fhir/StructureDefinition/assessment-source', 'valueCode' => (string)($row['assessment_source'] ?: 'UNSPECIFIED')]
            ]
        ];
        $bundle['entry'][] = ['fullUrl' => 'MeasureReport/' . $report['id'], 'resource' => $report];
    }
    header('Content-Type: application/fhir+json; charset=utf-8');
    echo json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    Response::serverError('FHIR assessment export failed: ' . $e->getMessage());
}
