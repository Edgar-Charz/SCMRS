<?php
class Staff 
{

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }


    public function getStaffDetailsByUserId($userId)
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_role, users.user_status,
                       staffs.staff_id, staffs.staff_department_id, staffs.staff_approval_status,
                       staffs.staff_approved_by, staffs.staff_approved_at,
                       departments.department_name, 
                       staff_roles.role_id, staff_roles.role_name, staff_roles.role_rank
                FROM users
                INNER JOIN staffs ON users.user_id = staffs.staff_user_id
                LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                WHERE users.user_id = ? AND users.user_role = 'staff'
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    public function isStaffApproved($userId)
    {
        $sql = "SELECT staffs.staff_approval_status
                FROM staffs 
                INNER JOIN users ON staffs.staff_user_id = users.user_id
                WHERE users.user_id = ? AND users.user_role = 'staff'
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row && (int) $row['staff_approval_status'] === 1;
    }

    public function getStaffComplaintCounts($staffId)
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN complaint_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN complaint_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN complaint_status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN complaint_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM complaints
                WHERE assigned_staff_id = ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'rejected' => 0,
            ];
        }

        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'resolved' => (int) ($row['resolved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ];
    }

    public function getRecentAssignedComplaints($staffId, $limit = 5)
    {
        $sql = "SELECT complaints.complaint_id, complaints.complaint_title, complaints.complaint_status, complaints.created_at,
                       complaint_categories.category_name
                FROM complaints
                LEFT JOIN complaint_categories ON complaints.category_id = complaint_categories.category_id
                WHERE complaints.assigned_staff_id = ?
                ORDER BY complaints.created_at DESC
                LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $staffId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $complaints = [];
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }

        return $complaints;
    }

    public function getAssignedComplaints($staffId)
    {
        $sql = "SELECT complaints.complaint_id, complaints.complaint_title, complaints.complaint_status, complaints.created_at,
                       users.username AS student_name, complaint_categories.category_name
                FROM complaints
                LEFT JOIN users ON complaints.student_id = users.user_id
                LEFT JOIN complaint_categories ON complaints.category_id = complaint_categories.category_id
                WHERE complaints.assigned_staff_id = ?
                ORDER BY complaints.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();

        $complaints = [];
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }

        return $complaints;
    }

    // Get staff members eligible to receive an escalation (higher rank than the forwarding staff)
    public function getStaffForEscalation($fromStaffId)
    {
        $sql = "SELECT u.user_id, u.username, s.staff_id, d.department_name,
                       sr.role_name, sr.role_rank
                FROM staffs s
                JOIN users u ON s.staff_user_id = u.user_id
                LEFT JOIN departments d ON s.staff_department_id = d.department_id
                LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
                WHERE s.staff_approval_status = '1'
                  AND s.staff_id != ?
                  AND sr.role_rank > (
                      SELECT COALESCE(sr2.role_rank, 0)
                      FROM staffs s2
                      LEFT JOIN staff_roles sr2 ON s2.staff_role_id = sr2.role_id
                      WHERE s2.staff_id = ?
                  )
                ORDER BY sr.role_rank ASC, u.username ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ss', $fromStaffId, $fromStaffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Forward/escalate a complaint to a higher-ranked staff member
    public function forwardComplaint($complaintId, $fromStaffId, $toStaffId, $forwardedByUserId, $reason)
    {
        try {
            $this->conn->begin_transaction();

            // Verify destination staff has a higher rank
            $rankSql = "SELECT
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS from_rank,
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS to_rank";
            $rankStmt = $this->conn->prepare($rankSql);
            $rankStmt->bind_param('ss', $fromStaffId, $toStaffId);
            $rankStmt->execute();
            $ranks = $rankStmt->get_result()->fetch_assoc();
            $rankStmt->close();

            if ((int)$ranks['to_rank'] <= (int)$ranks['from_rank']) {
                throw new Exception("Can only escalate to a staff member of higher rank.");
            }

            // Log the escalation
            $escStmt = $this->conn->prepare(
                "INSERT INTO complaint_escalations (complaint_id, from_staff_id, to_staff_id, forwarded_by, reason, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            $escStmt->bind_param('issis', $complaintId, $fromStaffId, $toStaffId, $forwardedByUserId, $reason);
            $escStmt->execute();
            $escStmt->close();

            // Mark old assignment forwarded, create new lead assignment
            $fwdStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET status = 'forwarded', completed_at = NOW()
                 WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
            );
            $fwdStmt->bind_param('is', $complaintId, $fromStaffId);
            $fwdStmt->execute();
            $fwdStmt->close();

            $newAssignStmt = $this->conn->prepare(
                "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                 VALUES (?, ?, ?, 1, 'active', ?)"
            );
            $newAssignStmt->bind_param('isis', $complaintId, $toStaffId, $forwardedByUserId, $reason);
            $newAssignStmt->execute();
            $newAssignStmt->close();

            // Get destination staff's user_id for the complaints table
            $uidStmt = $this->conn->prepare("SELECT staff_user_id FROM staffs WHERE staff_id = ? LIMIT 1");
            $uidStmt->bind_param('s', $toStaffId);
            $uidStmt->execute();
            $toUserId = $uidStmt->get_result()->fetch_assoc()['staff_user_id'];
            $uidStmt->close();

            $updStmt = $this->conn->prepare(
                "UPDATE complaints SET assigned_staff_id = ? WHERE complaint_id = ?"
            );
            $updStmt->bind_param('si', $toStaffId, $complaintId);
            $updStmt->execute();
            $updStmt->close();

            // Status log
            $oldStmt = $this->conn->prepare("SELECT complaint_status FROM complaints WHERE complaint_id = ?");
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $oldStatus = $oldStmt->get_result()->fetch_assoc()['complaint_status'];
            $oldStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'escalated', ?, 'in_progress', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $oldStatus, $forwardedByUserId, $reason);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Get escalations pending acceptance for a staff member
    public function getPendingEscalationsForStaff($staffId)
    {
        $sql = "SELECT ce.*, c.complaint_title, c.complaint_status,
                       u.username AS forwarded_by_name,
                       su.username AS from_staff_name
                FROM complaint_escalations ce
                JOIN complaints c ON ce.complaint_id = c.complaint_id
                JOIN users u ON ce.forwarded_by = u.user_id
                JOIN staffs fs ON ce.from_staff_id = fs.staff_id
                JOIN users su ON fs.staff_user_id = su.user_id
                WHERE ce.to_staff_id = ? AND ce.status = 'pending'
                ORDER BY ce.escalated_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get full escalation history for a complaint
    public function getEscalationHistoryForComplaint($complaintId)
    {
        $sql = "SELECT ce.*,
                       fu.username AS from_staff_name,
                       tu.username AS to_staff_name,
                       fsr.role_name AS from_role,
                       tsr.role_name AS to_role,
                       fbu.username AS forwarded_by_name
                FROM complaint_escalations ce
                JOIN staffs fs ON ce.from_staff_id = fs.staff_id
                JOIN users fu ON fs.staff_user_id = fu.user_id
                LEFT JOIN staff_roles fsr ON fs.staff_role_id = fsr.role_id
                JOIN staffs ts ON ce.to_staff_id = ts.staff_id
                JOIN users tu ON ts.staff_user_id = tu.user_id
                LEFT JOIN staff_roles tsr ON ts.staff_role_id = tsr.role_id
                JOIN users fbu ON ce.forwarded_by = fbu.user_id
                WHERE ce.complaint_id = ?
                ORDER BY ce.escalated_at ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getComplaintById($complaintId, $staffId)
    {
        $sql = "SELECT c.*,
                       u.username AS student_name,
                       cc.category_name,
                       d.department_name,
                       su.username AS assigned_staff_name,
                       sr.role_name AS staff_role_name
                FROM complaints c
                LEFT JOIN users u ON c.student_id = u.user_id
                LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN staffs st ON c.assigned_staff_id = st.staff_id
                LEFT JOIN users su ON st.staff_user_id = su.user_id
                LEFT JOIN staff_roles sr ON st.staff_role_id = sr.role_id
                WHERE c.complaint_id = ? AND c.assigned_staff_id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('is', $complaintId, $staffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getComplaintAttachments($complaintId)
    {
        $sql = "SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getComplaintStatusLogs($complaintId)
    {
        $sql = "SELECT csl.*, u.username AS performed_by_name
                FROM complaint_status_logs csl
                LEFT JOIN users u ON csl.performed_by = u.user_id
                WHERE csl.complaint_id = ?
                ORDER BY csl.changed_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCollaborationNotes($complaintId)
    {
        $sql = "SELECT cn.*, u.username AS created_by_name
                FROM collaboration_notes cn
                LEFT JOIN users u ON cn.created_by = u.user_id
                WHERE cn.complaint_id = ?
                ORDER BY cn.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getInformationRequests($complaintId)
    {
        $sql = "SELECT ir.*, u.username AS requested_by_name
                FROM information_requests ir
                LEFT JOIN users u ON ir.requested_by = u.user_id
                WHERE ir.complaint_id = ?
                ORDER BY ir.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addCollaborationNote($complaintId, $createdByUserId, $note)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO collaboration_notes (complaint_id, created_by, note_text) VALUES (?, ?, ?)"
        );
        if (!$stmt) throw new Exception("DB error: " . $this->conn->error);
        $stmt->bind_param('iis', $complaintId, $createdByUserId, $note);
        if (!$stmt->execute()) throw new Exception("Failed to save note.");
        $stmt->close();
        return true;
    }

    public function requestInformation($complaintId, $requestedByUserId, $question)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old       = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? 'in_progress';

            $insStmt = $this->conn->prepare(
                "INSERT INTO information_requests (complaint_id, requested_by, request_message) VALUES (?, ?, ?)"
            );
            $insStmt->bind_param('iis', $complaintId, $requestedByUserId, $question);
            $insStmt->execute();
            $insStmt->close();

            $updStmt = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'awaiting_student_response' WHERE complaint_id = ?"
            );
            $updStmt->bind_param('i', $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'info_requested', ?, 'awaiting_student_response', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $oldStatus, $requestedByUserId, $question);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    public function respondToComplaint($complaintId, $staffUserId, $response, $action)
    {
        $statusMap = [
            'resolve'     => 'resolved',
            'reject'      => 'rejected',
            'in_progress' => 'in_progress',
        ];
        if (!array_key_exists($action, $statusMap)) {
            throw new Exception("Invalid action.");
        }
        $newStatus = $statusMap[$action];

        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old       = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? 'pending';

            if ($newStatus === 'resolved') {
                $updStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = ?, complaint_response = ?,
                     resolved_at = NOW(), updated_at = NOW() WHERE complaint_id = ?"
                );
            } else {
                $updStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = ?, complaint_response = ?,
                     updated_at = NOW() WHERE complaint_id = ?"
                );
            }
            $updStmt->bind_param('ssi', $newStatus, $response, $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $logStmt->bind_param('isssis', $complaintId, $action, $oldStatus, $newStatus, $staffUserId, $response);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

}
