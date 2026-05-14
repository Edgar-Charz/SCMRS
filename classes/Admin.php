<?php
class Admin extends User
{

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getTotalUsers()
    {
        $total_users_stmt = $this->conn->prepare("SELECT COUNT(*) as total_users FROM users");
        $total_users_stmt->execute();
        $total_users_result = $total_users_stmt->get_result();

        return $total_users_result->fetch_assoc()['total_users'];
    }

    public function getTotalDepartments()
    {
        $total_departments_stmt = $this->conn->prepare("SELECT COUNT(*) as total_departments FROM departments");
        $total_departments_stmt->execute();
        $total_departments_result = $total_departments_stmt->get_result();

        return $total_departments_result->fetch_assoc()['total_departments'];
    }

    public function getTotalCategories()
    {
        $total_categories_stmt = $this->conn->prepare("SELECT COUNT(*) as total_categories FROM complaint_categories");
        $total_categories_stmt->execute();
        $total_complaints_result = $total_categories_stmt->get_result();

        return $total_complaints_result->fetch_assoc()['total_categories'];
    }

    public function getTotalComplaints()
    {
        $total_complaints_stmt = $this->conn->prepare("SELECT COUNT(*) as total_complaints FROM complaints");
        $total_complaints_stmt->execute();
        $total_complaints_result = $total_complaints_stmt->get_result();

        return $total_complaints_result->fetch_assoc()['total_complaints'];
    }

    public function getTotalPending()
    {
        $total_pending_stmt = $this->conn->prepare("SELECT COUNT(*) as total_pending FROM complaints WHERE complaint_status = 'pending' ");
        $total_pending_stmt->execute();
        $total_pending_result = $total_pending_stmt->get_result();

        return $total_pending_result->fetch_assoc()['total_pending'];
    }

    public function getTotalInprogress()
    {
        $total_inprogress_stmt = $this->conn->prepare("SELECT COUNT(*) as total_inprogress FROM complaints WHERE complaint_status = 'in_progress' ");
        $total_inprogress_stmt->execute();
        $total_inprogress_result = $total_inprogress_stmt->get_result();

        return $total_inprogress_result->fetch_assoc()['total_inprogress'];
    }

    public function getTotalResolved()
    {
        $total_resolved_stmt = $this->conn->prepare("SELECT COUNT(*) as total_resolved FROM complaints WHERE complaint_status = 'resolved' ");
        $total_resolved_stmt->execute();
        $total_resolved_result = $total_resolved_stmt->get_result();

        return $total_resolved_result->fetch_assoc()['total_resolved'];
    }

    public function getTotalRejected()
    {
        $total_rejected_stmt = $this->conn->prepare("SELECT COUNT(*) as total_rejected FROM complaints WHERE complaint_status = 'rejected' ");
        $total_rejected_stmt->execute();
        $total_rejected_result = $total_rejected_stmt->get_result();

        return $total_rejected_result->fetch_assoc()['total_rejected'];
    }

    public function getUserCountsByRole()
    {
        $stmt = $this->conn->prepare(
            "SELECT user_role, COUNT(*) AS total FROM users GROUP BY user_role"
        );
        $stmt->execute();
        $rows   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $counts = ['student' => 0, 'staff' => 0, 'admin' => 0];
        foreach ($rows as $row) {
            $counts[$row['user_role']] = (int) $row['total'];
        }
        return $counts;
    }

    public function getPendingStaffCount()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM staffs WHERE staff_approval_status = 0");
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['cnt'];
    }

    public function getComplaints()
    {
        $sql = "SELECT c.*, cc.category_name,
                       u.username AS student_name,
                       s.student_registration_number,
                       d.department_name,
                       su.username AS assigned_staff_name
                FROM complaints c
                JOIN complaint_categories cc ON c.category_id = cc.category_id
                JOIN students s ON c.student_id = s.student_id
                JOIN users u ON s.student_user_id = u.user_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN staffs sf ON c.assigned_staff_id = sf.staff_id
                LEFT JOIN users su ON sf.staff_user_id = su.user_id
                ORDER BY c.created_at DESC";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all registered students
    public function getAllStudents()
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_status, 
                           students.student_registration_number, students.student_program, colleges.college_name
                    FROM users
                    JOIN students ON users.user_id = students.student_user_id
                    LEFT JOIN colleges ON students.student_college_id = colleges.college_id
                    WHERE users.user_role = 'student'
                    ORDER BY students.student_registration_number ASC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all registered staff
    public function getAllStaff()
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_status,
                       staffs.staff_id, staffs.staff_role_id,
                       departments.department_name, departments.department_id,
                       staff_roles.role_name, staff_roles.role_rank
                FROM users
                JOIN staffs ON users.user_id = staffs.staff_user_id
                LEFT JOIN departments  ON staffs.staff_department_id = departments.department_id
                LEFT JOIN staff_roles  ON staffs.staff_role_id = staff_roles.role_id
                WHERE users.user_role = 'staff'
                ORDER BY users.username ASC";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    // Get pending staff approvals
    public function getPendingStaffApprovals()
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, staffs.staff_id, staffs.staff_approval_status
                    FROM users
                    JOIN staffs ON users.user_id = staffs.staff_user_id
                    WHERE staffs.staff_approval_status = '0'
                    ORDER BY users.created_at ASC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get approved staff (includes role info for escalation dropdowns)
    public function getApprovedStaff()
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_status,
                       staffs.staff_id, staffs.staff_approval_status,
                       staffs.staff_approved_at, departments.department_name,
                       staff_roles.role_name, staff_roles.role_rank
                    FROM users
                    JOIN staffs ON users.user_id = staffs.staff_user_id
                    LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                    LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                    WHERE staffs.staff_approval_status = '1'
                    ORDER BY staff_roles.role_rank ASC, users.username ASC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get pending approvals count
    public function getPendingApprovalsCount()
    {
        $sql = "SELECT COUNT(*) as count FROM staffs WHERE staff_approval_status = '0'";
        $result = $this->conn->query($sql);
        $data = $result->fetch_assoc();

        return $data['count'];
    }

    // Approve staff
    public function approveStaff($userId, $departmentId)
    {
        try {
            $this->conn->begin_transaction();

            // Update user status to active
            $user_stmt = $this->conn->prepare("UPDATE users SET user_status = 'active' WHERE user_id = ?");
            $user_stmt->bind_param("i", $userId);
            $user_stmt->execute();
            $user_stmt->close();

            // Update staff approval status
            $staff_stmt = $this->conn->prepare("UPDATE staffs SET staff_approval_status = '1' WHERE staff_user_id = ?");
            $staff_stmt->bind_param("i", $userId);
            $staff_stmt->execute();
            $staff_stmt->close();

            // Update staff department
            if (!empty($departmentId)) {
                $staff_stmt = $this->conn->prepare("UPDATE staffs SET staff_department_id = ? WHERE staff_user_id = ?");
                $staff_stmt->bind_param("ii", $departmentId, $userId);
                $staff_stmt->execute();
                $staff_stmt->close();
            }

            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Approval error: " . $e->getMessage());
        }
    }

    // Reject staff
    public function rejectStaff($userId)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ? AND user_role = 'staff'");
            $stmt->bind_param("i", $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            throw new Exception("Rejection error: " . $e->getMessage());
        }
    }

    // Delete student
    public function deleteStudent($userId)
    {
        try {
            $this->conn->begin_transaction();

            // Delete student record
            $student_stmt = $this->conn->prepare("DELETE FROM students WHERE student_user_id = ?");
            $student_stmt->bind_param("i", $userId);
            $student_stmt->execute();
            $student_stmt->close();

            // Delete user record
            $user_stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
            $user_stmt->bind_param("i", $userId);
            $user_stmt->execute();
            $user_stmt->close();

            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Delete error: " . $e->getMessage());
        }
    }

    // Delete staff
    public function deleteStaff($userId)
    {
        try {
            $this->conn->begin_transaction();

            // Delete staff record
            $staff_stmt = $this->conn->prepare("DELETE FROM staffs WHERE staff_user_id = ?");
            $staff_stmt->bind_param("i", $userId);
            $staff_stmt->execute();
            $staff_stmt->close();

            // Delete user record
            $user_stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
            $user_stmt->bind_param("i", $userId);
            $user_stmt->execute();
            $user_stmt->close();

            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Delete error: " . $e->getMessage());
        }
    }

    // Get student by ID
    public function getStudentById($userId)
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_status,
                           students.student_registration_number, students.student_program, colleges.college_name, colleges.college_id
                    FROM users 
                    JOIN students ON users.user_id = students.student_user_id
                    LEFT JOIN colleges ON students.student_college_id = colleges.college_id
                    WHERE users.user_id = ? AND users.user_role = 'student'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    // Get staff by ID
    public function getStaffById($userId)
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_status,
                           staffs.staff_id, departments.department_name, departments.department_id
                    FROM users
                    JOIN staffs ON users.user_id = staffs.staff_user_id
                    LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                    WHERE users.user_id = ? AND users.user_role = 'staff'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId); 
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    // Get all departments for dropdown
    public function getAllDepartments()
    {
        $sql = "SELECT department_id, department_name FROM departments ORDER BY department_name ASC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all categories for dropdown
    public function getAllCategories()
    {
        $sql = "SELECT category_id, category_name FROM complaint_categories WHERE status = 'active' ORDER BY category_name ASC";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get all colleges for dropdown
    public function getAllColleges()
    {
        $sql = "SELECT college_id, college_name FROM colleges ORDER BY college_name ASC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get single complaint with full details
    public function getComplaintById($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, cc.category_name,
                    u.username AS student_name,
                    s.student_registration_number,
                    d.department_name,
                    su.username AS assigned_staff_name
             FROM complaints c
             JOIN complaint_categories cc ON c.category_id = cc.category_id
             JOIN students s ON c.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             LEFT JOIN departments d ON c.department_id = d.department_id
             LEFT JOIN staffs sf ON c.assigned_staff_id = sf.staff_id
             LEFT JOIN users su ON sf.staff_user_id = su.user_id
             WHERE c.complaint_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Get attachments for a complaint
    public function getComplaintAttachments($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get status change timeline for a complaint
    public function getComplaintStatusLogs($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT csl.*, u.username
             FROM complaint_status_logs csl
             LEFT JOIN users u ON csl.performed_by = u.user_id
             WHERE csl.complaint_id = ?
             ORDER BY csl.changed_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get internal collaboration notes for a complaint
    public function getCollaborationNotes($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT cn.*, u.username
             FROM collaboration_notes cn
             JOIN users u ON cn.created_by = u.user_id
             WHERE cn.complaint_id = ?
             ORDER BY cn.created_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get information requests for a complaint
    public function getInformationRequests($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT ir.*, u.username AS requested_by_name
             FROM information_requests ir
             JOIN users u ON ir.requested_by = u.user_id
             WHERE ir.complaint_id = ?
             ORDER BY ir.created_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Assign complaint to a staff member
    public function assignComplaint($complaintId, $staffId, $priority, $note = '')
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ?");
            $oldStmt->bind_param("i", $complaintId);
            $oldStmt->execute();
            $oldStatus = $oldStmt->get_result()->fetch_assoc()['complaint_status'];
            $oldStmt->close();

            // Deactivate any existing active assignments
            $deactivateStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET status = 'completed', completed_at = NOW()
                 WHERE complaint_id = ? AND status = 'active'"
            );
            $deactivateStmt->bind_param("i", $complaintId);
            $deactivateStmt->execute();
            $deactivateStmt->close();

            $stmt = $this->conn->prepare(
                "UPDATE complaints SET assigned_staff_id = ?, priority = ?,
                 complaint_status = 'in_progress', routed_at = NOW()
                 WHERE complaint_id = ?"
            );
            $stmt->bind_param("ssi", $staffId, $priority, $complaintId);
            $stmt->execute();
            $stmt->close();

            // Record in complaint_assignments junction table
            $adminId = $_SESSION['user_id'];
            $assignStmt = $this->conn->prepare(
                "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                 VALUES (?, ?, ?, 1, 'active', ?)"
            );
            $assignNote = !empty($note) ? $note : null;
            $assignStmt->bind_param("isis", $complaintId, $staffId, $adminId, $assignNote);
            $assignStmt->execute();
            $assignStmt->close();

            $remarks = !empty($note) ? $note : 'Complaint assigned to staff';
            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'assigned', ?, 'in_progress', ?, ?)"
            );
            $logStmt->bind_param("isis", $complaintId, $oldStatus, $adminId, $remarks);
            $logStmt->execute();
            $logStmt->close();

            if (!empty($note)) {
                $noteStmt = $this->conn->prepare(
                    "INSERT INTO collaboration_notes (complaint_id, created_by, note_text, is_internal)
                     VALUES (?, ?, ?, 1)"
                );
                $noteStmt->bind_param("iis", $complaintId, $adminId, $note);
                $noteStmt->execute();
                $noteStmt->close();
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Assignment error: " . $e->getMessage());
        }
    }

    // Get full assignment history for a complaint
    public function getComplaintAssignments($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT ca.*, u.username AS staff_name, ab.username AS assigned_by_name,
                    sr.role_name
             FROM complaint_assignments ca
             JOIN staffs s ON ca.staff_id = s.staff_id
             JOIN users u ON s.staff_user_id = u.user_id
             LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
             JOIN users ab ON ca.assigned_by = ab.user_id
             WHERE ca.complaint_id = ?
             ORDER BY ca.assigned_at DESC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Respond to a complaint (resolve or reject)
    public function respondComplaint($complaintId, $response, $newStatus)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ?");
            $oldStmt->bind_param("i", $complaintId);
            $oldStmt->execute();
            $oldStatus = $oldStmt->get_result()->fetch_assoc()['complaint_status'];
            $oldStmt->close();

            $stmt = $this->conn->prepare(
                "UPDATE complaints
                 SET complaint_response = ?, complaint_status = ?,
                     resolved_at = IF(? = 'resolved', NOW(), NULL)
                 WHERE complaint_id = ?"
            );
            $stmt->bind_param("sssi", $response, $newStatus, $newStatus, $complaintId);
            $stmt->execute();
            $stmt->close();

            $adminId = $_SESSION['user_id'];
            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'responded', ?, ?, ?, ?)"
            );
            $logStmt->bind_param("issis", $complaintId, $oldStatus, $newStatus, $adminId, $response);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Response error: " . $e->getMessage());
        }
    }

    // Delete a complaint and its uploaded files
    public function deleteComplaint($complaintId)
    {
        $pathStmt = $this->conn->prepare(
            "SELECT file_path FROM complaint_attachments WHERE complaint_id = ?"
        );
        $pathStmt->bind_param("i", $complaintId);
        $pathStmt->execute();
        $filePaths = array_column($pathStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'file_path');
        $pathStmt->close();

        $stmt = $this->conn->prepare("DELETE FROM complaints WHERE complaint_id = ?");
        $stmt->bind_param("i", $complaintId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            foreach ($filePaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            $dir = "uploads/complaints/$complaintId";
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                rmdir($dir);
            }
        }

        return $ok;
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    private function buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo): array
    {
        $conditions = [];
        $types      = '';
        $params     = [];

        if (!empty($deptId)) {
            $conditions[] = 'c.department_id = ?';
            $types .= 'i';
            $params[] = (int)$deptId;
        }
        if (!empty($categoryId)) {
            $conditions[] = 'c.category_id = ?';
            $types .= 'i';
            $params[] = (int)$categoryId;
        }
        if (!empty($dateFrom)) {
            $conditions[] = 'c.created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $conditions[] = 'c.created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        return [$where, $types, $params];
    }

    public function getReportStats($deptId = null, $categoryId = null, $dateFrom = null, $dateTo = null): array
    {
        [$where, $types, $params] = $this->buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo);

        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved,
                    SUM(c.complaint_status = 'rejected')    AS rejected,
                    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END), 1) AS avg_resolution_hours
                FROM complaints c $where";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total'               => (int)($row['total'] ?? 0),
            'pending'             => (int)($row['pending'] ?? 0),
            'in_progress'         => (int)($row['in_progress'] ?? 0),
            'resolved'            => (int)($row['resolved'] ?? 0),
            'rejected'            => (int)($row['rejected'] ?? 0),
            'avg_resolution_hours'=> $row['avg_resolution_hours'] ?? null,
        ];
    }

    public function getReportByDepartment($deptId = null, $categoryId = null, $dateFrom = null, $dateTo = null): array
    {
        [$where, $types, $params] = $this->buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo);

        $sql = "SELECT
                    COALESCE(d.department_name, 'Unassigned') AS department_name,
                    COUNT(*) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved,
                    SUM(c.complaint_status = 'rejected')    AS rejected,
                    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END), 1) AS avg_resolution_hours
                FROM complaints c
                LEFT JOIN departments d ON c.department_id = d.department_id
                $where
                GROUP BY c.department_id, d.department_name
                ORDER BY total DESC";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getReportByCategory($deptId = null, $categoryId = null, $dateFrom = null, $dateTo = null): array
    {
        [$where, $types, $params] = $this->buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo);

        $sql = "SELECT
                    cc.category_name,
                    COUNT(*) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved,
                    SUM(c.complaint_status = 'rejected')    AS rejected,
                    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END), 1) AS avg_resolution_hours
                FROM complaints c
                JOIN complaint_categories cc ON c.category_id = cc.category_id
                $where
                GROUP BY c.category_id, cc.category_name
                ORDER BY total DESC";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getReportByPriority($deptId = null, $categoryId = null, $dateFrom = null, $dateTo = null): array
    {
        [$where, $types, $params] = $this->buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo);

        $sql = "SELECT
                    c.priority,
                    COUNT(*) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved,
                    SUM(c.complaint_status = 'rejected')    AS rejected
                FROM complaints c
                $where
                GROUP BY c.priority
                ORDER BY FIELD(c.priority, 'high', 'medium', 'low')";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getReportByStaff($deptId = null, $categoryId = null, $dateFrom = null, $dateTo = null): array
    {
        [$where, $types, $params] = $this->buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo);

        $sql = "SELECT
                    u.username AS staff_name,
                    COALESCE(d.department_name, '—') AS department_name,
                    COALESCE(sr.role_name, '—') AS role_name,
                    COUNT(c.complaint_id) AS total,
                    SUM(c.complaint_status = 'pending')     AS pending,
                    SUM(c.complaint_status = 'in_progress') AS in_progress,
                    SUM(c.complaint_status = 'resolved')    AS resolved,
                    SUM(c.complaint_status = 'rejected')    AS rejected,
                    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END), 1) AS avg_resolution_hours,
                    ROUND(SUM(c.complaint_status = 'resolved') / COUNT(*) * 100, 1) AS resolution_rate
                FROM complaints c
                JOIN staffs s ON c.assigned_staff_id = s.staff_id
                JOIN users u ON s.staff_user_id = u.user_id
                LEFT JOIN departments d ON s.staff_department_id = d.department_id
                LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
                $where
                GROUP BY c.assigned_staff_id, u.username, d.department_name, sr.role_name
                ORDER BY total DESC";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getReportMonthlyTrend($dateFrom = null, $dateTo = null): array
    {
        $conditions = [];
        $types      = '';
        $params     = [];

        if (!empty($dateFrom)) {
            $conditions[] = 'created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        } elseif (empty($dateTo)) {
            // Default: last 12 months when no date range given
            $conditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)';
        }
        if (!empty($dateTo)) {
            $conditions[] = 'created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $sql = "SELECT
                    DATE_FORMAT(created_at, '%Y-%m')  AS month_key,
                    DATE_FORMAT(created_at, '%b %Y')  AS month_label,
                    COUNT(*) AS total,
                    SUM(complaint_status = 'pending')     AS pending,
                    SUM(complaint_status = 'in_progress') AS in_progress,
                    SUM(complaint_status = 'resolved')    AS resolved,
                    SUM(complaint_status = 'rejected')    AS rejected
                FROM complaints
                $where
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month_key ASC";

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getOldestPendingComplaints(int $limit = 10): array
    {
        $sql = "SELECT c.complaint_id, c.complaint_title, c.priority,
                       TIMESTAMPDIFF(DAY, c.created_at, NOW()) AS days_pending,
                       cc.category_name,
                       COALESCE(d.department_name, 'Unassigned') AS department_name,
                       CASE WHEN c.is_anonymous = 1 THEN 'Anonymous'
                            ELSE u.username END AS student_name
                FROM complaints c
                JOIN complaint_categories cc ON c.category_id = cc.category_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                JOIN students st ON c.student_id = st.student_id
                JOIN users u ON st.student_user_id = u.user_id
                WHERE c.complaint_status = 'pending'
                ORDER BY c.created_at ASC
                LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // ── Staff Roles CRUD ─────────────────────────────────────────────────────

    public function getAllStaffRolesWithCount()
    {
        $sql = "SELECT sr.role_id, sr.role_name, sr.role_rank,
                       COUNT(s.staff_id) AS staff_count
                FROM staff_roles sr
                LEFT JOIN staffs s ON sr.role_id = s.staff_role_id
                GROUP BY sr.role_id, sr.role_name, sr.role_rank
                ORDER BY sr.role_rank ASC";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function addStaffRole($name, $rank)
    {
        $chk = $this->conn->prepare("SELECT role_id FROM staff_roles WHERE role_rank = ?");
        $chk->bind_param("i", $rank);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            throw new Exception("A role with rank {$rank} already exists.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("INSERT INTO staff_roles (role_name, role_rank) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $rank);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateStaffRole($id, $name, $rank)
    {
        $chk = $this->conn->prepare("SELECT role_id FROM staff_roles WHERE role_rank = ? AND role_id != ?");
        $chk->bind_param("ii", $rank, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            throw new Exception("A role with rank {$rank} already exists.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("UPDATE staff_roles SET role_name = ?, role_rank = ? WHERE role_id = ?");
        $stmt->bind_param("sii", $name, $rank, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteStaffRole($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM staffs WHERE staff_role_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int)$chk->get_result()->fetch_assoc()['cnt'] > 0) {
            $chk->close();
            throw new Exception("Cannot delete: role is assigned to one or more staff members.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("DELETE FROM staff_roles WHERE role_id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function assignStaffRole($staffUserId, $roleId)
    {
        $stmt = $this->conn->prepare("UPDATE staffs SET staff_role_id = ? WHERE staff_user_id = ?");
        $roleVal = $roleId ?: null;
        $stmt->bind_param("ii", $roleVal, $staffUserId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Departments CRUD ─────────────────────────────────────────────────────

    public function getAllDepartmentsWithStats()
    {
        $sql = "SELECT d.department_id, d.department_name,
                       COUNT(DISTINCT c.complaint_id)  AS complaint_count,
                       COUNT(DISTINCT s.staff_id)             AS staff_count
                FROM departments d
                LEFT JOIN complaints c ON d.department_id = c.department_id
                LEFT JOIN staffs s    ON d.department_id = s.staff_department_id
                                     AND s.staff_approval_status = 1
                GROUP BY d.department_id, d.department_name
                ORDER BY d.department_name ASC";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function addDepartment($name)
    {
        $stmt = $this->conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateDepartment($id, $name)
    {
        $stmt = $this->conn->prepare("UPDATE departments SET department_name = ? WHERE department_id = ?");
        $stmt->bind_param("si", $name, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteDepartment($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM staffs WHERE staff_department_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int)$chk->get_result()->fetch_assoc()['cnt'] > 0) {
            $chk->close();
            throw new Exception("Cannot delete: department still has assigned staff members.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Categories CRUD ───────────────────────────────────────────────────────

    public function getAllCategoriesWithStats()
    {
        $sql = "SELECT cc.category_id, cc.category_name, cc.category_description, cc.status,
                       COUNT(c.complaint_id) AS complaint_count
                FROM complaint_categories cc
                LEFT JOIN complaints c ON cc.category_id = c.category_id
                GROUP BY cc.category_id
                ORDER BY cc.category_name ASC";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function addCategory($name, $description, $createdBy)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_categories (category_name, category_description, created_by) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("ssi", $name, $description, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateCategory($id, $name, $description, $status)
    {
        $stmt = $this->conn->prepare(
            "UPDATE complaint_categories SET category_name = ?, category_description = ?, status = ? WHERE category_id = ?"
        );
        $stmt->bind_param("sssi", $name, $description, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteCategory($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE category_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int)$chk->get_result()->fetch_assoc()['cnt'] > 0) {
            $chk->close();
            throw new Exception("Cannot delete: category has associated complaints.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("DELETE FROM complaint_categories WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Subcategories CRUD ────────────────────────────────────────────────────

    public function getAllSubcategoriesGrouped()
    {
        $sql = "SELECT cs.subcategory_id, cs.category_id, cs.subcategory_name,
                       cs.subcategory_description, cs.status,
                       COUNT(c.complaint_id) AS complaint_count
                FROM complaint_subcategories cs
                LEFT JOIN complaints c ON cs.subcategory_id = c.subcategory_id
                GROUP BY cs.subcategory_id, cs.category_id, cs.subcategory_name,
                         cs.subcategory_description, cs.status
                ORDER BY cs.category_id ASC, cs.subcategory_name ASC";
        $rows = $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['category_id']][] = $row;
        }
        return $grouped;
    }

    public function addSubcategory($categoryId, $name, $description, $createdBy)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_subcategories (category_id, subcategory_name, subcategory_description, created_by)
             VALUES (?, ?, ?, ?)"
        );
        $desc = $description ?: null;
        $stmt->bind_param("issi", $categoryId, $name, $desc, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateSubcategory($id, $name, $description, $status)
    {
        $stmt = $this->conn->prepare(
            "UPDATE complaint_subcategories
             SET subcategory_name = ?, subcategory_description = ?, status = ?
             WHERE subcategory_id = ?"
        );
        $desc = $description ?: null;
        $stmt->bind_param("sssi", $name, $desc, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteSubcategory($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE subcategory_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int)$chk->get_result()->fetch_assoc()['cnt'] > 0) {
            $chk->close();
            throw new Exception("Cannot delete: subcategory has associated complaints.");
        }
        $chk->close();
        $stmt = $this->conn->prepare("DELETE FROM complaint_subcategories WHERE subcategory_id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Add user accounts (admin-created) ────────────────────────────────────

    public function addStudent($username, $email, $password, $regNumber, $collegeId)
    {
        $this->conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $this->conn->prepare(
                "INSERT INTO users (username, user_email, user_password, user_role, user_status) VALUES (?, ?, ?, 'student', 'active')"
            );
            $u->bind_param("sss", $username, $email, $hash);
            $u->execute();
            $userId = $this->conn->insert_id;
            $u->close();

            $s = $this->conn->prepare(
                "INSERT INTO students (student_user_id, student_registration_number, student_college_id) VALUES (?, ?, ?)"
            );
            $s->bind_param("isi", $userId, $regNumber, $collegeId);
            $s->execute();
            $s->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Add student error: " . $e->getMessage());
        }
    }

    public function addStaffAccount($username, $email, $password, $departmentId)
    {
        $this->conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $this->conn->prepare(
                "INSERT INTO users (username, user_email, user_password, user_role, user_status) VALUES (?, ?, ?, 'staff', 'active')"
            );
            $u->bind_param("sss", $username, $email, $hash);
            $u->execute();
            $userId = $this->conn->insert_id;
            $u->close();

            $deptId = $departmentId ?: null;
            $s = $this->conn->prepare(
                "INSERT INTO staffs (staff_user_id, staff_department_id, staff_approval_status) VALUES (?, ?, 1)"
            );
            $s->bind_param("ii", $userId, $deptId);
            $s->execute();
            $s->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Add staff error: " . $e->getMessage());
        }
    }

    // ── Collaboration / info ──────────────────────────────────────────────────

    // Add an internal collaboration note
    public function addCollaborationNote($complaintId, $createdBy, $noteText)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO collaboration_notes (complaint_id, created_by, note_text, is_internal)
             VALUES (?, ?, ?, 1)"
        );
        $stmt->bind_param("iis", $complaintId, $createdBy, $noteText);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Request more information from the student
    public function requestInformation($complaintId, $requestedBy, $message)
    {
        try {
            $this->conn->begin_transaction();

            $irStmt = $this->conn->prepare(
                "INSERT INTO information_requests (complaint_id, requested_by, request_message)
                 VALUES (?, ?, ?)"
            );
            $irStmt->bind_param("iis", $complaintId, $requestedBy, $message);
            $irStmt->execute();
            $irStmt->close();

            $oldStmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ?");
            $oldStmt->bind_param("i", $complaintId);
            $oldStmt->execute();
            $oldStatus = $oldStmt->get_result()->fetch_assoc()['complaint_status'];
            $oldStmt->close();

            $statusStmt = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'awaiting_student_response' WHERE complaint_id = ?"
            );
            $statusStmt->bind_param("i", $complaintId);
            $statusStmt->execute();
            $statusStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'info_requested', ?, 'awaiting_student_response', ?, ?)"
            );
            $logStmt->bind_param("isis", $complaintId, $oldStatus, $requestedBy, $message);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Info request error: " . $e->getMessage());
        }
    }
}
