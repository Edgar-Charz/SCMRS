<?php
class College
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get Colleges
    public function getColleges()
    {
        $colleges_query = "SELECT college_id, college_name FROM colleges ORDER BY college_name";
        $colleges_result = $this->conn->query($colleges_query);

        return $colleges_result;
    }
}
