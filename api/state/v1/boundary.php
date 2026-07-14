<?php

/*! SaQshi Open Source | State Map Boundary API | boundary.php | Version 1.0.0 */

require_once __DIR__ . '/_bootstrap.php';

Security::requireMethod('GET');

try {
    if (!headers_sent()) {
        header('Cache-Control: public, max-age=86400');
    }

    Response::success('Map boundary loaded', StateDashboardService::mapBoundary($_GET['state'] ?? ''));
} catch (InvalidArgumentException $e) {
    Response::validation(['state' => $e->getMessage()]);
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
