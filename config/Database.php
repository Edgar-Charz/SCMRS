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
            die("Connection failed: " . $this->conn->connect_error);
        }
        return $this->conn;
    }
}
