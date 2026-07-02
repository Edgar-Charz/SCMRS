<?php
class Database
{
    private $servername = "localhost";
    private $username   = "root";
    private $password   = "";
    private $dbname     = "scmrs";
    public $conn;

    // private $servername = "sql101.infinityfree.com";
    // private $username   = "if0_42219014";
    // private $password   = "CharlesEddy004";
    // private $dbname     = "if0_42219014_scmrs";
    // public $conn;

    public function connect()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->conn = new mysqli(
            $this->servername,
            $this->username,
            $this->password,
            $this->dbname
        );
        if ($this->conn->connect_error) {
            error_log("DB connection failed: " . $this->conn->connect_error);
            die("A database error occurred. Please try again later.");
        }

        // Set MySQL session timezone to East Africa Time (UTC+3)
        $this->conn->query("SET time_zone = '+03:00'");

        // Align PHP date functions to the same timezone
        date_default_timezone_set('Africa/Dar_es_Salaam');

        return $this->conn;
    }
}
