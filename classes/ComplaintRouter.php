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
    // This is the Level 1 role of the escalation matrix.
    public function resolveRoleId($categoryId, $subcategoryId)
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

    // Role a complaint's category designates for the given escalation level
    // (2 or 3), per complaint_categories.level2_role_id / level3_role_id.
    public function getCategoryEscalationRoleId($categoryId, $level)
    {
        $column = $level >= 3 ? 'level3_role_id' : 'level2_role_id';
        $stmt = $this->conn->prepare(
            "SELECT {$column} AS role_id FROM complaint_categories WHERE category_id = ?"
        );
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row && $row['role_id'] ? (int) $row['role_id'] : null;
    }

    // Role designated for any level (1, 2 or 3) of the escalation matrix, used
    // by both upward escalation and downward delegation so both walk the same
    // category-defined ladder instead of two different notions of "rank".
    public function getRoleIdForLevel($categoryId, $subcategoryId, $level)
    {
        if ($level <= 1) {
            return $this->resolveRoleId($categoryId, $subcategoryId);
        }
        return $this->getCategoryEscalationRoleId($categoryId, $level);
    }

    // Roles marked non-department-scoped (e.g. Principal, Dean of Students) have
    // a single university-wide holder, so a complaint's department must never
    // filter them out. Departmental roles (e.g. Head of Department) keep the filter.
    private function scopedDepartmentId($roleId, $departmentId)
    {
        if ($departmentId === null) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT is_department_scoped FROM staff_roles WHERE role_id = ?");
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $isScoped = $row ? (bool) $row['is_department_scoped'] : true;
        return $isScoped ? $departmentId : null;
    }

    // Approved, active staff holding the given role (optionally scoped to a
    // department), for presenting an escalation target list to staff.
    public function getStaffForRole($roleId, $departmentId = null)
    {
        $departmentId = $this->scopedDepartmentId($roleId, $departmentId);

        $stmt = $this->conn->prepare(
            "SELECT u.user_id, u.username, s.staff_id, d.department_name, sr.role_name
             FROM staffs s
             JOIN users u ON s.staff_user_id = u.user_id
             LEFT JOIN departments d ON s.staff_department_id = d.department_id
             LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
             WHERE s.staff_role_id = ?
               AND s.staff_approval_status = 1
               AND u.user_status = 'active'
               AND (? IS NULL OR s.staff_department_id = ?)
             ORDER BY u.username ASC"
        );
        $stmt->bind_param('iii', $roleId, $departmentId, $departmentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Picks an approved, active staff member holding the given role, scoped to the
    // given department when one is supplied and the role is department-scoped.
    // Ties are broken by whoever currently has the fewest active assignments.
    public function findLeastLoadedStaff($roleId, $departmentId)
    {
        $departmentId = $this->scopedDepartmentId($roleId, $departmentId);

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
