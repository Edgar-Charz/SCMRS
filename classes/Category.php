<?php
class Category
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get Categories
    public function getCategories()
    {
        $stmt = $this->conn->prepare("SELECT category_id, category_name FROM complaint_categories ORDER BY category_name");
        $stmt->execute();
        return $stmt->get_result();
    }

    // Get Subcategories
    public function getSubcategories()
    {
        $stmt = $this->conn->prepare("SELECT subcategory_id, subcategory_name FROM complaint_subcategories ORDER BY subcategory_name");
        $stmt->execute();
        return $stmt->get_result();
    }
}
