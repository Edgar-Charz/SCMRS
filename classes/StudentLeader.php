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

    // Complaints are only ever visible to leaders when their category (and, if set, subcategory)
    // has been flagged endorsable by an admin. A subcategory can only narrow this, never widen it.
    private const ENDORSABLE_CLAUSE = "cat.leader_endorsable = 1
               AND (c.subcategory_id IS NULL OR sub.leader_endorsable = 1)";

    // Department scope: senior leaders (no rows in student_rep_departments) see every department;
    // department-scoped leaders only see complaints in the departments they represent.
    private function departmentScopeClause(): string
    {
        $depts = $this->getDepartments();
        if (empty($depts)) {
            return '1=1';
        }
        $deptIds = implode(',', array_column($depts, 'department_id'));
        return "c.department_id IN ($deptIds)";
    }

    // Get all complaints visible to this leader (rank + endorsable-category scoped)
    public function getComplaints(int $limit = 0): array
    {
        $deptClause = $this->departmentScopeClause();
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
             LEFT JOIN complaint_subcategories sub ON sub.subcategory_id = c.subcategory_id
             LEFT JOIN departments d       ON d.department_id = c.department_id
             JOIN students st              ON st.student_id = c.student_id
             JOIN users u                  ON u.user_id = st.student_user_id
             WHERE $deptClause
               AND " . self::ENDORSABLE_CLAUSE . "
             ORDER BY c.created_at DESC
             $limitClause"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Get a single complaint by ID (scope-checked against leader's rank/departments and endorsable categories)
    public function getComplaintById(int $complaintId): ?array
    {
        $deptClause = $this->departmentScopeClause();
        $uid = (int) $this->userId;
        $cid = (int) $complaintId;

        $result = $this->conn->query(
            "SELECT c.complaint_id, c.complaint_title, c.complaint_description,
                    c.complaint_status, c.priority, c.is_anonymous, c.created_at,
                    cat.category_name, sub.subcategory_name, d.department_name,
                    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous' ELSE u.username END AS student_name,
                    EXISTS(SELECT 1 FROM complaint_endorsements ce
                           WHERE ce.complaint_id = c.complaint_id
                             AND ce.leader_id = $uid) AS i_endorsed
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             LEFT JOIN complaint_subcategories sub ON sub.subcategory_id = c.subcategory_id
             LEFT JOIN departments d       ON d.department_id = c.department_id
             JOIN students st              ON st.student_id = c.student_id
             JOIN users u                  ON u.user_id = st.student_user_id
             WHERE c.complaint_id = $cid
               AND $deptClause
               AND " . self::ENDORSABLE_CLAUSE . "
             LIMIT 1"
        );
        return $result ? ($result->fetch_assoc() ?: null) : null;
    }

    // Get complaint count statistics for the leader's scope (rank + endorsable categories)
    public function getStats(): array
    {
        $deptClause = $this->departmentScopeClause();
        $uid = (int) $this->userId;

        $row = $this->conn->query(
            "SELECT COUNT(*) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             LEFT JOIN complaint_subcategories sub ON sub.subcategory_id = c.subcategory_id
             WHERE $deptClause
               AND " . self::ENDORSABLE_CLAUSE
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

    // Whether a complaint has reached a final state where endorsements should be frozen
    public function isComplaintFinalized(int $complaintId): bool
    {
        $stmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1");
        if (!$stmt)
            return false;
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return in_array($row['complaint_status'] ?? '', [STATUS_RESOLVED, STATUS_REJECTED], true);
    }

    // Endorse a complaint with an optional note
    public function endorse(int $complaintId, string $note = ''): bool
    {
        if ($this->isComplaintFinalized($complaintId))
            return false;

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
        if ($this->isComplaintFinalized($complaintId))
            return false;

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

    // Get all current student reps with their departments and endorsement counts.
    // Reps with no department rows are "senior leaders" who oversee every department.
    public function getAllReps(): array
    {
        $result = $this->conn->query(
            "SELECT u.user_id, u.username, u.user_email, u.user_status,
                    st.student_registration_number,
                    GROUP_CONCAT(d.department_name ORDER BY d.department_name SEPARATOR ', ') AS departments,
                    GROUP_CONCAT(s.department_id   ORDER BY d.department_name SEPARATOR ',') AS department_ids,
                    (SELECT COUNT(*) FROM complaint_endorsements ce WHERE ce.leader_id = u.user_id) AS endorsement_count
             FROM users u
             LEFT JOIN student_rep_departments s ON s.user_id = u.user_id
             LEFT JOIN departments d             ON d.department_id = s.department_id
             LEFT JOIN students st               ON st.student_user_id = u.user_id
             WHERE u.user_role = 'student_leader'
             GROUP BY u.user_id, u.username, u.user_email, u.user_status, st.student_registration_number
             ORDER BY u.username"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Check if this user is an active student rep (department-scoped or senior)
    public function isLeader(): bool
    {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND user_role = 'student_leader' LIMIT 1");
        if (!$stmt)
            return false;
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null;
    }

    // Whether this leader has no department rows, i.e. a senior leader who sees every department
    public function isSenior(): bool
    {
        return empty($this->getDepartments());
    }

    // Promote a student straight to a senior (all-department) leader, with no department rows
    public function promoteAsSeniorLeader(): bool
    {
        $upd = $this->conn->prepare("UPDATE users SET user_role = 'student_leader' WHERE user_id = ?");
        if (!$upd)
            return false;
        $upd->bind_param('i', $this->userId);
        $ok = $upd->execute();
        $upd->close();
        return $ok;
    }

    // Demote a senior leader (no departments) back to a plain student
    public function demoteToStudent(): bool
    {
        $upd = $this->conn->prepare("UPDATE users SET user_role = 'student' WHERE user_id = ?");
        if (!$upd)
            return false;
        $upd->bind_param('i', $this->userId);
        $ok = $upd->execute();
        $upd->close();
        return $ok;
    }

    // Get the student_id for this leader (leaders are also students)
    public function getStudentId(): ?int
    {
        $stmt = $this->conn->prepare("SELECT student_id FROM students WHERE student_user_id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['student_id'] : null;
    }

    // Get approved, active staff members for a given department
    public function getApprovedStaffByDepartment(int $deptId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT s.staff_id, u.username, sr.role_name
             FROM staffs s
             JOIN users u ON u.user_id = s.staff_user_id
             LEFT JOIN staff_roles sr ON sr.role_id = s.staff_role_id
             WHERE s.staff_department_id = ?
               AND s.staff_approval_status = 1
               AND u.user_status = 'active'
             ORDER BY u.username"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $deptId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Get complaints submitted by this leader (as a student)
    public function getMyComplaints(int $limit = 0): array
    {
        $studentId = $this->getStudentId();
        if (!$studentId) return [];
        $limitClause = $limit > 0 ? "LIMIT $limit" : '';
        $stmt = $this->conn->prepare(
            "SELECT c.complaint_id, c.complaint_title, c.complaint_description,
                    c.complaint_status, c.priority, c.is_anonymous, c.created_at, c.updated_at,
                    c.preferred_staff_id,
                    cat.category_name, d.department_name,
                    su.username AS preferred_staff_name
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             LEFT JOIN departments d       ON d.department_id = c.department_id
             LEFT JOIN staffs sf           ON sf.staff_id = c.preferred_staff_id
             LEFT JOIN users su            ON su.user_id = sf.staff_user_id
             WHERE c.student_id = ?
               AND c.complaint_status != 'deleted'
             ORDER BY c.created_at DESC
             $limitClause"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // Count this leader's own submitted complaints by status
    public function getMyComplaintCounts(): array
    {
        $studentId = $this->getStudentId();
        if (!$studentId) {
            return ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'rejected' => 0];
        }
        $row = $this->conn->query(
            "SELECT COUNT(*) AS total,
                    SUM(complaint_status = 'pending')     AS pending,
                    SUM(complaint_status = 'in_progress') AS in_progress,
                    SUM(complaint_status = 'resolved')    AS resolved,
                    SUM(complaint_status = 'rejected')    AS rejected
             FROM complaints
             WHERE student_id = $studentId
               AND complaint_status != 'deleted'"
        )->fetch_assoc();
        return [
            'total'       => (int)($row['total'] ?? 0),
            'pending'     => (int)($row['pending'] ?? 0),
            'in_progress' => (int)($row['in_progress'] ?? 0),
            'resolved'    => (int)($row['resolved'] ?? 0),
            'rejected'    => (int)($row['rejected'] ?? 0),
        ];
    }

    // Count pending complaints in the leader's scope that the leader has NOT yet endorsed
    public function getUnendorsedPendingCount(): int
    {
        $deptClause = $this->departmentScopeClause();
        $uid = (int)$this->userId;
        $row = $this->conn->query(
            "SELECT COUNT(*) AS cnt
             FROM complaints c
             JOIN complaint_categories cat ON cat.category_id = c.category_id
             LEFT JOIN complaint_subcategories sub ON sub.subcategory_id = c.subcategory_id
             WHERE $deptClause
               AND c.complaint_status = 'pending'
               AND " . self::ENDORSABLE_CLAUSE . "
               AND NOT EXISTS (
                   SELECT 1 FROM complaint_endorsements ce
                   WHERE ce.complaint_id = c.complaint_id
                     AND ce.leader_id = $uid
               )"
        )->fetch_assoc();
        return (int)($row['cnt'] ?? 0);
    }

    // Set preferred staff on a newly created complaint
    public function setPreferredStaff(int $complaintId, string $staffId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE complaints SET preferred_staff_id = ? WHERE complaint_id = ?"
        );
        if (!$stmt) return false;
        $stmt->bind_param('si', $staffId, $complaintId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
