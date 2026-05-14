<?php
class Department
{
    private $conn; 

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get all departments
    public function getDepartments()
    {
        $departments_query = "SELECT department_id, department_name FROM departments ORDER BY department_name";
        $departments_result = $this->conn->query($departments_query);

        return $departments_result;
    }
}
