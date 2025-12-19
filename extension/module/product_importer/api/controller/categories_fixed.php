<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Api\Controller;

class CategoriesController {
    private $db;
    private $request;
    private $response;
    
    public function __construct($db, $request, $response) {
        $this->db = $db;
        $this->request = $request;
        $this->response = $response;
    }
    
    /**
     * GET /api/categories
     * Получить все категории
     */
    public function getAll() {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $query = $this->db->query("
                SELECT c.category_id, cd.name, c.parent_id, c.status, 
                       COUNT(p2c.product_id) as product_count
                FROM `oc_category` c
                LEFT JOIN `oc_category_description` cd ON c.category_id = cd.category_id
                LEFT JOIN `oc_product_to_category` p2c ON c.category_id = p2c.category_id
                WHERE cd.language_id = '1'
                GROUP BY c.category_id
                ORDER BY c.parent_id, c.sort_order
            ");
            
            return $this->success([
                'categories' => $query->rows,
                'total' => count($query->rows)
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/categories/{id}
     * Получить категорию по ID
     */
    public function getById($category_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $query = $this->db->query("SELECT * FROM `oc_category` WHERE category_id = '" . (int)$category_id . "'");
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            $desc_query = $this->db->query("SELECT * FROM `oc_category_description` WHERE category_id = '" . (int)$category_id . "'");
            
            $category = $query->row;
            $category['descriptions'] = $desc_query->rows;
            
            // Получить дочерние категории
            $children_query = $this->db->query("SELECT * FROM `oc_category` WHERE parent_id = '" . (int)$category_id . "'");
            $category['children'] = $children_query->rows;
            
            return $this->success(['category' => $category]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/categories/create
     * Создать новую категорию
     */
    public function create() {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['name']) || empty($input['name'])) {
                return $this->error('Field "name" is required', 400);
            }
            
            $parent_id = (int)($input['parent_id'] ?? 0);
            $description = $input['description'] ?? '';
            $status = (int)($input['status'] ?? 1);
            
            // Вставить категорию
            $this->db->query("INSERT INTO `oc_category` (parent_id, image, status, sort_order) VALUES ('" . $parent_id . "', '', '" . $status . "', 0)");
            
            $category_id = $this->db->getLastId();
            
            // Вставить описание
            $this->db->query("INSERT INTO `oc_category_description` (category_id, language_id, name, description) VALUES ('" . (int)$category_id . "', '1', '" . $this->db->escape($input['name']) . "', '" . $this->db->escape($description) . "')");
            
            return $this->success([
                'category_id' => $category_id,
                'message' => 'Category created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/categories/{id}
     * Обновить категорию
     */
    public function update($category_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Проверить существует ли категория
            $query = $this->db->query("SELECT * FROM `oc_category` WHERE category_id = '" . (int)$category_id . "'");
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            // Обновить основную таблицу
            if (isset($input['parent_id']) || isset($input['status'])) {
                $updates = [];
                
                if (isset($input['parent_id'])) {
                    $updates[] = "parent_id = '" . (int)$input['parent_id'] . "'";
                }
                
                if (isset($input['status'])) {
                    $updates[] = "status = '" . (int)$input['status'] . "'";
                }
                
                if (!empty($updates)) {
                    $this->db->query(
                        "UPDATE `oc_category` SET " . implode(', ', $updates) . " WHERE category_id = '" . (int)$category_id . "'"
                    );
                }
            }
            
            // Обновить описание
            if (isset($input['name']) || isset($input['description'])) {
                $updates = [];
                
                if (isset($input['name'])) {
                    $updates[] = "name = '" . $this->db->escape($input['name']) . "'";
                }
                
                if (isset($input['description'])) {
                    $updates[] = "description = '" . $this->db->escape($input['description']) . "'";
                }
                
                if (!empty($updates)) {
                    $this->db->query(
                        "UPDATE `oc_category_description` SET " . implode(', ', $updates) . " WHERE category_id = '" . (int)$category_id . "' AND language_id = '1'"
                    );
                }
            }
            
            return $this->success([
                'category_id' => $category_id,
                'message' => 'Category updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/categories/{id}
     * Удалить категорию
     */
    public function delete($category_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Проверить существует ли категория
            $query = $this->db->query("SELECT * FROM `oc_category` WHERE category_id = '" . (int)$category_id . "'");
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            // Удалить товары из этой категории (опционально перенести в parent)
            // или оставить в категории
            
            // Удалить описание
            $this->db->query("DELETE FROM `oc_category_description` WHERE category_id = '" . (int)$category_id . "'");
            
            // Удалить категорию
            $this->db->query("DELETE FROM `oc_category` WHERE category_id = '" . (int)$category_id . "'");
            
            return $this->success([
                'message' => 'Category deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    private function authenticate() {
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        if (empty($token)) {
            return false;
        }
        
        $query = $this->db->query("SELECT * FROM `oc_api_tokens` WHERE token = '" . $this->db->escape($token) . "' AND status = '1'");
        
        return !empty($query->row);
    }
    
    private function success($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    private function error($message, $status = 400) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}
