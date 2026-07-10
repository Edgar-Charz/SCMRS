<?php
class Student extends User
{

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get student id
    public function getStudentId($userId)
    {
        $stmt = $this->conn->prepare("SELECT student_id FROM students WHERE student_user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['student_id'] : null;
    }

    // Get total complaints by student
    public function getTotalComplaints($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalPending($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'pending'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalInprogress($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'in_progress'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalResolved($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'resolved'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    // Get complaints list for dashboard overview
    public function getStudentComplaints($studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, cc.category_name
             FROM complaints c
             JOIN complaint_categories cc ON c.category_id = cc.category_id
             WHERE c.student_id = ? AND c.complaint_status != 'deleted'
             ORDER BY c.created_at DESC"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get details of a specific complaint
    public function readStudentComplaint($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, u.username, d.department_name, cc.category_name, csc.subcategory_name
             FROM complaints c
             LEFT JOIN students s ON c.student_id = s.student_id
             JOIN users u ON u.user_id = s.student_user_id
             LEFT JOIN departments d ON c.department_id = d.department_id
             LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
             LEFT JOIN complaint_subcategories csc ON c.category_id = csc.category_id
             WHERE c.complaint_id = ?"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Complaint status history for all actors (admin, staff, student)
    public function readStudentComplaintHistory($complaintId)
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

    // Complaint attachments
    public function readStudentComplaintAttachments($complaintId)
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

    // Information requests for a complaint
    public function readStudentComplaintInfoRequests($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT ir.*, u.username
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

    // Student submits a response to an information request
    public function respondToInfoRequest($requestId, $complaintId, $studentId, $response)
    {
        try {
            $this->conn->begin_transaction();

            // Verify this request belongs to a complaint owned by this student
            $checkStmt = $this->conn->prepare(
                "SELECT ir.request_id FROM information_requests ir
                 JOIN complaints c ON ir.complaint_id = c.complaint_id
                 WHERE ir.request_id = ? AND ir.complaint_id = ? AND c.student_id = ? AND ir.status = 'pending'"
            );
            $checkStmt->bind_param("iii", $requestId, $complaintId, $studentId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                throw new Exception("Invalid request or already responded.");
            }
            $checkStmt->close();

            // Save the student response
            $updStmt = $this->conn->prepare(
                "UPDATE information_requests SET student_response = ?, status = 'responded' WHERE request_id = ?"
            );
            $updStmt->bind_param("si", $response, $requestId);
            $updStmt->execute();
            $updStmt->close();

            // If no more pending requests for this complaint, revert status to in_progress
            $pendingStmt = $this->conn->prepare(
                "SELECT COUNT(*) AS cnt FROM information_requests WHERE complaint_id = ? AND status = 'pending'"
            );
            $pendingStmt->bind_param("i", $complaintId);
            $pendingStmt->execute();
            $remaining = (int)$pendingStmt->get_result()->fetch_assoc()['cnt'];
            $pendingStmt->close();

            if ($remaining === 0) {
                $revertStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = 'in_progress'
                     WHERE complaint_id = ? AND complaint_status = 'awaiting_student_response'"
                );
                $revertStmt->bind_param("i", $complaintId);
                $revertStmt->execute();
                $revertStmt->close();
            }

            $this->conn->commit();

            // Notify the assigned lead staff member that the student has responded
            try {
                require_once __DIR__ . '/Notification.php';
                $staffStmt = $this->conn->prepare(
                    "SELECT sf.staff_user_id FROM complaint_assignments ca
                     JOIN staffs sf ON ca.staff_id = sf.staff_id
                     WHERE ca.complaint_id = ? AND ca.status = 'active' AND ca.is_lead = 1
                     LIMIT 1"
                );
                $staffStmt->bind_param('i', $complaintId);
                $staffStmt->execute();
                $staffRow = $staffStmt->get_result()->fetch_assoc();
                $staffStmt->close();
                if ($staffRow) {
                    (new Notification($this->conn))->create(
                        $staffRow['staff_user_id'],
                        "The student has responded to your information request on complaint #$complaintId. Please review their response.",
                        'info_responded',
                        "assigned_complaint_details.php?id=$complaintId#info-requests",
                        $complaintId
                    );
                }
            } catch (Throwable $e) {
                error_log('[Student::respondToInfoRequest] Notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Submit feedback for a resolved complaint
    public function submitFeedback($complaintId, $studentId, $rating, $feedbackText)
    {
        // Only allow feedback for resolved complaints owned by this student
        $checkStmt = $this->conn->prepare(
            "SELECT complaint_id FROM complaints
             WHERE complaint_id = ? AND student_id = ? AND complaint_status = 'resolved'"
        );
        $checkStmt->bind_param("ii", $complaintId, $studentId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            throw new Exception("Feedback can only be submitted for your resolved complaints.");
        }
        $checkStmt->close();

        // Check not already submitted
        $dupStmt = $this->conn->prepare(
            "SELECT feedback_id FROM complaint_feedback WHERE complaint_id = ? AND student_id = ?"
        );
        $dupStmt->bind_param("ii", $complaintId, $studentId);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            throw new Exception("You have already submitted feedback for this complaint.");
        }
        $dupStmt->close();

        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_feedback (complaint_id, student_id, rating, feedback_text) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iiis", $complaintId, $studentId, $rating, $feedbackText);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Check if student already submitted feedback for a complaint
    public function getComplaintFeedback($complaintId, $studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM complaint_feedback WHERE complaint_id = ? AND student_id = ? LIMIT 1"
        );
        $stmt->bind_param("ii", $complaintId, $studentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    public function reopenComplaint($complaintId, $studentId, $userId)
    {
        // Verify ownership, status and 7-day window
        $stmt = $this->conn->prepare(
            "SELECT complaint_status, updated_at, category_id, department_id, escalation_level
             FROM complaints
             WHERE complaint_id = ? AND student_id = ?"
        );
        $stmt->bind_param("ii", $complaintId, $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) throw new Exception("Complaint not found.");
        if ($row['complaint_status'] !== STATUS_RESOLVED)
            throw new Exception("Only resolved complaints can be reopened.");

        $daysSince = (new DateTime())->diff(new DateTime($row['updated_at']))->days;
        if ($daysSince > 7)
            throw new Exception("Complaints can only be reopened within 7 days of resolution.");

        // Update status
        $stmt = $this->conn->prepare(
            "UPDATE complaints SET complaint_status = 'reopened', updated_at = NOW() WHERE complaint_id = ?"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $stmt->close();

        // Log the change
        $old = 'resolved'; $new = 'reopened';
        $remarks = 'Student requested reopening - resolution was unsatisfactory.';
        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_status_logs (complaint_id, performed_by, action, old_status, new_status, remarks)
             VALUES (?, ?, 'complaint_reopened', ?, ?, ?)"
        );
        $stmt->bind_param("iisss", $complaintId, $userId, $old, $new, $remarks);
        $stmt->execute();
        $stmt->close();

        // Look up the current active lead assignment
        $stmt = $this->conn->prepare(
            "SELECT ca.staff_id, sf.staff_user_id
             FROM complaint_assignments ca
             JOIN staffs sf ON ca.staff_id = sf.staff_id
             WHERE ca.complaint_id = ? AND ca.status = 'active' AND ca.is_lead = 1
             LIMIT 1"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $currentAssignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // A reopened complaint escalates to the next level per the category's
        // escalation path (never back to the same staff who resolved it),
        // unless it's already at the top or the category has no path defined.
        $currentLevel = (int) $row['escalation_level'];
        if ($currentLevel < 3) {
            require_once __DIR__ . '/ComplaintRouter.php';
            $router = new ComplaintRouter($this->conn);
            $targetLevel = $currentLevel + 1;
            $roleId = $router->getCategoryEscalationRoleId((int) $row['category_id'], $targetLevel);

            if ($roleId) {
                $nextStaffId = $router->findLeastLoadedStaff($roleId, $row['department_id']);

                if ($nextStaffId) {
                    if ($currentAssignment) {
                        $fwdStmt = $this->conn->prepare(
                            "UPDATE complaint_assignments SET status = 'forwarded', completed_at = NOW()
                             WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
                        );
                        $fwdStmt->bind_param("is", $complaintId, $currentAssignment['staff_id']);
                        $fwdStmt->execute();
                        $fwdStmt->close();
                    }

                    $newAssignStmt = $this->conn->prepare(
                        "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                         VALUES (?, ?, ?, 1, 'active', 'Auto-escalated: complaint reopened by student.')"
                    );
                    $newAssignStmt->bind_param("isi", $complaintId, $nextStaffId, $userId);
                    $newAssignStmt->execute();
                    $newAssignStmt->close();

                    $escStmt = $this->conn->prepare(
                        "INSERT INTO complaint_escalations (complaint_id, from_staff_id, to_staff_id, forwarded_by, reason, status)
                         VALUES (?, ?, ?, ?, 'Student reopened the complaint after resolution.', 'pending')"
                    );
                    $fromStaffId = $currentAssignment['staff_id'] ?? '';
                    $escStmt->bind_param("issi", $complaintId, $fromStaffId, $nextStaffId, $userId);
                    $escStmt->execute();
                    $escStmt->close();

                    $levelStmt = $this->conn->prepare("UPDATE complaints SET escalation_level = ? WHERE complaint_id = ?");
                    $levelStmt->bind_param("ii", $targetLevel, $complaintId);
                    $levelStmt->execute();
                    $levelStmt->close();

                    $nextUserStmt = $this->conn->prepare("SELECT staff_user_id FROM staffs WHERE staff_id = ? LIMIT 1");
                    $nextUserStmt->bind_param("s", $nextStaffId);
                    $nextUserStmt->execute();
                    $nextUser = $nextUserStmt->get_result()->fetch_assoc();
                    $nextUserStmt->close();

                    return $nextUser['staff_user_id'] ?? null;
                }
            }
        }

        // No escalation path available (already at top level, or category has none
        // configured) - keep the current lead staff informed of the reopening.
        return $currentAssignment['staff_user_id'] ?? null;
    }

    // Get filtered complaints with status tabs support
    public function getFilteredComplaints($studentId, $filter = 'all')
    {
        $where  = "WHERE c.student_id = ? AND c.complaint_status != 'deleted'";
        $types  = "i";
        $params = [$studentId];

        $validStatuses = ['pending', 'in_progress', 'resolved', 'rejected', 'awaiting_student_response'];
        if ($filter !== 'all' && in_array($filter, $validStatuses)) {
            $where   .= " AND c.complaint_status = ?";
            $types   .= "s";
            $params[] = $filter;
        }

        $sql = "SELECT c.*, d.department_name, cc.category_name,
                    (SELECT COUNT(*) FROM information_requests
                     WHERE complaint_id = c.complaint_id AND status = 'pending') AS pending_requests
                FROM complaints c
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                $where
                ORDER BY FIELD(c.complaint_status, 'awaiting_student_response', 'pending', 'in_progress', 'resolved', 'rejected'),
                         c.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Count complaints per status for tab badges
    public function getComplaintCounts($studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT complaint_status, COUNT(*) AS cnt
             FROM complaints
             WHERE student_id = ? AND complaint_status != 'deleted'
             GROUP BY complaint_status"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $counts[$row['complaint_status']] = (int) $row['cnt'];
            $counts['all'] += (int) $row['cnt'];
        }
        foreach (['awaiting_student_response', 'pending', 'in_progress', 'resolved', 'rejected'] as $s) {
            $counts[$s] = $counts[$s] ?? 0;
        }

        return $counts;
    }

    // Count pending info requests that need a student response
    public function getPendingInfoRequestsCount($studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(DISTINCT c.complaint_id) AS cnt
             FROM complaints c
             JOIN information_requests ir ON ir.complaint_id = c.complaint_id
             WHERE c.student_id = ? AND ir.status = 'pending' AND c.complaint_status != 'deleted'"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Edit a pending complaint (only allowed while status is pending)
    public function updateComplaint($complaintId, $studentId, $title, $description, $categoryId, $subcategoryId, $isAnonymous = 0, $departmentId = null)
    {
        $checkStmt = $this->conn->prepare(
            "SELECT complaint_id FROM complaints WHERE complaint_id = ? AND student_id = ? AND complaint_status = 'pending' LIMIT 1"
        );
        $checkStmt->bind_param("ii", $complaintId, $studentId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$exists) {
            throw new Exception("Complaint cannot be edited. It may have already been processed.");
        }

        $anon = $isAnonymous ? 1 : 0;
        $subcatVal = $subcategoryId ?: null;
        $deptVal = $departmentId ?: null;

        $stmt = $this->conn->prepare(
            "UPDATE complaints SET complaint_title = ?, complaint_description = ?, category_id = ?, subcategory_id = ?, department_id = ?, is_anonymous = ?, updated_at = NOW()
             WHERE complaint_id = ? AND student_id = ?"
        );
        $stmt->bind_param("ssiiiiii", $title, $description, $categoryId, $subcatVal, $deptVal, $anon, $complaintId, $studentId);

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Delete a single attachment - verifies it belongs to a pending complaint owned by this student
    public function deleteAttachment($attachmentId, $complaintId, $studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT ca.file_path FROM complaint_attachments ca
             JOIN complaints c ON ca.complaint_id = c.complaint_id
             WHERE ca.attachment_id = ? AND ca.complaint_id = ? AND c.student_id = ? AND c.complaint_status = 'pending'
             LIMIT 1"
        );
        $stmt->bind_param("iii", $attachmentId, $complaintId, $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        if (file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }

        $del = $this->conn->prepare("DELETE FROM complaint_attachments WHERE attachment_id = ?");
        $del->bind_param("i", $attachmentId);
        $del->execute();
        $del->close();

        return true;
    }

    // Delete a pending complaint (only allowed while status is pending)
    public function deleteComplaint($complaintId, $studentId, $reason = '')
    {
        // Verify complaint exists, belongs to this student and is pending
        $checkStmt = $this->conn->prepare(
            "SELECT complaint_status FROM complaints WHERE complaint_id = ? AND student_id = ? LIMIT 1"
        );
        $checkStmt->bind_param("ii", $complaintId, $studentId);
        $checkStmt->execute();
        $row = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$row) {
            return false;
        }
        if ($row['complaint_status'] !== STATUS_PENDING) {
            return false;
        }

        // Update status to 'deleted' instead of hard delete
        $stmt = $this->conn->prepare(
            "UPDATE complaints SET complaint_status = 'deleted', updated_at = NOW() WHERE complaint_id = ?"
        );
        $stmt->bind_param("i", $complaintId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            try {
                require_once __DIR__ . '/Notification.php';
                $reasonText = $reason ? " Reason: $reason" : '';
                (new Notification($this->conn))->notifyAllAdmins(
                    "Complaint #$complaintId was deleted by the student.$reasonText",
                    'complaint_deleted',
                    'manage_complaints.php',
                    $complaintId
                );
            } catch (Throwable $e) {
                error_log('[deleteComplaint] Notification failed: ' . $e->getMessage());
            }
        }

        return $ok;
    }

    public function getProgressUpdates($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT pu.message, pu.created_at, u.username AS sent_by_name
             FROM complaint_progress_updates pu
             LEFT JOIN users u ON pu.sent_by = u.user_id
             WHERE pu.complaint_id = ?
             ORDER BY pu.created_at ASC"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
