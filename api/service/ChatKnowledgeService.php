<?php

/*!
 * ==========================================================
 * SaQshi Open Source
 * Chat Knowledge Service
 * ChatKnowledgeService.php
 * Version 1.0.0 | Updated 2026-07-18
 * ==========================================================
 */

class ChatKnowledgeService
{
    private const KNOWLEDGE_FILE = __DIR__ . '/../config/chat/knowledge.json';

    public static function answer(string $answerKey): string
    {
        $answers = self::answers();
        $answer = $answers[$answerKey] ?? ($answers['fallback'] ?? []);

        return self::formatAnswer($answer);
    }

    private static function answers(): array
    {
        static $answers = null;

        if ($answers !== null) {
            return $answers;
        }

        $json = is_file(self::KNOWLEDGE_FILE) ? json_decode((string)file_get_contents(self::KNOWLEDGE_FILE), true) : [];
        $answers = is_array($json['answers'] ?? null) ? $json['answers'] : [];
        return $answers;
    }

    private static function formatAnswer(array $answer): string
    {
        $lines = [];
        $short = trim((string)($answer['short'] ?? ''));

        if ($short !== '') {
            $lines[] = $short;
        }

        if (!empty($answer['steps']) && is_array($answer['steps'])) {
            $lines[] = '';
            foreach (array_values($answer['steps']) as $index => $step) {
                $lines[] = ($index + 1) . '. ' . trim((string)$step);
            }
        }

        if (!empty($answer['notes']) && is_array($answer['notes'])) {
            $lines[] = '';
            foreach ($answer['notes'] as $note) {
                $lines[] = '- ' . trim((string)$note);
            }
        }

        return trim(implode("\n", $lines));
    }
}
