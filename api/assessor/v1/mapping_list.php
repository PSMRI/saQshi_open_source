<?php

/*! SaQshi Open Source | Assessor Facility Mapping List API | mapping_list.php | Version 1.0.0 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/AssessorService.php';

Security::requireMethod('GET');

try {
    Response::success('Mappings loaded', [
        'rows' => (new AssessorService($con))->listMappings($_GET)
    ]);
} catch (InvalidArgumentException $e) {
    Response::validation(['assessor_id' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
