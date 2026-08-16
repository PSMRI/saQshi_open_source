<?php

/*! SaQshi Open Source | Assessor Facility Mapping Save API | mapping_save.php | Version 1.0.0 */

require_once __DIR__ . '/_management.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireAnyMethod(['POST', 'PATCH']);

try {
    $payload = Security::jsonInput();
    $service = new AssessorService($con);
    $data = isset($payload['fac_ids'])
        ? $service->saveMappings($payload, SessionManager::userId())
        : $service->saveMapping($payload, SessionManager::userId());

    foreach ($data['mappings'] ?? [$data] as $mapping) {
        Event::dispatch('assessor.facility_mapped', [
            'assessor_id' => $mapping['assessor_id'] ?? null,
            'fac_id' => $mapping['fac_id'] ?? null,
            'mapped_by' => SessionManager::userId()
        ]);
    }

    Response::success('Facility mapping saved', $data);
} catch (InvalidArgumentException $e) {
    Response::validation(['mapping' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
