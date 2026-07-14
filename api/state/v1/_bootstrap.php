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
