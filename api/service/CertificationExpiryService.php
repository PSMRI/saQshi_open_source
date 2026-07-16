<?php

/**
 * Provides certification expiry service behavior for SaQshi API workflows.
 */
class CertificationExpiryService
{
    /**
     * Handles calculate valid to processing for this API workflow.
     */
    public static function calculateValidTo(string $status, string $certificationDate, array $config): array
    {
        $normalized = self::normalizeStatus($status);
        $years = self::validityYears($normalized, $config);
        $from = new DateTime($certificationDate);
        $to = clone $from;
        $to->modify('+' . $years . ' years')->modify('-1 day');

        return [
            'validity_years' => $years,
            'valid_from' => $from->format('Y-m-d'),
            'valid_to' => $to->format('Y-m-d')
        ];
    }

    /**
     * Handles renewal status processing for this API workflow.
     */
    public static function renewalStatus(?string $validTo, int $dueDays = 90): string
    {
        if (!$validTo) {
            return 'NOT_AVAILABLE';
        }

        $today = new DateTime(date('Y-m-d'));
        $expiry = new DateTime($validTo);

        if ($expiry < $today) {
            return 'EXPIRED';
        }

        $diff = (int)$today->diff($expiry)->format('%a');

        return $diff <= $dueDays ? 'RENEWAL_DUE' : 'ACTIVE';
    }

    /**
     * Handles normalize status processing for this API workflow.
     */
    public static function normalizeStatus(string $status): string
    {
        $value = strtoupper(trim($status));

        if (in_array($value, ['CERTIFIED WITH CONDITION', 'CERTIFIED WITH CONDITIONS'], true)) {
            return 'CONDITIONAL';
        }

        return $value;
    }

    /**
     * Handles validity years processing for this API workflow.
     */
    private static function validityYears(string $status, array $config): int
    {
        foreach (($config['statuses'] ?? []) as $row) {
            if (strtoupper((string)($row['code'] ?? '')) === $status) {
                return max(1, (int)($row['validity_years'] ?? 1));
            }
        }

        throw new InvalidArgumentException('Invalid certification status.');
    }
}
