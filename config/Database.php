<?php
class Database
{
    private $servername = "localhost";
    private $username   = "root";
    private $password   = "";
    private $dbname     = "scmrs";
    public $conn;

    public function connect()
    {
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
        return $this->conn;
    }
}
