<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Chat Intent Service
 * ChatIntentService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

class ChatIntentService
{
    private const INTENTS_FILE = __DIR__ . '/../config/chat/intents.json';

    public static function match(string $message, int $roleId, string $contextPage = ''): array
    {
        $text = self::normalize($message . ' ' . $contextPage);
        $role = self::roleKey($roleId);
        $best = [];
        $bestScore = 0;

        foreach (self::intents() as $intent) {
            if (!self::roleAllowed($intent['roles'] ?? [], $role)) {
                continue;
            }

            $score = 0;
            foreach (($intent['keywords'] ?? []) as $keyword) {
                $needle = self::normalize((string)$keyword);
                if ($needle !== '' && str_contains($text, $needle)) {
                    $score += max(1, substr_count($needle, ' ') + 1);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $intent;
            }
        }

        if (!$best) {
            return [
                'intent' => 'fallback',
                'answer_key' => 'fallback',
                'score' => 0,
                'role' => $role
            ];
        }

        $best['score'] = $bestScore;
        $best['role'] = $role;
        return $best;
    }

    public static function roleKey(int $roleId): string
    {
        return match ($roleId) {
            9 => 'state',
            5 => 'division',
            4 => 'district',
            8 => 'block',
            10 => 'assessor',
            default => 'facility',
        };
    }

    private static function intents(): array
    {
        static $intents = null;

        if ($intents !== null) {
            return $intents;
        }

        $json = is_file(self::INTENTS_FILE) ? json_decode((string)file_get_contents(self::INTENTS_FILE), true) : [];
        $intents = is_array($json['intents'] ?? null) ? $json['intents'] : [];
        return $intents;
    }

    private static function roleAllowed(array $roles, string $role): bool
    {
        return in_array('all', $roles, true) || in_array($role, $roles, true);
    }

    private static function normalize(string $value): string
    {
        return trim((string)preg_replace('/\s+/', ' ', strtolower($value)));
    }
}
