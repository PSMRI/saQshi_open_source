<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * State API Bootstrap
 * _bootstrap.php
 * Version 1.0.0 | Updated 2026-07-10
 * ==========================================================
 */

require_once __DIR__ . '/../../auth_api.php';
require_once __DIR__ . '/../../assets/conn/db.php';
require_once __DIR__ . '/../../service/StateDashboardService.php';

StateDashboardService::requireStateRole();
$_GET = StateDashboardService::applyMonitoringScope($_GET);

// State monitoring endpoints are read-only. Release the PHP session lock so a
// slow report cannot block drill-down, map, or dashboard requests from the
// same logged-in browser session.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
