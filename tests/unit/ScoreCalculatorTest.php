<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/core/ScoreCalculator.php';

sqTest('ScoreCalculator calculates percentage', function (): void {
    sqAssertSame(50.0, ScoreCalculator::percentage(5, 10), 'Five out of ten should be fifty percent.');
    sqAssertSame(0.0, ScoreCalculator::percentage(5, 0), 'Zero total should return zero percent.');
});

sqTest('ScoreCalculator finds checkpoint max score', function (): void {
    $checkpoint = [
        'response' => [
            'options' => [
                ['score' => 0],
                ['score' => 1],
                ['score' => 2],
            ],
        ],
    ];

    sqAssertSame(2.0, ScoreCalculator::checkpointMaxScore($checkpoint), 'Max response score should be used.');
    sqAssertSame(2.0, ScoreCalculator::checkpointMaxScore([]), 'Missing options should use default score.');
});

sqTest('ScoreCalculator totals unique checkpoint scores', function (): void {
    $checkpoints = [
        ['csqa_id' => 'A1', 'response' => ['options' => [['score' => 0], ['score' => 2]]]],
        ['csqa_id' => 'A1', 'response' => ['options' => [['score' => 0], ['score' => 2]]]],
        ['csqa_id' => 'A2', 'response' => ['options' => [['score' => 0], ['score' => 1]]]],
        ['csqa_id' => '', 'response' => ['options' => [['score' => 2]]]],
    ];

    sqAssertSame(3.0, ScoreCalculator::totalCheckpointScore($checkpoints), 'Duplicate and empty checkpoint IDs should be ignored.');
});
