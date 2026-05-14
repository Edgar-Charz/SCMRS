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
        $categories_query_result = $this->conn->query("SELECT category_id, category_name FROM complaint_categories ORDER BY category_name");

        return $categories_query_result;
    }

    // Get Subcategories
    public function getSubcategories()
    {
        $subcategories_query_result = $this->conn->query("SELECT subcategory_id, subcategory_name FROM complaint_subcategories ORDER BY subcategory_name");

        return $subcategories_query_result;
    }
}
