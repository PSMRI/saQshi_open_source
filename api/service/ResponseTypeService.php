<?php

/**
 * SaQshi configurable checkpoint response handler.
 *
 * This keeps the checklist engine domain-neutral. Healthcare can keep the
 * default 0/1/2 scoring, while education or other domains can use yes/no,
 * dropdown, number, text or multi-field responses from framework JSON.
 */
class ResponseTypeService
{
    private const DEFAULT_OPTIONS = [
        ['label' => 'Non Compliance', 'value' => '0', 'score' => 0],
        ['label' => 'Partial Compliance', 'value' => '1', 'score' => 1],
        ['label' => 'Fully Compliance', 'value' => '2', 'score' => 2],
    ];

    public static function ensureSchema(mysqli $con): void
    {
        self::ensureColumn($con, 'assessment_response', 'response_type', "VARCHAR(50) NULL AFTER response_value");
        self::ensureColumn($con, 'assessment_response', 'response_json', "LONGTEXT NULL AFTER response_type");
        self::ensureColumn($con, 'assessment_response', 'max_score', "DECIMAL(10,2) NULL DEFAULT 2.00 AFTER score");
        self::ensureColumn($con, 'assessment_response', 'score_status', "VARCHAR(30) NULL DEFAULT 'SCORED' AFTER max_score");

        $con->query("
            CREATE TABLE IF NOT EXISTS assessment_response_field_index (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                assessment_id BIGINT NOT NULL,
                dept_id INT NOT NULL,
                checkpoint_id INT NOT NULL,
                field_key VARCHAR(120) NOT NULL,
                field_label VARCHAR(255) NULL,
                field_type VARCHAR(50) NULL,
                value_text LONGTEXT NULL,
                value_number DECIMAL(18,4) NULL,
                value_date DATE NULL,
                value_bool TINYINT NULL,
                updated_by INT NULL,
                updated_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_assessment_response_field (
                    assessment_id,
                    dept_id,
                    checkpoint_id,
                    field_key
                ),
                KEY idx_response_field_key (field_key),
                KEY idx_response_field_number (field_key, value_number),
                KEY idx_response_field_bool (field_key, value_bool)
            )
        ");

        $con->query("
            CREATE TABLE IF NOT EXISTS assessment_response_evidence (
                evidence_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                assessment_id BIGINT NOT NULL,
                dept_id INT NOT NULL,
                checkpoint_id INT NOT NULL,
                field_key VARCHAR(120) NULL,
                file_url VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NULL,
                file_type VARCHAR(120) NULL,
                uploaded_by INT NULL,
                uploaded_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_response_evidence (
                    assessment_id,
                    dept_id,
                    checkpoint_id
                )
            )
        ");
    }

    public static function evaluate(array $checkpoint, array $request): array
    {
        $definition = self::normalizeDefinition($checkpoint);
        $type = $definition['type'];
        $mandatory = (bool)($definition['mandatory'] ?? true);
        $payload = self::payload($request);
        $responseValue = trim((string)($request['response_value'] ?? ($payload['value'] ?? '')));
        $scoreStatus = 'SCORED';
        $score = null;
        $maxScore = 0.0;
        $indexFields = [];

        if (in_array($type, ['radio', 'yes_no', 'dropdown'], true)) {
            $options = self::options($definition, $type);

            if ($mandatory && $responseValue === '') {
                Response::validation(['response_value' => 'Response is required']);
            }

            $matched = self::matchOption($options, $responseValue);

            if (!$matched && $responseValue !== '') {
                Response::validation(['response_value' => 'Selected response is not valid for this checkpoint']);
            }

            $score = $matched ? (float)($matched['score'] ?? 0) : null;
            $maxScore = self::maxOptionScore($options);
            $payload = [
                'value' => $responseValue,
                'label' => $matched['label'] ?? $responseValue,
            ];

            $indexFields[] = self::field('value', 'Response', $type, $responseValue);
        } elseif ($type === 'number') {
            $numberValue = $responseValue !== '' ? $responseValue : ($payload['number'] ?? '');

            if ($mandatory && $numberValue === '') {
                Response::validation(['response_value' => 'Number value is required']);
            }

            if ($numberValue !== '' && !is_numeric($numberValue)) {
                Response::validation(['response_value' => 'Number value must be numeric']);
            }

            $responseValue = (string)$numberValue;
            $payload = ['value' => $numberValue];
            $scoreStatus = 'NOT_SCORED';
            $indexFields[] = self::field('value', $definition['label'] ?? 'Value', 'number', $numberValue);
        } elseif ($type === 'text') {
            if ($mandatory && $responseValue === '') {
                Response::validation(['response_value' => 'Text response is required']);
            }

            $payload = ['value' => $responseValue];
            $scoreStatus = 'NOT_SCORED';
            $indexFields[] = self::field('value', $definition['label'] ?? 'Text', 'text', $responseValue);
        } elseif ($type === 'form') {
            $fields = self::formFields($definition);
            $values = is_array($payload['fields'] ?? null) ? $payload['fields'] : $payload;

            foreach ($fields as $field) {
                $key = trim((string)($field['key'] ?? ''));

                if ($key === '') {
                    continue;
                }

                $value = $values[$key] ?? '';

                if (!empty($field['required']) && trim((string)$value) === '') {
                    Response::validation([$key => ($field['label'] ?? $key) . ' is required']);
                }

                $indexFields[] = self::field(
                    $key,
                    $field['label'] ?? $key,
                    strtolower((string)($field['type'] ?? 'text')),
                    $value
                );
            }

            $payload = ['fields' => $values];
            $responseValue = self::summaryValue($fields, $values);
            $scoreStatus = 'NOT_SCORED';
        } else {
            Response::validation(['response_type' => 'Unsupported response type: ' . $type]);
        }

        if ($scoreStatus === 'NOT_SCORED') {
            $score = 0.0;
            $maxScore = 0.0;
        }

        return [
            'response_type' => $type,
            'response_value' => $responseValue,
            'response_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'score' => $score,
            'max_score' => $maxScore,
            'score_status' => $scoreStatus,
            'fields' => $indexFields,
        ];
    }

    public static function replaceFieldIndex(
        mysqli $con,
        int $assessmentId,
        int $deptId,
        int $checkpointId,
        int $userId,
        array $fields
    ): void {
        $delete = $con->prepare("
            DELETE FROM assessment_response_field_index
            WHERE assessment_id = ?
              AND dept_id = ?
              AND checkpoint_id = ?
        ");

        if (!$delete) {
            return;
        }

        $delete->bind_param('iii', $assessmentId, $deptId, $checkpointId);
        $delete->execute();

        if (!$fields) {
            return;
        }

        $insert = $con->prepare("
            INSERT INTO assessment_response_field_index (
                assessment_id,
                dept_id,
                checkpoint_id,
                field_key,
                field_label,
                field_type,
                value_text,
                value_number,
                value_date,
                value_bool,
                updated_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insert) {
            return;
        }

        foreach ($fields as $field) {
            $key = (string)($field['key'] ?? '');
            $label = (string)($field['label'] ?? $key);
            $type = (string)($field['type'] ?? 'text');
            $text = $field['text'] ?? null;
            $number = $field['number'] ?? null;
            $date = $field['date'] ?? null;
            $bool = $field['bool'] ?? null;

            $insert->bind_param(
                'iiissssdsii',
                $assessmentId,
                $deptId,
                $checkpointId,
                $key,
                $label,
                $type,
                $text,
                $number,
                $date,
                $bool,
                $userId
            );
            $insert->execute();
        }
    }

    private static function ensureColumn(mysqli $con, string $table, string $column, string $definition): void
    {
        $stmt = $con->prepare("
            SELECT COUNT(*) AS count_found
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ((int)($row['count_found'] ?? 0) > 0) {
            return;
        }

        $con->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }

    private static function normalizeDefinition(array $checkpoint): array
    {
        $definition = $checkpoint['response'] ?? [];

        if (!is_array($definition)) {
            $definition = [];
        }

        $definition['type'] = strtolower(trim((string)($definition['type'] ?? 'radio')));

        return $definition;
    }

    private static function payload(array $request): array
    {
        $payload = $request['response_json'] ?? [];

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private static function options(array $definition, string $type): array
    {
        $options = $definition['options'] ?? [];

        if (is_array($options) && $options) {
            return $options;
        }

        if ($type === 'yes_no') {
            return [
                ['label' => 'No', 'value' => '0', 'score' => 0],
                ['label' => 'Yes', 'value' => '1', 'score' => 1],
            ];
        }

        return self::DEFAULT_OPTIONS;
    }

    private static function matchOption(array $options, string $value): ?array
    {
        foreach ($options as $option) {
            if ((string)($option['value'] ?? '') === $value) {
                return $option;
            }
        }

        return null;
    }

    private static function maxOptionScore(array $options): float
    {
        $scores = array_map(static fn(array $option): float => (float)($option['score'] ?? 0), $options);
        $max = $scores ? max($scores) : 0;

        return $max > 0 ? (float)$max : 0.0;
    }

    private static function formFields(array $definition): array
    {
        $fields = $definition['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    private static function summaryValue(array $fields, array $values): string
    {
        foreach ($fields as $field) {
            $key = (string)($field['key'] ?? '');

            if ($key !== '' && isset($values[$key]) && trim((string)$values[$key]) !== '') {
                return trim((string)$values[$key]);
            }
        }

        return '';
    }

    private static function field(string $key, string $label, string $type, mixed $value): array
    {
        $text = trim((string)$value);
        $number = is_numeric($value) ? (float)$value : null;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
        $bool = null;

        if (in_array(strtolower($text), ['1', 'yes', 'true'], true)) {
            $bool = 1;
        } elseif (in_array(strtolower($text), ['0', 'no', 'false'], true)) {
            $bool = 0;
        }

        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'text' => $text,
            'number' => $number,
            'date' => $date,
            'bool' => $bool,
        ];
    }
}
