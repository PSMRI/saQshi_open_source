<?php

require_once __DIR__ . '/CertificationExpiryService.php';

/**
 * Provides certification validator behavior for SaQshi API workflows.
 */
class CertificationValidator
{
    /**
     * Handles validate payload processing for this API workflow.
     */
    public static function validatePayload(array $payload, array $config): array
    {
        $errors = [];
        $type = strtoupper(trim((string)($payload['certification_type'] ?? $payload['cert_type'] ?? '')));
        $mode = strtoupper(trim((string)($payload['assessment_mode'] ?? $payload['ass_mod'] ?? '')));
        $status = CertificationExpiryService::normalizeStatus((string)($payload['status'] ?? $payload['Cert_status'] ?? ''));
        $date = trim((string)($payload['certification_date'] ?? $payload['date_of_ass'] ?? ''));
        $appliedDate = trim((string)($payload['applied_date'] ?? ''));
        $score = $payload['score'] ?? null;

        if (!in_array($type, $config['types'] ?? [], true)) {
            $errors['certification_type'] = 'Certification type must be STATE or NATIONAL.';
        }

        if (!in_array($mode, $config['assessment_modes'] ?? [], true)) {
            $errors['assessment_mode'] = 'Assessment mode must be PHYSICAL or VIRTUAL.';
        }

        $statuses = array_map(fn($row) => strtoupper((string)($row['code'] ?? '')), $config['statuses'] ?? []);
        if (!in_array($status, $statuses, true)) {
            $errors['status'] = 'Status must be CONDITIONAL or CERTIFIED.';
        }

        if ($date === '' || !self::isDate($date)) {
            $errors['certification_date'] = 'Certification date must be a valid YYYY-MM-DD date.';
        } elseif ($date > date('Y-m-d')) {
            $errors['certification_date'] = 'Certification date cannot be a future date.';
        }

        if ($appliedDate !== '') {
            if (!self::isDate($appliedDate)) {
                $errors['applied_date'] = 'Applied date must be a valid YYYY-MM-DD date.';
            } elseif ($date !== '' && self::isDate($date) && $appliedDate > $date) {
                $errors['applied_date'] = 'Applied date cannot be greater than certification date.';
            }
        }

        if ($score === null || $score === '' || !is_numeric($score) || (float)$score < 0 || (float)$score > 100) {
            $errors['score'] = 'Score must be between 0 and 100.';
        }

        return $errors;
    }

    /**
     * Handles is date processing for this API workflow.
     */
    private static function isDate(string $value): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value;
    }
}
