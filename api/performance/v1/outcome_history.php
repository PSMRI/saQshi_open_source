<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Outcome History API
 * outcome_history.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/OutcomeService.php';

Security::requireMethod('GET');

try {
    Response::success('Outcome history loaded', OutcomeService::history($con, SessionManager::facilityId(), $_GET));
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
