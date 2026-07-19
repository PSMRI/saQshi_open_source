<?php

/**
 * SaQshi score calculation helper.
 *
 * Keep score math in a pure helper so reports, dashboards and tests can reuse
 * the same calculation rules without loading endpoint/session/database code.
 */

declare(strict_types=1);

class ScoreCalculator
{
    public static function percentage(float|int $obtainedScore, float|int $totalScore, int $precision = 2): float
    {
        $total = (float) $totalScore;

        if ($total <= 0) {
            return 0.0;
        }

        return round(((float) $obtainedScore / $total) * 100, $precision);
    }

    public static function checkpointMaxScore(array $checkpoint, float $defaultScore = 2.0): float
    {
        $response = $checkpoint['response'] ?? [];
        $responseType = strtolower((string)($response['type'] ?? 'radio'));
        $scoreMode = strtolower((string)($response['score_mode'] ?? ''));

        if (in_array($responseType, ['number', 'text', 'form'], true) && $scoreMode !== 'fixed') {
            return 0.0;
        }

        $options = $checkpoint['response']['options'] ?? [];

        if (!is_array($options) || $options === []) {
            return $defaultScore;
        }

        $scores = array_map(
            static fn ($option): float => (float) ($option['score'] ?? 0),
            $options
        );

        $max = max($scores);

        return $max > 0 ? (float) $max : $defaultScore;
    }

    public static function totalCheckpointScore(array $checkpoints, float $defaultScore = 2.0): float
    {
        $seen = [];
        $total = 0.0;

        foreach ($checkpoints as $checkpoint) {
            $checkpointId = (string) ($checkpoint['csqa_id'] ?? '');

            if ($checkpointId === '' || isset($seen[$checkpointId])) {
                continue;
            }

            $seen[$checkpointId] = true;
            $total += self::checkpointMaxScore($checkpoint, $defaultScore);
        }

        return $total;
    }
}
