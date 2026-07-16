<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Dashboard Service
 * DashboardService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * DashboardService.php
 * -------------------------------------------------------
 * Performance dashboard service facade.
 * -------------------------------------------------------
 */

require_once __DIR__ . '/PerformanceService.php';

/**
 * Provides dashboard service behavior for SaQshi API workflows.
 */
class DashboardService
{
    /**
     * Handles dashboard processing for this API workflow.
     */
    public static function dashboard(mysqli $con, int $facilityId, array $filters = []): array
    {
        return PerformanceService::dashboard($con, $facilityId, $filters);
    }

    /**
     * Handles summary processing for this API workflow.
     */
    public static function summary(mysqli $con, int $facilityId): array
    {
        return PerformanceService::summary($con, $facilityId);
    }
}
