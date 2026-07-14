<?php

/*! SaQshi Open Source | State Certification Status Update API | certification_update.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('POST');

try {
    $payload = Security::jsonInput();
    $updated = StateDashboardService::updateCertificationStatus($con, $payload);
    Response::success('Certification status updated successfully', $updated);
} catch (InvalidArgumentException $e) {
    Response::validation(['certification' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
