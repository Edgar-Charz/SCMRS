<?php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Settings.php';

class EmailQueue
{
    private static function getConnection(): mysqli
    {
        require_once __DIR__ . '/../config/Database.php';
        $db = new Database();
        return $db->connect();
    }

    // Add one email to the outbox. Called from Notification::create().
    public static function enqueue(
        mysqli $conn,
        string $toEmail,
        string $toName,
        string $subject,
        string $body
    ): void {
        $stmt = $conn->prepare(
            "INSERT INTO email_queue (to_email, to_name, subject, body)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $toEmail, $toName, $subject, $body);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Process up to $limit pending emails. Called from the shutdown hook.
     * Opens its own DB connection so it works safely after the response is sent.
     */
    public static function processPending(int $limit = 10): void
    {
        try {
            $conn = self::getConnection();
            $cfg = (new Settings($conn))->get();

            $max = (int) ($cfg['email_max_attempts'] ?? 3);
            $batchSize = min($limit, (int) ($cfg['email_batch_size'] ?? 10));

            $stmt = $conn->prepare(
                "SELECT id, to_email, to_name, subject, body, attempts
                 FROM email_queue
                 WHERE status = 'pending' AND attempts < ?
                 ORDER BY created_at ASC
                 LIMIT ?"
            );
            $stmt->bind_param('ii', $max, $batchSize);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $row) {
                self::attemptSend($conn, $row);
            }

            $conn->close();
        } catch (Throwable $e) {
            // Shutdown context - log silently, never throw
            error_log('[EmailQueue] processPending error: ' . $e->getMessage());
        }
    }

    private static function attemptSend(mysqli $conn, array $row): void
    {
        $id = (int) $row['id'];
        $attempts = (int) $row['attempts'] + 1;
        $cfg = (new Settings($conn))->get();
        $maxAttempts = (int) ($cfg['email_max_attempts'] ?? 3);

        try {
            Mailer::send($conn, $row['to_email'], $row['to_name'], $row['subject'], $row['body']);

            // Success
            $stmt = $conn->prepare(
                "UPDATE email_queue
                 SET status = 'sent', attempts = ?, sent_at = NOW(), last_attempted_at = NOW(), error_msg = NULL
                 WHERE id = ?"
            );
            $stmt->bind_param('ii', $attempts, $id);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            $errMsg = substr($e->getMessage(), 0, 500);
            $newStatus = ($attempts >= $maxAttempts) ? 'failed' : 'pending';

            $stmt = $conn->prepare(
                "UPDATE email_queue
                 SET status = ?, attempts = ?, last_attempted_at = NOW(), error_msg = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('sisi', $newStatus, $attempts, $errMsg, $id);
            $stmt->execute();
            $stmt->close();

            error_log("[EmailQueue] Failed to send email ID {$id}: {$errMsg}");
        }
    }

    //  Admin: get queue stats summary.
    public static function getStats(mysqli $conn): array
    {
        $result = $conn->query(
            "SELECT status, COUNT(*) AS cnt FROM email_queue GROUP BY status"
        );
        $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = (int) $row['cnt'];
        }
        return $stats;
    }

    // Admin: get recent queue rows for the management table.
    public static function getRecent(mysqli $conn, int $limit = 50): array
    {
        $stmt = $conn->prepare(
            "SELECT id, to_email, to_name, subject, status, attempts, error_msg,
                    last_attempted_at, sent_at, created_at
             FROM email_queue
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    //  Admin: retry all failed emails by resetting them to pending.
    public static function retryFailed(mysqli $conn): int
    {
        $conn->query(
            "UPDATE email_queue SET status = 'pending', attempts = 0, error_msg = NULL
             WHERE status = 'failed'"
        );
        return $conn->affected_rows;
    }

    // WHERE clause + bind params for a purge scope.
    private static function purgeWhere(string $scope, ?int $days): array
    {
        $statuses = $scope === 'sent_failed' ? ['sent', 'failed'] : ['sent'];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "WHERE status IN ($placeholders)";
        $types = str_repeat('s', count($statuses));
        $params = $statuses;

        if ($days !== null) {
            $sql .= " AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $types .= 'i';
            $params[] = $days;
        }

        return [$sql, $types, $params];
    }

    // Count how many queue rows a purge would remove.
    public static function countPurgable(mysqli $conn, string $scope, ?int $days): int
    {
        [$where, $types, $params] = self::purgeWhere($scope, $days);
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM email_queue $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $cnt = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Delete old queue rows matching scope/period. Returns rows removed.
    public static function purge(mysqli $conn, string $scope, ?int $days): int
    {
        $count = self::countPurgable($conn, $scope, $days);
        if ($count === 0) {
            return 0;
        }

        [$where, $types, $params] = self::purgeWhere($scope, $days);
        $stmt = $conn->prepare("DELETE FROM email_queue $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        return $count;
    }
}
