<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Assessment Dashboard Insights API
 * dashboard_insights.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../core/FrameworkEngine.php';
require_once __DIR__ . '/../../assets/conn/db.php';

Security::requireMethod('GET');

/**
 * Handles dashboard facility type id processing for this API workflow.
 */
function dashboardFacilityTypeId(int $facId): int
{
    $path = __DIR__ . '/../../config/masters/facilities.json';

    if ($facId <= 0 || !file_exists($path)) {
        return 0;
    }

    $states = json_decode(file_get_contents($path), true);

    foreach ((array)$states as $state) {
        foreach (($state['divisions'] ?? []) as $division) {
            foreach (($division['districts'] ?? []) as $district) {
                foreach (($district['blocks'] ?? []) as $block) {
                    foreach (($block['facilities'] ?? []) as $facility) {
                        if ((int)($facility['fac_id'] ?? 0) === $facId) {
                            return (int)($facility['fac_type_id'] ?? 0);
                        }
                    }
                }
            }
        }
    }

    return 0;
}

try {
    $assessmentId = (int)($_GET['assessment_id'] ?? 0);
    $facId = SessionManager::facilityId();

    if ($assessmentId <= 0) {
        Response::validation(['assessment_id' => 'Assessment ID is required']);
    }

    $stmt = $con->prepare("
        SELECT assessment_id, assessment_name, framework_code, fac_id_fk, status
        FROM assessment_master
        WHERE assessment_id = ?
          AND fac_id_fk = ?
        LIMIT 1
    ");

    if (!$stmt) {
        Response::serverError('Assessment prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();

    if (!$assessment) {
        Response::error('Assessment not found for this facility');
    }

    $stmt = $con->prepare("
        SELECT dept_id
        FROM assessment_department
        WHERE assessment_id = ?
          AND fac_id_fk = ?
          AND is_active = 1
        ORDER BY dept_id
    ");

    if (!$stmt) {
        Response::serverError('Department prepare failed: ' . $con->error);
    }

    $stmt->bind_param('ii', $assessmentId, $facId);
    $stmt->execute();
    $deptResult = $stmt->get_result();

    $deptIds = [];

    while ($row = $deptResult->fetch_assoc()) {
        $deptIds[] = (int)$row['dept_id'];
    }

    $stmt = $con->prepare("
        SELECT dept_id, checkpoint_id
        FROM assessment_response
        WHERE assessment_id = ?
    ");

    if (!$stmt) {
        Response::serverError('Response prepare failed: ' . $con->error);
    }

    $stmt->bind_param('i', $assessmentId);
    $stmt->execute();
    $responseResult = $stmt->get_result();

    $answered = [];

    while ($row = $responseResult->fetch_assoc()) {
        $answered[(int)$row['dept_id'] . '|' . (string)$row['checkpoint_id']] = true;
    }

    $frameworkCode = $assessment['framework_code'] ?: 'saqshi-nqas';
    $facTypeId = dashboardFacilityTypeId($facId);
    $engine = FrameworkEngine::load($frameworkCode);
    $areas = [];

    foreach ($deptIds as $deptId) {
        foreach ($engine->getCheckpoints($facTypeId, $deptId) as $checkpoint) {
            $checkpointId = (string)($checkpoint['csqa_id'] ?? '');

            if ($checkpointId === '') {
                continue;
            }

            $concernId = (int)($checkpoint['_concern_id'] ?? 0);
            $key = $deptId . '|' . $concernId;
            $name = trim((string)($checkpoint['_concern_name'] ?? ''));
            $description = trim((string)($checkpoint['_concern_des'] ?? ''));

            if (!isset($areas[$key])) {
                $areas[$key] = [
                    'dept_id' => $deptId,
                    'concern_id' => $concernId,
                    'area_name' => $name !== '' ? $name : ($description !== '' ? $description : 'Area ' . $concernId),
                    'total_checkpoints' => 0,
                    'completed_checkpoints' => 0,
                    'pending_checkpoints' => 0,
                    '_seen' => []
                ];
            }

            if (isset($areas[$key]['_seen'][$checkpointId])) {
                continue;
            }

            $areas[$key]['_seen'][$checkpointId] = true;
            $areas[$key]['total_checkpoints']++;

            if (isset($answered[$deptId . '|' . $checkpointId])) {
                $areas[$key]['completed_checkpoints']++;
            }
        }
    }

    $rows = [];

    foreach ($areas as $area) {
        unset($area['_seen']);
        $area['pending_checkpoints'] = max(
            (int)$area['total_checkpoints'] - (int)$area['completed_checkpoints'],
            0
        );
        $area['completion_percent'] = (int)$area['total_checkpoints'] > 0
            ? round(((int)$area['completed_checkpoints'] / (int)$area['total_checkpoints']) * 100, 2)
            : 0;
        $rows[] = $area;
    }

    usort($rows, fn($a, $b) => [$a['dept_id'], $a['concern_id']] <=> [$b['dept_id'], $b['concern_id']]);

    Response::success('Dashboard insights fetched successfully', [
        'assessment' => [
            'assessment_id' => (int)$assessment['assessment_id'],
            'assessment_name' => $assessment['assessment_name'],
            'framework_code' => $assessment['framework_code'],
            'status' => $assessment['status']
        ],
        'area_concerns' => $rows
    ]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
