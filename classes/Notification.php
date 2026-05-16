<?php
class Notification
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create($userId, $message, $type, $link = null, $complaintId = null)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications (user_id, complaint_id, message, type, link)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisss", $userId, $complaintId, $message, $type, $link);
        $stmt->execute();
        $stmt->close();
    }

    // Notify every admin
    public function notifyAllAdmins($message, $type, $link = null, $complaintId = null)
    {
        $result = $this->conn->query("SELECT user_id FROM users WHERE user_role = 'admin'");
        while ($row = $result->fetch_assoc()) {
            $this->create($row['user_id'], $message, $type, $link, $complaintId);
        }
    }

    // Get recent notifications for a user (for dropdown)
    public function getRecent($userId, $limit = 10)
    {
        $stmt = $this->conn->prepare(
            "SELECT notification_id, message, type, link, complaint_id, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Get all notifications for a user (view-all page)
    public function getAll($userId)
    {
        $stmt = $this->conn->prepare(
            "SELECT notification_id, message, type, link, complaint_id, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Count unread notifications for a user
    public function countUnread($userId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cnt = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Mark a single notification as read
    public function markRead($notificationId, $userId)
    {
        $stmt = $this->conn->prepare(
            "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Mark all notifications as read for a user
    public function markAllRead($userId)
    {
        $stmt = $this->conn->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Get the link for a notification and mark it read, return the link
    public function getLink($notificationId, $userId)
    {
        $stmt = $this->conn->prepare(
            "SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['link'] ?? null;
    }

    // Human-readable time-ago
    public static function timeAgo($datetime)
    {
        $now  = new DateTime();
        $past = new DateTime($datetime);
        $diff = $now->diff($past);

        if ($diff->y > 0) return $diff->y . 'y ago';
        if ($diff->m > 0) return $diff->m . 'mo ago';
        if ($diff->d > 0) return $diff->d . 'd ago';
        if ($diff->h > 0) return $diff->h . 'h ago';
        if ($diff->i > 0) return $diff->i . 'm ago';
        return 'just now';
    }

    // Icon class for each notification type
    public static function typeIcon($type)
    {
        $map = [
            'status_change'      => 'fa-sync-alt text-info',
            'new_assignment'     => 'fa-tasks text-primary',
            'request_info'       => 'fa-question-circle text-warning',
            'new_complaint'      => 'fa-file-alt text-danger',
            'new_registration'   => 'fa-user-plus text-success',
            'staff_approved'     => 'fa-check-circle text-success',
            'staff_rejected'     => 'fa-times-circle text-danger',
            'info_responded'     => 'fa-reply text-primary',
            'complaint_rejected' => 'fa-ban text-danger',
            'complaint_resolved' => 'fa-check-double text-success',
            'complaint_deleted'  => 'fa-trash text-danger',
        ];
        return $map[$type] ?? 'fa-bell text-secondary';
    }
}
