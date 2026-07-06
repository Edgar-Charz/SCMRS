<?php
require_once __DIR__ . '/Admin.php';

class ComplaintRouter
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Auto-route a freshly submitted complaint to the staff member responsible
    public function routeComplaint($complaintId, $categoryId, $subcategoryId, $departmentId, $priority)
    {
        $roleId = $this->resolveRoleId($categoryId, $subcategoryId);
        if (!$roleId) {
            return ['routed' => false, 'staff_id' => null, 'role_id' => null];
        }

        $staffId = $this->findLeastLoadedStaff($roleId, $departmentId);
        if (!$staffId) {
            return ['routed' => false, 'staff_id' => null, 'role_id' => $roleId];
        }

        $admin = new Admin($this->conn);
        $admin->assignComplaint($complaintId, $staffId, $priority, '', null, true);

        return ['routed' => true, 'staff_id' => $staffId, 'role_id' => $roleId];
    }

    // Subcategory's configured role wins; falls back to the category's role.
    private function resolveRoleId($categoryId, $subcategoryId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(sub.default_role_id, cat.default_role_id) AS role_id
             FROM complaint_categories cat
             LEFT JOIN complaint_subcategories sub
                 ON sub.subcategory_id = ? AND sub.category_id = cat.category_id
             WHERE cat.category_id = ?"
        );
        $stmt->bind_param('ii', $subcategoryId, $categoryId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row && $row['role_id'] ? (int) $row['role_id'] : null;
    }

    // Picks an approved, active staff member holding the given role, scoped to the
    // given department when one is supplied. Ties are broken by whoever currently
    // has the fewest active assignments.
    private function findLeastLoadedStaff($roleId, $departmentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT s.staff_id
             FROM staffs s
             JOIN users u ON s.staff_user_id = u.user_id
             LEFT JOIN complaint_assignments ca
                 ON ca.staff_id = s.staff_id AND ca.status = 'active'
             WHERE s.staff_role_id = ?
               AND s.staff_approval_status = 1
               AND u.user_status = 'active'
               AND (? IS NULL OR s.staff_department_id = ?)
             GROUP BY s.staff_id
             ORDER BY COUNT(ca.assignment_id) ASC
             LIMIT 1"
        );
        $stmt->bind_param('iii', $roleId, $departmentId, $departmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $row['staff_id'] : null;
    }
}
