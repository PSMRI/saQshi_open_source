<?php

require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/ChatIntentService.php';
require_once __DIR__ . '/ChatKnowledgeService.php';
require_once __DIR__ . '/ChatDataService.php';

/**
 * Provides chat assistant service behavior for SaQshi API workflows.
 */
class ChatAssistantService
{
    /**
     * Handles ensure table processing for this API workflow.
     */
    public static function ensureTable(mysqli $con): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ai_chat_messages (
                message_id BIGINT NOT NULL AUTO_INCREMENT,
                user_id INT NULL,
                fac_id INT NULL,
                role VARCHAR(20) NOT NULL,
                message_text TEXT NOT NULL,
                context_page VARCHAR(120) NULL,
                intent_key VARCHAR(80) NULL,
                source_type VARCHAR(30) NULL,
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id),
                KEY idx_ai_chat_user (user_id, created_on),
                KEY idx_ai_chat_facility (fac_id, created_on)
            )
        ";

        if (!$con->query($sql)) {
            throw new RuntimeException('Unable to create ai_chat_messages table: ' . $con->error);
        }

        self::ensureColumn($con, 'intent_key', 'VARCHAR(80) NULL');
        self::ensureColumn($con, 'source_type', 'VARCHAR(30) NULL');
    }

    /**
     * Handles send processing for this API workflow.
     */
    public static function send(mysqli $con, int $userId, int $facId, string $message, string $contextPage = ''): array
    {
        self::ensureTable($con);
        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException('Message is required.');
        }

        if (strlen($message) > 2000) {
            throw new InvalidArgumentException('Message is too long. Please keep it under 2000 characters.');
        }

        $intent = ChatIntentService::match($message, (int)SessionManager::roleId(), $contextPage);
        self::saveMessage($con, $userId, $facId, 'user', $message, $contextPage, (string)$intent['intent'], 'user');

        $dataReply = ChatDataService::answer($con, $intent, $message, $userId, $facId);
        $reply = $dataReply ?: ChatKnowledgeService::answer((string)($intent['answer_key'] ?? 'fallback'));
        $source = $dataReply ? 'data' : 'knowledge';
        self::saveMessage($con, $userId, $facId, 'assistant', $reply, $contextPage, (string)$intent['intent'], $source);

        return [
            'reply' => $reply,
            'intent' => $intent['intent'] ?? 'fallback',
            'source' => $source,
            'history' => self::history($con, $userId, $facId)
        ];
    }

    /**
     * Handles history processing for this API workflow.
     */
    public static function history(mysqli $con, int $userId, int $facId, int $limit = 50): array
    {
        self::ensureTable($con);
        $limit = max(1, min(100, $limit));
        $stmt = $con->prepare("
            SELECT message_id, role, message_text, context_page, intent_key, source_type, created_on
            FROM ai_chat_messages
            WHERE user_id = ? AND fac_id = ?
            ORDER BY message_id DESC
            LIMIT ?
        ");

        if (!$stmt) {
            throw new RuntimeException('Chat history prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iii', $userId, $facId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'message_id' => (int)$row['message_id'],
                'role' => $row['role'],
                'message' => $row['message_text'],
                'context_page' => $row['context_page'],
                'intent' => $row['intent_key'] ?? '',
                'source' => $row['source_type'] ?? '',
                'created_on' => $row['created_on']
            ];
        }

        return array_reverse($rows);
    }

    /**
     * Handles clear processing for this API workflow.
     */
    public static function clear(mysqli $con, int $userId, int $facId): void
    {
        self::ensureTable($con);
        $stmt = $con->prepare("DELETE FROM ai_chat_messages WHERE user_id = ? AND fac_id = ?");

        if (!$stmt) {
            throw new RuntimeException('Chat clear prepare failed: ' . $con->error);
        }

        $stmt->bind_param('ii', $userId, $facId);
        $stmt->execute();
    }

    /**
     * Handles save message processing for this API workflow.
     */
    private static function saveMessage(mysqli $con, int $userId, int $facId, string $role, string $message, string $contextPage, string $intentKey = '', string $sourceType = ''): void
    {
        $stmt = $con->prepare("
            INSERT INTO ai_chat_messages (user_id, fac_id, role, message_text, context_page, intent_key, source_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Chat save prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iisssss', $userId, $facId, $role, $message, $contextPage, $intentKey, $sourceType);

        if (!$stmt->execute()) {
            throw new RuntimeException('Chat save failed: ' . $stmt->error);
        }
    }

    private static function ensureColumn(mysqli $con, string $column, string $definition): void
    {
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $result = $con->query("SHOW COLUMNS FROM ai_chat_messages LIKE '{$safeColumn}'");

        if ($result && $result->num_rows > 0) {
            return;
        }

        if (!$con->query("ALTER TABLE ai_chat_messages ADD COLUMN {$safeColumn} {$definition}")) {
            throw new RuntimeException('Unable to update ai_chat_messages table: ' . $con->error);
        }
    }
}
