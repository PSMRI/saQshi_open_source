<?php

/** Durable MySQL-backed queue. Workers claim one job at a time and can be run by Task Scheduler or a process manager. */
final class JobQueueService
{
    public function __construct(private mysqli $db) {}

    public function enqueue(string $type, array $payload = [], ?int $createdBy = null, int $maxAttempts = 3): int
    {
        $stmt = $this->db->prepare('INSERT INTO background_jobs (job_type, payload_json, max_attempts, created_by) VALUES (?, ?, ?, ?)');
        if (!$stmt) throw new RuntimeException('Job queue is unavailable: ' . $this->db->error);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $maxAttempts = max(1, $maxAttempts);
        $stmt->bind_param('ssii', $type, $json, $maxAttempts, $createdBy);
        if (!$stmt->execute()) throw new RuntimeException('Could not enqueue background job: ' . $stmt->error);
        return (int)$stmt->insert_id;
    }

    public function claim(): ?array
    {
        $this->db->begin_transaction();
        try {
            $result = $this->db->query("SELECT * FROM background_jobs WHERE status = 'QUEUED' AND available_at <= NOW() ORDER BY job_id LIMIT 1 FOR UPDATE");
            $job = $result ? $result->fetch_assoc() : null;
            if (!$job) { $this->db->commit(); return null; }
            $jobId = (int)$job['job_id'];
            $stmt = $this->db->prepare("UPDATE background_jobs SET status = 'RUNNING', attempts = attempts + 1, started_at = NOW() WHERE job_id = ?");
            if (!$stmt) throw new RuntimeException($this->db->error);
            $stmt->bind_param('i', $jobId);
            $stmt->execute();
            $this->db->commit();
            $job['attempts'] = (int)$job['attempts'] + 1;
            return $job;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function complete(int $jobId): void
    {
        $stmt = $this->db->prepare("UPDATE background_jobs SET status = 'COMPLETED', completed_at = NOW(), error_message = NULL WHERE job_id = ?");
        $stmt->bind_param('i', $jobId); $stmt->execute();
    }

    public function fail(array $job, Throwable $error): void
    {
        $jobId = (int)$job['job_id'];
        $retry = (int)$job['attempts'] < (int)$job['max_attempts'];
        $status = $retry ? 'QUEUED' : 'FAILED';
        $delay = min(300, 15 * max(1, (int)$job['attempts']));
        $message = substr($error->getMessage(), 0, 2000);
        $sql = $retry
            ? "UPDATE background_jobs SET status = ?, available_at = DATE_ADD(NOW(), INTERVAL {$delay} SECOND), error_message = ? WHERE job_id = ?"
            : "UPDATE background_jobs SET status = ?, failed_at = NOW(), error_message = ? WHERE job_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ssi', $status, $message, $jobId); $stmt->execute();
    }
}
