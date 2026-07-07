<?php
require_once __DIR__ . '/EmailQueue.php';
require_once __DIR__ . '/Mailer.php';

class Notification
{
    private $conn;

    // Maps notification type → email subject prefix
    private static array $subjects = [
        'new_complaint' => 'New Complaint Submitted',
        'new_assignment' => 'Complaint Assigned to You',
        'status_change' => 'Complaint Status Updated',
        'complaint_resolved' => 'Your Complaint Has Been Resolved',
        'complaint_rejected' => 'Your Complaint Has Been Rejected',
        'complaint_reopened' => 'Complaint Reopened',
        'complaint_deleted' => 'Complaint Deleted',
        'request_info' => 'Information Requested on Your Complaint',
        'info_responded' => 'Student Responded to Information Request',
        'system' => 'Important Account Notice',
        'password_reset_admin' => 'Your Password Has Been Reset',
        'staff_approved' => 'Your Staff Account Has Been Approved',
        'staff_rejected' => 'Your Staff Account Was Not Approved',
        'new_registration' => 'New Staff Registration Pending Approval',
        'complaint_delegated' => 'Complaint Delegated to You',
        'complaint_delegated_resolved' => 'Delegated Complaint Resolved',
        'complaint_overdue' => 'Complaint Past Resolution Deadline',
        'new_complaint_in_rep_scope' => 'New Complaint in Your Department',
        'endorsed_complaint_updated' => 'An Endorsed Complaint Has Been Updated',
    ];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    //  Create a new notification
    public function create($userId, $message, $type, $link = null, $complaintId = null)
    {
        if ($complaintId !== null) {
            $stmt = $this->conn->prepare(
                "INSERT INTO notifications (user_id, complaint_id, message, type, link)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iisss", $userId, $complaintId, $message, $type, $link);
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO notifications (user_id, message, type, link)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("isss", $userId, $message, $type, $link);
        }
        $stmt->execute();
        $stmt->close();

        // Queue email to same user
        $this->queueEmailForUser((int) $userId, $message, $type, $link);
    }

    private function queueEmailForUser(int $userId, string $message, string $type, ?string $link): void
    {
        try {
            $uStmt = $this->conn->prepare(
                "SELECT username, user_email FROM users WHERE user_id = ? LIMIT 1"
            );
            $uStmt->bind_param('i', $userId);
            $uStmt->execute();
            $user = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            if (!$user || empty($user['user_email'])) {
                return;
            }

            $subject = self::$subjects[$type] ?? 'SCMRS Notification';
            $body = Mailer::buildBody($this->conn, $user['username'], $message, $link);

            EmailQueue::enqueue($this->conn, $user['user_email'], $user['username'], $subject, $body);
        } catch (Throwable $e) {
            // Never let email queuing break the main request
            error_log('[Notification] queueEmailForUser error: ' . $e->getMessage());
        }
    }

    // Create the same notification for many users in one round-trip (to admins, delegators, endorsers.)
    public function createBulk(array $userIds, $message, $type, $link = null, $complaintId = null): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        $params = [];
        if ($complaintId !== null) {
            $placeholders = implode(', ', array_fill(0, count($userIds), '(?, ?, ?, ?, ?)'));
            $sql = "INSERT INTO notifications (user_id, complaint_id, message, type, link) VALUES $placeholders";
            $types = str_repeat('iisss', count($userIds));
            foreach ($userIds as $uid) {
                array_push($params, $uid, $complaintId, $message, $type, $link);
            }
        } else {
            $placeholders = implode(', ', array_fill(0, count($userIds), '(?, ?, ?, ?)'));
            $sql = "INSERT INTO notifications (user_id, message, type, link) VALUES $placeholders";
            $types = str_repeat('isss', count($userIds));
            foreach ($userIds as $uid) {
                array_push($params, $uid, $message, $type, $link);
            }
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $this->queueEmailForUsers($userIds, $message, $type, $link);
    }

    private function queueEmailForUsers(array $userIds, string $message, string $type, ?string $link): void
    {
        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $types = str_repeat('i', count($userIds));
            $stmt = $this->conn->prepare(
                "SELECT username, user_email FROM users WHERE user_id IN ($placeholders) AND user_email IS NOT NULL AND user_email <> ''"
            );
            $stmt->bind_param($types, ...$userIds);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($users)) {
                return;
            }

            $subject = self::$subjects[$type] ?? 'SCMRS Notification';
            foreach ($users as $user) {
                $body = Mailer::buildBody($this->conn, $user['username'], $message, $link);
                EmailQueue::enqueue($this->conn, $user['user_email'], $user['username'], $subject, $body);
            }
        } catch (Throwable $e) {
            // Never let email queuing break the main request
            error_log('[Notification] queueEmailForUsers error: ' . $e->getMessage());
        }
    }

    // Notify every admin
    public function notifyAllAdmins($message, $type, $link = null, $complaintId = null)
    {
        $result = $this->conn->query("SELECT user_id FROM users WHERE user_role = 'admin'");
        $adminIds = array_column($result->fetch_all(MYSQLI_ASSOC), 'user_id');
        $this->createBulk($adminIds, $message, $type, $link, $complaintId);
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
        $now = new DateTime();
        $past = new DateTime($datetime);
        $diff = $now->diff($past);

        if ($diff->y > 0)
            return $diff->y . 'y ago';
        if ($diff->m > 0)
            return $diff->m . 'mo ago';
        if ($diff->d > 0)
            return $diff->d . 'd ago';
        if ($diff->h > 0)
            return $diff->h . 'h ago';
        if ($diff->i > 0)
            return $diff->i . 'm ago';
        return 'just now';
    }

    // Icon class for each notification type
    public static function typeIcon($type)
    {
        $map = [
            'status_change' => 'fa-sync-alt text-info',
            'new_assignment' => 'fa-tasks text-primary',
            'request_info' => 'fa-question-circle text-warning',
            'new_complaint' => 'fa-file-alt text-danger',
            'new_registration' => 'fa-user-plus text-success',
            'staff_approved' => 'fa-check-circle text-success',
            'staff_rejected' => 'fa-times-circle text-danger',
            'info_responded' => 'fa-reply text-primary',
            'complaint_rejected' => 'fa-ban text-danger',
            'complaint_resolved' => 'fa-check-double text-success',
            'complaint_deleted' => 'fa-trash text-danger',
            'complaint_reopened' => 'fa-redo text-warning',
            'complaint_delegated_resolved' => 'fa-check-double text-success',
            'complaint_delegated' => 'fa-level-down-alt text-info',
            'complaint_overdue' => 'fa-exclamation-triangle text-danger',
            'new_complaint_in_rep_scope' => 'fa-building text-primary',
            'endorsed_complaint_updated' => 'fa-thumbs-up text-success',
            'system' => 'fa-shield-alt text-secondary',
            'password_reset_admin' => 'fa-key text-warning',
        ];
        return $map[$type] ?? 'fa-bell text-secondary';
    }
}
