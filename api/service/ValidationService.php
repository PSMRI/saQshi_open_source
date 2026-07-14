<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Validation Service
 * ValidationService.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * ValidationService.php
 * -------------------------------------------------------
 * Performance indicator validation helper.
 * -------------------------------------------------------
 */

class ValidationService
{
    public static function validateEntry(array $payload): array
    {
        $errors = [];
        $month = (int)($payload['month'] ?? 0);
        $year = (int)($payload['year'] ?? 0);
        $indicatorId = (int)($payload['indicator_id'] ?? 0);
        $denominator = (float)($payload['denominator'] ?? $payload['denominator_value'] ?? 0);
        $denominatorNA = !empty($payload['denominator_na']) || !empty($payload['denominator_not_applicable']);

        if ($indicatorId <= 0) {
            $errors['indicator_id'] = 'Indicator is required';
        }

        if ($month < 1 || $month > 12) {
            $errors['month'] = 'Valid month is required';
        }

        if ($year < 2000 || $year > 2100) {
            $errors['year'] = 'Valid year is required';
        }

        if (!$denominatorNA && $denominator == 0.0) {
            $errors['denominator'] = 'Denominator cannot be zero';
        }

        return $errors;
    }
}
