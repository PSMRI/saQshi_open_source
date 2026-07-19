<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/service/ResponseTypeService.php';

sqTest('response type service scores radio options from JSON', function (): void {
    $result = ResponseTypeService::evaluate(
        [
            'response' => [
                'type' => 'radio',
                'options' => [
                    ['label' => 'No', 'value' => '0', 'score' => 0],
                    ['label' => 'Yes', 'value' => '1', 'score' => 1],
                ],
            ],
        ],
        ['response_value' => '1']
    );

    sqAssertSame('radio', $result['response_type'], 'Radio type should be retained.');
    sqAssertSame('1', $result['response_value'], 'Selected response should be retained.');
    sqAssertSame(1.0, $result['score'], 'Radio score should come from option score.');
    sqAssertSame(1.0, $result['max_score'], 'Max score should come from highest option score.');
    sqAssertSame('SCORED', $result['score_status'], 'Radio response should be scored.');
});

sqTest('response type service stores number responses as not scored', function (): void {
    $result = ResponseTypeService::evaluate(
        ['response' => ['type' => 'number', 'label' => 'Teachers']],
        ['response_value' => '4']
    );

    sqAssertSame('number', $result['response_type'], 'Number type should be retained.');
    sqAssertSame('4', $result['response_value'], 'Number value should be retained.');
    sqAssertSame(0.0, $result['score'], 'Number response should not contribute score.');
    sqAssertSame(0.0, $result['max_score'], 'Number response should not contribute denominator.');
    sqAssertSame('NOT_SCORED', $result['score_status'], 'Number response should be marked not scored.');
    sqAssertSame(4.0, $result['fields'][0]['number'], 'Number response should be indexed numerically.');
});

sqTest('response type service indexes form fields', function (): void {
    $result = ResponseTypeService::evaluate(
        [
            'response' => [
                'type' => 'form',
                'fields' => [
                    ['key' => 'teacher_count', 'label' => 'Teachers', 'type' => 'number'],
                    ['key' => 'has_building', 'label' => 'Building available', 'type' => 'yes_no'],
                ],
            ],
        ],
        [
            'response_json' => [
                'fields' => [
                    'teacher_count' => '3',
                    'has_building' => 'yes',
                ],
            ],
        ]
    );

    sqAssertSame('form', $result['response_type'], 'Form type should be retained.');
    sqAssertSame('3', $result['response_value'], 'Form summary should use first non-empty field.');
    sqAssertSame('NOT_SCORED', $result['score_status'], 'Form response should be marked not scored.');
    sqAssertSame(3.0, $result['fields'][0]['number'], 'Numeric form field should be indexed.');
    sqAssertSame(1, $result['fields'][1]['bool'], 'Yes/no form field should be indexed as boolean.');
});
