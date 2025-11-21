<?php
// api/models/Category.php

class Category
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // [R] LEER TODAS (Categorías y Subcategorías anidadas)
    public function readAll()
    {
        $query = "SELECT 
                    c.category_id, c.category_name, 
                    s.subcategory_id, s.subcategory_name 
                  FROM 
                    categories c 
                  LEFT JOIN 
                    subcategories s ON c.category_id = s.category_id
                  ORDER BY 
                    c.category_name, s.subcategory_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categoriesData = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cat_id = $row['category_id'];
            $cat_name = $row['category_name'];

            if (!isset($categoriesData[$cat_id])) {
                $categoriesData[$cat_id] = [
                    'category_id' => $cat_id,
                    'category_name' => $cat_name,
                    'subcategories' => []
                ];
            }

            if ($row['subcategory_id']) {
                $categoriesData[$cat_id]['subcategories'][] = [
                    'subcategory_id' => $row['subcategory_id'],
                    'subcategory_name' => $row['subcategory_name']
                ];
            }
        }
        // Retorna como array de objetos (más fácil para JS)
        return array_values($categoriesData);
    }

    // [C] CREAR CATEGORÍA
    public function createCategory($name)
    {
        $query = "INSERT INTO categories (category_name) VALUES (:name)";
        $stmt = $this->conn->prepare($query);
        $name = htmlspecialchars(strip_tags($name));
        $stmt->bindParam(':name', $name);
        return $stmt->execute();
    }

    // [C] CREAR SUBCATEGORÍA
    public function createSubcategory($categoryId, $name)
    {
        $query = "INSERT INTO subcategories (category_id, subcategory_name) VALUES (:category_id, :name)";
        $stmt = $this->conn->prepare($query);

        $categoryId = htmlspecialchars(strip_tags($categoryId));
        $name = htmlspecialchars(strip_tags($name));

        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':name', $name);
        return $stmt->execute();
    }

    // [U] ACTUALIZAR CATEGORÍA
    public function updateCategory($id, $name)
    {
        $query = "UPDATE categories SET category_name = :name WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        
        $name = htmlspecialchars(strip_tags($name));
        $id = htmlspecialchars(strip_tags($id));

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // [U] ACTUALIZAR SUBCATEGORÍA
    public function updateSubcategory($id, $name)
    {
        $query = "UPDATE subcategories SET subcategory_name = :name WHERE subcategory_id = :id";
        $stmt = $this->conn->prepare($query);

        $name = htmlspecialchars(strip_tags($name));
        $id = htmlspecialchars(strip_tags($id));

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // [D] ELIMINAR CATEGORÍA (ON DELETE CASCADE se encarga de las subcategorías)
    public function deleteCategory($id)
    {
        $query = "DELETE FROM categories WHERE category_id = :id";
        $stmt = $this->conn->prepare($query);
        $id = htmlspecialchars(strip_tags($id));
        $stmt->bindParam(':id', $id);
        
        // Ejecutar con verificación de error si está en uso por una WO
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            // Error 23000 es típicamente un error de clave foránea (uso)
            if ($e->getCode() === '23000') {
                 // Devolvemos false para indicar que no se pudo borrar por uso.
                return false; 
            }
            throw $e; // Relanzar cualquier otro error
        }
    }

    // [D] ELIMINAR SUBCATEGORÍA
    public function deleteSubcategory($id)
    {
        $query = "DELETE FROM subcategories WHERE subcategory_id = :id";
        $stmt = $this->conn->prepare($query);
        $id = htmlspecialchars(strip_tags($id));
        $stmt->bindParam(':id', $id);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            // Error 23000 es típicamente un error de clave foránea (uso)
            if ($e->getCode() === '23000') {
                 // Devolvemos false para indicar que no se pudo borrar por uso.
                return false; 
            }
            throw $e; // Relanzar cualquier otro error
        }
    }
}