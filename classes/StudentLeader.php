<?php
require_once __DIR__ . '/Notification.php';

class StudentLeader
{
    private $conn;
    private int $userId;

    public function __construct($db, int $userId = 0)
    {
        $this->conn = $db;
        $this->userId = $userId;
    }

    // Switch the active user this instance operates on
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    // Get username of this user
    public function getUsername(): string
    {
        $stmt = $this->conn->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
        if (!$stmt)
            return "User #$this->userId";
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['username'] ?? "User #$this->userId";
    }

    // Get leader profile with concatenated department names
    public function getLeaderDetails(): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.user_id, u.username, u.user_email,
                    GROUP_CONCAT(d.department_name ORDER BY d.department_name SEPARATOR ', ') AS departments
             FROM users u
             JOIN student_rep_departments s ON s.user_id = u.user_id
             JOIN departments d ON d.department_id = s.department_id
             WHERE u.user_id = ?
             GROUP BY u.user_id"
        );
        if (!$stmt)
            return null;
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // Get departments this leader represents
    public function getDepartments(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT s.department_id, d.department_name
             FROM student_rep_departments s
             JOIN departments d ON d.department_id = s.department_id
             WHERE s.user_id = ?
             ORDER BY d.department_name"
        );
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Get all complaints from leader's departments
    public function getComplaints(int $limit = 0): array
    {
        $depts = $this->getDepartments();
        if (empty($depts))
            return [];

        $deptIds = implode(',', array_column($depts, 'department_id'));
        $uid = (int) $this->userId;
        $limitClause = $limit > 0 ? "LIMIT $limit" : '';

        $result = $this->conn->query(
            "SELECT c.complaint_id, c.complaint_title, c.complaint_description,
                    c.complaint_status, c.priority, c.is_anonymous, c.created_at,
                    cat.category_name, d.department_name,
                    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous' ELSE u.username END AS student_name,
                    (SELECT COUNT(*) FROM complaint_endorsements ce
                     WHERE ce.complaint_id = c.complaint_id) AS endorsement_count,
                    EXISTS(SELECT 1 FROM complaint_endorsements ce2
                           WHERE ce2.complaint_id = c.complaint_id
                             AND ce2.leader_id = $uid) AS i_endorsed
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             JOIN departments d            ON d.department_id = c.department_id
             JOIN students st              ON st.student_id = c.student_id
             JOIN users u                  ON u.user_id = st.student_user_id
             WHERE c.department_id IN ($deptIds)
             ORDER BY c.created_at DESC
             $limitClause"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Get a single complaint by ID (scope-checked against leader's departments)
    public function getComplaintById(int $complaintId): ?array
    {
        $depts = $this->getDepartments();
        if (empty($depts))
            return null;

        $deptIds = implode(',', array_column($depts, 'department_id'));
        $uid = (int) $this->userId;
        $cid = (int) $complaintId;

        $result = $this->conn->query(
            "SELECT c.complaint_id, c.complaint_title, c.complaint_description,
                    c.complaint_status, c.priority, c.is_anonymous, c.created_at,
                    cat.category_name, d.department_name,
                    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous' ELSE u.username END AS student_name,
                    EXISTS(SELECT 1 FROM complaint_endorsements ce
                           WHERE ce.complaint_id = c.complaint_id
                             AND ce.leader_id = $uid) AS i_endorsed
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             JOIN departments d            ON d.department_id = c.department_id
             JOIN students st              ON st.student_id = c.student_id
             JOIN users u                  ON u.user_id = st.student_user_id
             WHERE c.complaint_id = $cid
               AND c.department_id IN ($deptIds)
             LIMIT 1"
        );
        return $result ? ($result->fetch_assoc() ?: null) : null;
    }

    // Get complaint count statistics for the leader's departments
    public function getStats(): array
    {
        $depts = $this->getDepartments();
        if (empty($depts)) {
            return ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'endorsed' => 0];
        }

        $deptIds = implode(',', array_column($depts, 'department_id'));
        $uid = (int) $this->userId;

        $row = $this->conn->query(
            "SELECT COUNT(*) AS total,
                    SUM(complaint_status = 'pending')     AS pending,
                    SUM(complaint_status = 'in_progress') AS in_progress,
                    SUM(complaint_status = 'resolved')    AS resolved
             FROM complaints
             WHERE department_id IN ($deptIds)"
        )->fetch_assoc();

        $endorsed = (int) $this->conn->query(
            "SELECT COUNT(*) AS cnt FROM complaint_endorsements WHERE leader_id = $uid"
        )->fetch_assoc()['cnt'];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'resolved' => (int) ($row['resolved'] ?? 0),
            'endorsed' => $endorsed,
        ];
    }

    // Check whether this leader has already endorsed a complaint
    public function hasEndorsed(int $complaintId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT id FROM complaint_endorsements WHERE complaint_id = ? AND leader_id = ? LIMIT 1"
        );
        if (!$stmt)
            return false;
        $stmt->bind_param('ii', $complaintId, $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null;
    }

    // Endorse a complaint with an optional note
    public function endorse(int $complaintId, string $note = ''): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT IGNORE INTO complaint_endorsements (complaint_id, leader_id, note) VALUES (?, ?, ?)"
        );
        if (!$stmt)
            return false;
        $stmt->bind_param('iis', $complaintId, $this->userId, $note);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows; // capture before close(); conn->affected_rows resets on close
        $stmt->close();
        return $ok && $affected > 0;
    }

    // Remove this leader's endorsement from a complaint
    public function removeEndorsement(int $complaintId): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM complaint_endorsements WHERE complaint_id = ? AND leader_id = ?"
        );
        if (!$stmt)
            return false;
        $stmt->bind_param('ii', $complaintId, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Get all endorsements for a given complaint
    public function getEndorsementsForComplaint(int $complaintId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT ce.id, ce.note, ce.created_at, u.username AS leader_name
             FROM complaint_endorsements ce
             JOIN users u ON u.user_id = ce.leader_id
             WHERE ce.complaint_id = ?
             ORDER BY ce.created_at ASC"
        );
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Notify all reps assigned to a department about a new complaint event
    public function notifyLeadersInDepartment(int $deptId, string $message, string $type, ?string $link, int $complaintId): void
    {
        $stmt = $this->conn->prepare(
            "SELECT user_id FROM student_rep_departments WHERE department_id = ?"
        );
        if (!$stmt)
            return;
        $stmt->bind_param('i', $deptId);
        $stmt->execute();
        $leaders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($leaders))
            return;

        $notif = new Notification($this->conn);
        foreach ($leaders as $leader) {
            $notif->create($leader['user_id'], $message, $type, $link, $complaintId);
        }
    }

    // Assign this leader to a department and promote their role
    public function assign(int $deptId, int $adminId): bool
    {
        $stmt = $this->conn->prepare(
            "INSERT IGNORE INTO student_rep_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)"
        );
        if (!$stmt)
            return false;
        $stmt->bind_param('iii', $this->userId, $deptId, $adminId);
        $ok = $stmt->execute();
        $stmt->close();

        $upd = $this->conn->prepare("UPDATE users SET user_role = 'student_leader' WHERE user_id = ?");
        if (!$upd)
            return false;
        $upd->bind_param('i', $this->userId);
        $upd->execute();
        $upd->close();
        return $ok;
    }

    // Revoke this leader's access to a department; demotes to student if no departments remain
    public function revoke(int $deptId): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM student_rep_departments WHERE user_id = ? AND department_id = ?"
        );
        if (!$stmt)
            return false;
        $stmt->bind_param('ii', $this->userId, $deptId);
        $ok = $stmt->execute();
        $stmt->close();

        $check = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM student_rep_departments WHERE user_id = ?");
        if (!$check)
            return $ok;
        $check->bind_param('i', $this->userId);
        $check->execute();
        $cnt = (int) $check->get_result()->fetch_assoc()['cnt'];
        $check->close();

        if ($cnt === 0) {
            $dem = $this->conn->prepare("UPDATE users SET user_role = 'student' WHERE user_id = ?");
            if ($dem) {
                $dem->bind_param('i', $this->userId);
                $dem->execute();
                $dem->close();
            }
        }
        return $ok;
    }

    // Get all current student reps with their departments and endorsement counts
    public function getAllReps(): array
    {
        $result = $this->conn->query(
            "SELECT u.user_id, u.username, u.user_email, u.user_status,
                    st.student_registration_number,
                    GROUP_CONCAT(d.department_name ORDER BY d.department_name SEPARATOR ', ') AS departments,
                    GROUP_CONCAT(s.department_id   ORDER BY d.department_name SEPARATOR ',') AS department_ids,
                    (SELECT COUNT(*) FROM complaint_endorsements ce WHERE ce.leader_id = u.user_id) AS endorsement_count
             FROM users u
             JOIN student_rep_departments s ON s.user_id = u.user_id
             JOIN departments d             ON d.department_id = s.department_id
             LEFT JOIN students st          ON st.student_user_id = u.user_id
             GROUP BY u.user_id, u.username, u.user_email, u.user_status, st.student_registration_number
             ORDER BY u.username"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Check if this user is an active student rep
    public function isLeader(): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM student_rep_departments WHERE user_id = ? LIMIT 1");
        if (!$stmt)
            return false;
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null;
    }
}
