<?php

class ChatAssistantService
{
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
                created_on TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id),
                KEY idx_ai_chat_user (user_id, created_on),
                KEY idx_ai_chat_facility (fac_id, created_on)
            )
        ";

        if (!$con->query($sql)) {
            throw new RuntimeException('Unable to create ai_chat_messages table: ' . $con->error);
        }
    }

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

        self::saveMessage($con, $userId, $facId, 'user', $message, $contextPage);
        $reply = self::buildReply($message, $contextPage);
        self::saveMessage($con, $userId, $facId, 'assistant', $reply, $contextPage);

        return [
            'reply' => $reply,
            'history' => self::history($con, $userId, $facId)
        ];
    }

    public static function history(mysqli $con, int $userId, int $facId, int $limit = 50): array
    {
        self::ensureTable($con);
        $limit = max(1, min(100, $limit));
        $stmt = $con->prepare("
            SELECT message_id, role, message_text, context_page, created_on
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
                'created_on' => $row['created_on']
            ];
        }

        return array_reverse($rows);
    }

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

    private static function saveMessage(mysqli $con, int $userId, int $facId, string $role, string $message, string $contextPage): void
    {
        $stmt = $con->prepare("
            INSERT INTO ai_chat_messages (user_id, fac_id, role, message_text, context_page)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Chat save prepare failed: ' . $con->error);
        }

        $stmt->bind_param('iisss', $userId, $facId, $role, $message, $contextPage);

        if (!$stmt->execute()) {
            throw new RuntimeException('Chat save failed: ' . $stmt->error);
        }
    }

    private static function buildReply(string $message, string $contextPage): string
    {
        $text = strtolower($message . ' ' . $contextPage);

        $topics = [
            'certification' => "Certification: use Manage Certification to add STATE or NATIONAL records. Facility name and NIN come from the logged-in facility. Applied Date is optional but must be on or before Certification Date. Certification Date cannot be future dated. CONDITIONAL is valid for 1 year and CERTIFIED is valid for 3 years. NATIONAL requires an existing STATE certification.",
            'gap' => "Gap Analysis: review non-compliant and partially compliant checkpoints, then move to Action Plan for owner, target date and facility action details. Gap Closure records revised score, closure status, remarks and optional evidence.",
            'action' => "Action Plan: select department, area and subtype, review the suggested action, then save the facility action plan with responsible person and target date. Closure updates should be done after action completion.",
            'checklist' => "Checklist: select department, area of concern, subtype and method, then score each checkpoint as 0, 1 or 2. Zero means non-compliance, one partial compliance and two full compliance.",
            'assessment' => "Assessment: create an assessment only when no ACTIVE assessment exists. Activate applicable departments, fill assessor information, complete checklist responses, then use CQI and reports.",
            'kpi' => "KPI and Outcome: enter monthly numerator, denominator, result and remarks for activated departments. Trend pages show month-wise charts and Excel download options.",
            'outcome' => "KPI and Outcome: enter monthly numerator, denominator, result and remarks for activated departments. Trend pages show month-wise charts and Excel download options.",
            'report' => "Reports: Report Dashboard links to Score Report, Progress Report, KPI Report, Outcome Report and Certification Report. Score and progress reports download assessment data in standard formats."
        ];

        foreach ($topics as $keyword => $reply) {
            if (str_contains($text, $keyword)) {
                return $reply;
            }
        }

        return "I can help with SaQshi assessment, checklist scoring, CQI gap/action/closure, KPI and Outcome entry, reports, certification rules, and facility profile workflows. Please mention the page or task you are working on.";
    }
}
