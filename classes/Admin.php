<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Notification.php';

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
        $stmt = $this->conn->prepare("SELECT user_role, COUNT(*) AS total FROM users GROUP BY user_role");
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $counts = ['student' => 0, 'student_leader' => 0, 'staff' => 0, 'admin' => 0];
        foreach ($rows as $row) {
            $counts[$row['user_role']] = (int) $row['total'];
        }
        return $counts;
    }

    public function getTotalAwaiting(): int
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE complaint_status = 'awaiting_student_response'");
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['cnt'];
    }

    public function getUnassignedCount(): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS cnt
             FROM complaints c
             WHERE c.complaint_status = 'pending'
               AND NOT EXISTS (
                   SELECT 1 FROM complaint_assignments ca
                   WHERE ca.complaint_id = c.complaint_id AND ca.status = 'active'
               )"
        );
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['cnt'];
    }

    public function getPendingStaffCount()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM staffs WHERE staff_approval_status = 0");
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['cnt'];
    }

    public function getComplaints(int $limit = 0)
    {
        $sql = "SELECT c.*,
                    (SELECT COUNT(*) FROM complaint_endorsements ce WHERE ce.complaint_id = c.complaint_id) AS endorsement_count,
                    cc.category_name, cc.auto_assign_department_id AS category_dept_id,
                    u.username AS student_name,
                    s.student_registration_number,
                    d.department_name,
                    su.username AS assigned_staff_name,
                    ca_lead.target_resolution_date
                FROM complaints c
                JOIN complaint_categories cc ON c.category_id = cc.category_id
                JOIN students s ON c.student_id = s.student_id
                JOIN users u ON s.student_user_id = u.user_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN complaint_assignments ca_lead ON c.complaint_id = ca_lead.complaint_id AND ca_lead.status = 'active' AND ca_lead.is_lead = 1
                LEFT JOIN staffs sf ON ca_lead.staff_id = sf.staff_id
                LEFT JOIN users su ON sf.staff_user_id = su.user_id
                ORDER BY c.created_at DESC";
        if ($limit > 0) {
            $sql .= " LIMIT ?";
        }
        $stmt = $this->conn->prepare($sql);
        if ($limit > 0) {
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get all registered students
    public function getAllStudents()
    {
        $stmt = $this->conn->prepare(
            "SELECT users.user_id, users.username, users.user_email, users.user_phone_number, users.user_status,
                    students.student_registration_number, students.student_program, colleges.college_name
                 FROM users
                 JOIN students ON users.user_id = students.student_user_id
                 LEFT JOIN colleges ON students.student_college_id = colleges.college_id
                 WHERE users.user_role IN ('student', 'student_leader')
                 ORDER BY students.student_registration_number ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get all registered staff
    public function getAllStaff()
    {
        $stmt = $this->conn->prepare(
            "SELECT users.user_id, users.username, users.user_email, users.user_phone_number, users.user_status,
                    staffs.staff_id, staffs.staff_role_id, staffs.staff_approved_at,
                    departments.department_name, departments.department_id,
                    staff_roles.role_name, staff_roles.role_rank,
                    approver.username AS approved_by_name
                 FROM users
                 JOIN staffs ON users.user_id = staffs.staff_user_id
                 LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                 LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                 LEFT JOIN users approver ON staffs.staff_approved_by = approver.user_id
                 WHERE users.user_role = 'staff'
                 ORDER BY users.username ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    // Get pending staff approvals
    public function getPendingStaffApprovals()
    {
        $stmt = $this->conn->prepare(
            "SELECT users.user_id, users.username, users.user_email,
                    users.user_phone_number, users.created_at,
                    staffs.staff_id, staffs.staff_approval_status,
                    staffs.staff_department_id,
                    departments.department_name
                 FROM users
                 JOIN staffs ON users.user_id = staffs.staff_user_id
                 LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                 WHERE staffs.staff_approval_status = '0'
                 ORDER BY users.created_at ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }

    // Get approved staff (includes role info for escalation dropdowns)
    public function getApprovedStaff()
    {
        $stmt = $this->conn->prepare(
            "SELECT users.user_id, users.username, users.user_email, users.user_phone_number, users.user_status,
                    staffs.staff_id, staffs.staff_department_id, staffs.staff_approval_status,
                    staffs.staff_approved_at, departments.department_name,
                    staff_roles.role_name, staff_roles.role_rank,
                    approver.username AS approved_by_name
                 FROM users
                 JOIN staffs ON users.user_id = staffs.staff_user_id
                 LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                 LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                 LEFT JOIN users approver ON staffs.staff_approved_by = approver.user_id
                 WHERE staffs.staff_approval_status = '1'
                 ORDER BY staff_roles.role_rank ASC, users.username ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    // Get pending approvals count
    public function getPendingApprovalsCount()
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS count FROM staffs WHERE staff_approval_status = '0'");
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        return $count;
    }

    // Approve staff
    public function approveStaff($userId, $departmentId, $roleId = null)
    {
        try {
            $this->conn->begin_transaction();

            $user_stmt = $this->conn->prepare("UPDATE users SET user_status = 'active' WHERE user_id = ?");
            $user_stmt->bind_param("i", $userId);
            $user_stmt->execute();
            $user_stmt->close();

            $approverId = $_SESSION['user_id'];
            $staff_stmt = $this->conn->prepare("UPDATE staffs SET staff_approval_status = '1', staff_approved_at = NOW(), staff_approved_by = ? WHERE staff_user_id = ?");
            $staff_stmt->bind_param("ii", $approverId, $userId);
            $staff_stmt->execute();
            $staff_stmt->close();

            if (!empty($departmentId)) {
                $dept_stmt = $this->conn->prepare("UPDATE staffs SET staff_department_id = ? WHERE staff_user_id = ?");
                $dept_stmt->bind_param("ii", $departmentId, $userId);
                $dept_stmt->execute();
                $dept_stmt->close();
            }

            if (!empty($roleId)) {
                $role_stmt = $this->conn->prepare("UPDATE staffs SET staff_role_id = ? WHERE staff_user_id = ?");
                $role_stmt->bind_param("ii", $roleId, $userId);
                $role_stmt->execute();
                $role_stmt->close();
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Approval error: " . $e->getMessage());
        }

        try {
            (new Notification($this->conn))->create(
                $userId,
                "Your staff account has been approved. You can now access your dashboard.",
                'staff_approved',
                'staff_dashboard.php'
            );
        } catch (Throwable $e) {
            error_log('[approveStaff] Notification failed: ' . $e->getMessage());
        }

        return true;
    }

    // Reject staff
    public function rejectStaff($userId)
    {
        try {
            // Fetch email/name before deleting so we can notify the user
            $uStmt = $this->conn->prepare("SELECT username, user_email FROM users WHERE user_id = ? AND user_role = 'staff' LIMIT 1");
            $uStmt->bind_param("i", $userId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ? AND user_role = 'staff'");
            $stmt->bind_param("i", $userId);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok && $uRow && !empty($uRow['user_email'])) {
                try {
                    require_once __DIR__ . '/Mailer.php';
                    $body = Mailer::buildBody(
                        $uRow['username'],
                        "Unfortunately, your staff account registration has not been approved. Please contact the administrator for more information.",
                        null
                    );
                    EmailQueue::enqueue($this->conn, $uRow['user_email'], $uRow['username'], 'Your Staff Account Was Not Approved', $body);
                } catch (Throwable $e) {
                    error_log('[rejectStaff] Email notification failed: ' . $e->getMessage());
                }
            }

            return $ok;
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
        $stmt = $this->conn->prepare("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get all categories for dropdown
    public function getAllCategories()
    {
        $stmt = $this->conn->prepare("SELECT category_id, category_name FROM complaint_categories WHERE status = 'active' ORDER BY category_name ASC");
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get all colleges for dropdown
    public function getAllColleges()
    {
        $stmt = $this->conn->prepare("SELECT college_id, college_name FROM colleges ORDER BY college_name ASC");
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get single complaint with full details
    public function getComplaintById($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, cc.category_name,
                    u.username AS student_name,
                    u.user_email AS student_email,
                    u.user_phone_number AS student_phone,
                    s.student_registration_number,
                    d.department_name,
                    su.username AS assigned_staff_name
             FROM complaints c
             JOIN complaint_categories cc ON c.category_id = cc.category_id
             JOIN students s ON c.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             LEFT JOIN departments d ON c.department_id = d.department_id
             LEFT JOIN complaint_assignments ca_lead ON c.complaint_id = ca_lead.complaint_id AND ca_lead.status = 'active' AND ca_lead.is_lead = 1
             LEFT JOIN staffs sf ON ca_lead.staff_id = sf.staff_id
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
        $stmt = $this->conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC");
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
    public function assignComplaint($complaintId, $staffId, $priority, $note = '', $assignedByUserId = null)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ?");
            $oldStmt->bind_param("i", $complaintId);
            $oldStmt->execute();
            $oldStatus = $oldStmt->get_result()->fetch_assoc()['complaint_status'];
            $oldStmt->close();

            // Look up the staff member's department for routing route the complaint 
            $deptStmt = $this->conn->prepare("SELECT staff_department_id FROM staffs WHERE staff_id = ? LIMIT 1");
            $deptStmt->bind_param("s", $staffId);
            $deptStmt->execute();
            $deptRow = $deptStmt->get_result()->fetch_assoc();
            $deptStmt->close();
            $staffDeptId = $deptRow ? $deptRow['staff_department_id'] : null;

            // Deactivate any existing active assignments
            $deactivateStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET status = 'completed', completed_at = NOW()
                 WHERE complaint_id = ? AND status = 'active'"
            );
            $deactivateStmt->bind_param("i", $complaintId);
            $deactivateStmt->execute();
            $deactivateStmt->close();

            $stmt = $this->conn->prepare(
                "UPDATE complaints SET priority = ?,
                 complaint_status = 'in_progress', routed_at = NOW(),
                 department_id = COALESCE(?, department_id)
                 WHERE complaint_id = ?"
            );
            $stmt->bind_param("sii", $priority, $staffDeptId, $complaintId);
            $stmt->execute();
            $stmt->close();

            // Record in complaint_assignments table
            $adminId = $assignedByUserId ?? $_SESSION['user_id'];
            $assignStmt = $this->conn->prepare(
                "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                 VALUES (?, ?, ?, 1, 'active', ?)"
            );
            $assignNote = !empty($note) ? $note : null;
            $assignStmt->bind_param("isis", $complaintId, $staffId, $adminId, $assignNote);
            $assignStmt->execute();
            $assignStmt->close();

            // Auto-set SLA deadline: high=2 days, medium=5, low=10
            $slaDays = ['high' => 2, 'medium' => 5, 'low' => 10][$priority] ?? 5;
            $slaStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET target_resolution_date = DATE_ADD(NOW(), INTERVAL ? DAY)
                 WHERE complaint_id = ? AND status = 'active' AND is_lead = 1"
            );
            $slaStmt->bind_param('ii', $slaDays, $complaintId);
            $slaStmt->execute();
            $slaStmt->close();

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

            $notif = new Notification($this->conn);

            // Notify the assigned staff member
            $sStmt = $this->conn->prepare("SELECT staff_user_id FROM staffs WHERE staff_id = ? LIMIT 1");
            $sStmt->bind_param('s', $staffId);
            $sStmt->execute();
            $sRow = $sStmt->get_result()->fetch_assoc();
            $sStmt->close();
            if ($sRow) {
                $notif->create($sRow['staff_user_id'], "Complaint #$complaintId has been assigned to you.", 'new_assignment', "assigned_complaint_details.php?id=$complaintId", $complaintId);
            }

            // Notify the student their complaint is being handled
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                $notif->create($studRow['user_id'], "Your complaint #$complaintId is now being reviewed by a staff member.", 'status_change', "student_complaint_details.php?id=$complaintId", $complaintId);
            }

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

            // Notify the student
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                $type = $newStatus === STATUS_RESOLVED ? 'complaint_resolved' : 'complaint_rejected';
                $msg = $newStatus === STATUS_RESOLVED
                    ? "Your complaint #$complaintId has been resolved."
                    : "Your complaint #$complaintId has been rejected.";
                (new Notification($this->conn))->create($studRow['user_id'], $msg, $type, "student_complaint_details.php?id=$complaintId", $complaintId);
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Response error: " . $e->getMessage());
        }
    }

    // Delete a pending complaint and its uploaded files
    public function deleteComplaint($complaintId, $reason = '')
    {
        $checkStmt = $this->conn->prepare(
            "SELECT c.complaint_status, u.user_id
             FROM complaints c
             JOIN students s ON c.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             WHERE c.complaint_id = ?"
        );
        $checkStmt->bind_param("i", $complaintId);
        $checkStmt->execute();
        $row = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$row) {
            throw new Exception("Complaint not found.");
        }
        if ($row['complaint_status'] !== STATUS_PENDING) {
            throw new Exception("Only pending complaints can be deleted.");
        }

        $studentUserId = $row['user_id'];

        $pathStmt = $this->conn->prepare(
            "SELECT file_path FROM complaint_attachments WHERE complaint_id = ?"
        );
        $pathStmt->bind_param("i", $complaintId);
        $pathStmt->execute();
        $filePaths = array_column($pathStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'file_path');
        $pathStmt->close();

        // Soft delete: Update status to 'deleted' instead of hard delete
        $stmt = $this->conn->prepare("UPDATE complaints SET complaint_status = 'deleted', updated_at = NOW() WHERE complaint_id = ?");
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

            $notif = new Notification($this->conn);
            $reasonText = $reason ? " Reason: $reason" : '';
            $notif->create($studentUserId, "Your complaint #$complaintId has been deleted by an administrator.$reasonText", 'complaint_deleted', 'track_complaints.php', null);
            $notif->notifyAllAdmins("Complaint #$complaintId was deleted by an administrator.$reasonText", 'complaint_deleted', 'manage_complaints.php', null);
        }

        return $ok;
    }

    // Reports 

    private function buildReportFilters($deptId, $categoryId, $dateFrom, $dateTo): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if (!empty($deptId)) {
            $conditions[] = 'c.department_id = ?';
            $types .= 'i';
            $params[] = (int) $deptId;
        }
        if (!empty($categoryId)) {
            $conditions[] = 'c.category_id = ?';
            $types .= 'i';
            $params[] = (int) $categoryId;
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
            'total' => (int) ($row['total'] ?? 0),
            STATUS_PENDING => (int) ($row['pending'] ?? 0),
            STATUS_IN_PROGRESS => (int) ($row['in_progress'] ?? 0),
            STATUS_RESOLVED => (int) ($row['resolved'] ?? 0),
            STATUS_REJECTED => (int) ($row['rejected'] ?? 0),
            'avg_resolution_hours' => $row['avg_resolution_hours'] ?? null,
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
                JOIN complaint_assignments ca_lead ON c.complaint_id = ca_lead.complaint_id AND ca_lead.status = 'active' AND ca_lead.is_lead = 1
                JOIN staffs s ON ca_lead.staff_id = s.staff_id
                JOIN users u ON s.staff_user_id = u.user_id
                LEFT JOIN departments d ON s.staff_department_id = d.department_id
                LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
                $where
                GROUP BY ca_lead.staff_id, u.username, d.department_name, sr.role_name
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
        $types = '';
        $params = [];

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

    // Staff Roles CRUD 

    public function getAllStaffRoles()
    {
        $stmt = $this->conn->prepare("SELECT role_id, role_name FROM staff_roles ORDER BY role_rank DESC, role_name ASC");
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getAllStaffRolesWithCount()
    {
        $stmt = $this->conn->prepare(
            "SELECT sr.role_id, sr.role_name, sr.role_rank,
                    COUNT(s.staff_id) AS staff_count
                 FROM staff_roles sr
                 LEFT JOIN staffs s ON sr.role_id = s.staff_role_id
                 GROUP BY sr.role_id, sr.role_name, sr.role_rank
                 ORDER BY sr.role_rank ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
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
        if ((int) $chk->get_result()->fetch_assoc()['cnt'] > 0) {
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

    // Departments CRUD 

    public function getAllDepartmentsWithStats()
    {
        $stmt = $this->conn->prepare(
            "SELECT d.department_id, d.department_name,
                    COUNT(DISTINCT c.complaint_id) AS complaint_count,
                    COUNT(DISTINCT s.staff_id) AS staff_count
                 FROM departments d
                 LEFT JOIN complaints c ON d.department_id = c.department_id
                 LEFT JOIN staffs s ON d.department_id = s.staff_department_id
                                    AND s.staff_approval_status = 1
                 GROUP BY d.department_id, d.department_name
                 ORDER BY d.department_name ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
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
        if ((int) $chk->get_result()->fetch_assoc()['cnt'] > 0) {
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

    public function getDepartmentById($id)
    {
        $stmt = $this->conn->prepare("SELECT department_id, department_name FROM departments WHERE department_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // Approved staff of a department, ordered highest rank first, for a hierarchy/escalation-chain view
    public function getDepartmentStaffHierarchy($departmentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT users.user_id, users.username, users.user_email,
                    staffs.staff_id, staffs.staff_role_id,
                    staff_roles.role_name, staff_roles.role_rank
                 FROM staffs
                 JOIN users ON staffs.staff_user_id = users.user_id
                 LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                 WHERE staffs.staff_department_id = ?
                   AND staffs.staff_approval_status = 1
                 ORDER BY COALESCE(staff_roles.role_rank, -1) DESC, users.username ASC"
        );
        $stmt->bind_param("i", $departmentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Group into tiers by rank so equal-rank staff render side by side
        $tiers = [];
        foreach ($data as $row) {
            $key = $row['staff_role_id'] ?? 'unassigned';
            if (!isset($tiers[$key])) {
                $tiers[$key] = [
                    'role_name' => $row['role_name'] ?? 'No Role Assigned',
                    'role_rank' => $row['role_rank'] ?? null,
                    'staff' => [],
                ];
            }
            $tiers[$key]['staff'][] = $row;
        }

        return array_values($tiers);
    }

    // Categories CRUD

    public function getAllCategoriesWithStats()
    {
        $stmt = $this->conn->prepare(
            "SELECT cc.category_id, cc.category_name, cc.category_description, cc.status,
                    cc.requires_department_selection, cc.auto_assign_department_id, cc.default_role_id,
                    d.department_name AS default_dept_name,
                    sr.role_name AS default_role_name,
                    COUNT(c.complaint_id) AS complaint_count
                 FROM complaint_categories cc
                 LEFT JOIN complaints c ON cc.category_id = c.category_id
                 LEFT JOIN departments d ON cc.auto_assign_department_id = d.department_id
                 LEFT JOIN staff_roles sr ON cc.default_role_id = sr.role_id
                 GROUP BY cc.category_id, d.department_name, sr.role_name
                 ORDER BY cc.category_name ASC"
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function addCategory($name, $description, $createdBy, $departmentId = null, $requiresDeptSelection = 0, $defaultRoleId = null)
    {
        $deptId = ($departmentId > 0) ? (int) $departmentId : null;
        $roleId = ($defaultRoleId > 0) ? (int) $defaultRoleId : null;
        $reqDept = $requiresDeptSelection ? 1 : 0;
        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_categories
                (category_name, category_description, requires_department_selection, auto_assign_department_id, default_role_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssiiii", $name, $description, $reqDept, $deptId, $roleId, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateCategory($id, $name, $description, $status, $departmentId = null, $requiresDeptSelection = 0, $defaultRoleId = null)
    {
        $deptId = ($departmentId > 0) ? (int) $departmentId : null;
        $roleId = ($defaultRoleId > 0) ? (int) $defaultRoleId : null;
        $reqDept = $requiresDeptSelection ? 1 : 0;
        $stmt = $this->conn->prepare(
            "UPDATE complaint_categories
             SET category_name = ?, category_description = ?, status = ?,
                 requires_department_selection = ?, auto_assign_department_id = ?, default_role_id = ?
             WHERE category_id = ?"
        );
        $stmt->bind_param("sssiiii", $name, $description, $status, $reqDept, $deptId, $roleId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteCategory($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE category_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int) $chk->get_result()->fetch_assoc()['cnt'] > 0) {
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

    // Subcategories CRUD 

    public function getAllSubcategoriesGrouped()
    {
        $stmt = $this->conn->prepare(
            "SELECT cs.subcategory_id, cs.category_id, cs.subcategory_name,
                    cs.subcategory_description, cs.status, cs.default_role_id,
                    sr.role_name AS default_role_name,
                    COUNT(c.complaint_id) AS complaint_count
                 FROM complaint_subcategories cs
                 LEFT JOIN complaints c ON cs.subcategory_id = c.subcategory_id
                 LEFT JOIN staff_roles sr ON cs.default_role_id = sr.role_id
                 GROUP BY cs.subcategory_id, cs.category_id, cs.subcategory_name,
                          cs.subcategory_description, cs.status, cs.default_role_id, sr.role_name
                 ORDER BY cs.category_id ASC, cs.subcategory_name ASC"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['category_id']][] = $row;
        }
        return $grouped;
    }

    public function addSubcategory($categoryId, $name, $description, $createdBy, $defaultRoleId = null)
    {
        $roleId = ($defaultRoleId > 0) ? (int) $defaultRoleId : null;
        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_subcategories (category_id, subcategory_name, subcategory_description, default_role_id, created_by)
             VALUES (?, ?, ?, ?, ?)"
        );
        $desc = $description ?: null;
        $stmt->bind_param("issii", $categoryId, $name, $desc, $roleId, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateSubcategory($id, $name, $description, $status, $defaultRoleId = null)
    {
        $roleId = ($defaultRoleId > 0) ? (int) $defaultRoleId : null;
        $stmt = $this->conn->prepare(
            "UPDATE complaint_subcategories
             SET subcategory_name = ?, subcategory_description = ?, status = ?, default_role_id = ?
             WHERE subcategory_id = ?"
        );
        $desc = $description ?: null;
        $stmt->bind_param("sssii", $name, $desc, $status, $roleId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteSubcategory($id)
    {
        $chk = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE subcategory_id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        if ((int) $chk->get_result()->fetch_assoc()['cnt'] > 0) {
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

    // Add user accounts (admin-created)

    public function addStudent($username, $email, $password, $regNumber, $collegeId, $phone = null, $program = null)
    {
        $this->conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $this->conn->prepare(
                "INSERT INTO users (username, user_email, user_phone_number, user_password, user_role, user_status)
                 VALUES (?, ?, ?, ?, 'student', 'active')"
            );
            $u->bind_param("ssss", $username, $email, $phone, $hash);
            $u->execute();
            $userId = $this->conn->insert_id;
            $u->close();

            $prog = $program ?: null;
            $col = $collegeId ?: null;
            $s = $this->conn->prepare(
                "INSERT INTO students (student_user_id, student_registration_number, student_college_id, student_program)
                 VALUES (?, ?, ?, ?)"
            );
            $s->bind_param("isis", $userId, $regNumber, $col, $prog);
            $s->execute();
            $s->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Add student error: " . $e->getMessage());
        }
    }

    public function addStaffAccount($username, $email, $password, $departmentId, $staffId = null, $phone = null, $roleId = null)
    {
        $this->conn->begin_transaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $this->conn->prepare(
                "INSERT INTO users (username, user_email, user_phone_number, user_password, user_role, user_status)
                 VALUES (?, ?, ?, ?, 'staff', 'active')"
            );
            $u->bind_param("ssss", $username, $email, $phone, $hash);
            $u->execute();
            $userId = $this->conn->insert_id;
            $u->close();

            $deptId = $departmentId ?: null;
            $rId = $roleId ?: null;
            $sId = $staffId ?: null;
            $s = $this->conn->prepare(
                "INSERT INTO staffs (staff_id, staff_user_id, staff_department_id, staff_role_id, staff_approval_status)
                 VALUES (?, ?, ?, ?, 1)"
            );
            $s->bind_param("siii", $sId, $userId, $deptId, $rId);
            $s->execute();
            $s->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Add staff error: " . $e->getMessage());
        }
    }

    // Get student feedback for a resolved complaint
    public function getComplaintFeedback($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT cf.*, u.username AS student_name
             FROM complaint_feedback cf
             JOIN students s ON cf.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             WHERE cf.complaint_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Account management

    // Reset any user's password without requiring their current password
    public function adminResetPassword($userId, $newPassword)
    {
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters.");
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE users SET user_password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    // Write a row to activity_logs 
    public function logActivity($adminId, $action, $targetType, $targetId, $targetName, $details = null)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->conn->prepare(
            "INSERT INTO activity_logs (admin_id, action, target_type, target_id, target_name, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt)
            return;
        $stmt->bind_param("ississs", $adminId, $action, $targetType, $targetId, $targetName, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }

    // Paginated activity logs with optional filters
    public function getActivityLogs(int $page = 1, int $limit = 50, ?string $action = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $types = '';
        $params = [];

        if ($action) {
            $conditions[] = 'al.action = ?';
            $types .= 's';
            $params[] = $action;
        }
        if ($dateFrom) {
            $conditions[] = 'al.created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $conditions[] = 'al.created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT al.*, u.username AS admin_name
                  FROM activity_logs al
                  JOIN users u ON al.admin_id = u.user_id
                  $where
                  ORDER BY al.created_at DESC
                  LIMIT ? OFFSET ?";

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return [];
        if ($params)
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function getActivityLogsCount(?string $action = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($action) {
            $conditions[] = 'action = ?';
            $types .= 's';
            $params[] = $action;
        }
        if ($dateFrom) {
            $conditions[] = 'created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $conditions[] = 'created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) AS cnt FROM activity_logs $where";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return 0;
        if ($params)
            $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $cnt = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Collaboration / info

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

    // Detect newly-overdue complaints and notify all admins once per complaint.
    public function notifyOverdueComplaints(): int
    {
        $stmt = $this->conn->prepare(
            "SELECT ca.complaint_id, c.complaint_title, s.staff_user_id
             FROM complaint_assignments ca
             JOIN complaints c ON ca.complaint_id = c.complaint_id
             JOIN staffs s ON ca.staff_id = s.staff_id
             WHERE ca.status = 'active'
               AND ca.target_resolution_date IS NOT NULL
               AND ca.target_resolution_date < NOW()
               AND ca.overdue_notified = 0
               AND c.complaint_status NOT IN ('resolved','rejected','deleted')"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows))
            return 0;

        $notif = new Notification($this->conn);

        foreach ($rows as $row) {
            $cid = (int) $row['complaint_id'];
            $title = $row['complaint_title'];
            $staffUserId = (int) $row['staff_user_id'];

            $notif->notifyAllAdmins(
                "Complaint #$cid \"{$title}\" has passed its resolution deadline.",
                'complaint_overdue',
                "complaint_details.php?id=$cid",
                $cid
            );

            $notif->create(
                $staffUserId,
                "Complaint #$cid \"{$title}\" has passed its resolution deadline. Please take action or escalate.",
                'complaint_overdue',
                "assigned_complaint_details.php?id=$cid",
                $cid
            );

            $upStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET overdue_notified = 1
                 WHERE complaint_id = ? AND status = 'active'"
            );
            $upStmt->bind_param('i', $cid);
            $upStmt->execute();
            $upStmt->close();
        }

        return count($rows);
    }
}
