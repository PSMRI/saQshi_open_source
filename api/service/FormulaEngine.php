<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Performance Formula Engine
 * FormulaEngine.php
 * Version 1.0.0 | Updated 2026-07-06
 * ==========================================================
 */

/**
 * FormulaEngine.php
 * -------------------------------------------------------
 * Formula helper for Performance Monitoring.
 * -------------------------------------------------------
 */

class FormulaEngine
{
    private static ?array $formulaCache = null;

    /**
     * Handles config path processing for this API workflow.
     */
    public static function configPath(): string
    {
        return __DIR__ . '/../config/performance/formula.json';
    }

    /**
     * Handles formulas processing for this API workflow.
     */
    public static function formulas(): array
    {
        if (self::$formulaCache !== null) {
            return self::$formulaCache;
        }

        $path = self::configPath();

        if (!file_exists($path)) {
            self::$formulaCache = [];
            return self::$formulaCache;
        }

        $data = json_decode(file_get_contents($path), true);
        self::$formulaCache = is_array($data) ? ($data['formulas'] ?? $data) : [];

        return self::$formulaCache;
    }

    /**
     * Handles find formula processing for this API workflow.
     */
    public static function findFormula(int|string|null $formulaId): ?array
    {
        $formulaId = (int)$formulaId;

        if ($formulaId <= 0) {
            return null;
        }

        foreach (self::formulas() as $formula) {
            if ((int)($formula['formula_id'] ?? 0) === $formulaId) {
                return $formula;
            }
        }

        return null;
    }

    /**
     * Handles calculate processing for this API workflow.
     */
    public static function calculate(
        float $numerator,
        float $denominator,
        int|string|null $formulaId = null,
        string $fallbackExpression = '(N/D)*100'
    ): ?float {
        $formula = self::findFormula($formulaId);
        $expression = (string)($formula['expression'] ?? $fallbackExpression);
        $precision = (int)($formula['precision'] ?? 2);
        $zeroAllowed = (bool)($formula['denominator_zero_allowed'] ?? false);

        $normalized = strtoupper(str_replace(' ', '', $expression));

        if ($normalized === '0') {
            return 0.0;
        }

        if (preg_match('/^\(*N\)*$/', $normalized)) {
            return round($numerator, $precision);
        }

        if ($denominator == 0.0) {
            return 0.0;
        }

        if (str_contains($normalized, '(N-D)')) {
            return round((($numerator - $denominator) / $denominator) * 100, $precision);
        }

        if (str_contains($normalized, '(D-N)')) {
            return round((($denominator - $numerator) / $denominator) * 100, $precision);
        }

        if (preg_match('/\*([0-9]+(?:\.[0-9]+)?)/', $normalized, $match)) {
            return round(($numerator / $denominator) * (float)$match[1], $precision);
        }

        return round($numerator / $denominator, $precision);
    }
}
