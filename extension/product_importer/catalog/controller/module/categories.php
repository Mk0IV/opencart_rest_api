<?php
namespace Opencart\Catalog\Controller\Extension\ProductImporter\Module;

class Categories extends \Opencart\System\Engine\Controller {
    public function index() {
        $this->response->addHeader('Content-Type: application/json');
        
        // Проверка API токена
        $token = $this->request->server['HTTP_X_API_TOKEN'] ?? '';
        if (empty($token)) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'API token required',
                'code' => 401
            ]));
            return;
        }
        
        // Проверка токена в БД
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_tokens` WHERE token = '" . $this->db->escape($token) . "' AND status = '1'");
        
        if (!$query->num_rows) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Invalid API token',
                'code' => 401
            ]));
            return;
        }
        
        // Получаем категории
        $query = $this->db->query("
            SELECT c.category_id, cd.name, c.parent_id, c.status, 
                   COUNT(p2c.product_id) as product_count
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON c.category_id = cd.category_id
            LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON c.category_id = p2c.category_id
            WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            GROUP BY c.category_id
            ORDER BY c.parent_id, c.sort_order
        ");
        
        $this->response->setOutput(json_encode([
            'success' => true,
            'data' => [
                'categories' => $query->rows,
                'total' => count($query->rows)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
    
    public function create() {
        $this->response->addHeader('Content-Type: application/json');
        
        // Проверка API токена
        $token = $this->request->server['HTTP_X_API_TOKEN'] ?? '';
        if (empty($token)) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'API token required',
                'code' => 401
            ]));
            return;
        }
        
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Method not allowed',
                'code' => 405
            ]));
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || empty($input['name'])) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Field "name" is required',
                'code' => 400
            ]));
            return;
        }
        
        $parent_id = (int)($input['parent_id'] ?? 0);
        $description = $input['description'] ?? '';
        $status = (int)($input['status'] ?? 1);
        
        // Вставить категорию
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category` (parent_id, image, status, sort_order, date_added, date_modified) VALUES ('" . $parent_id . "', '', '" . $status . "', 0, NOW(), NOW())");
        
        $category_id = $this->db->getLastId();
        
        // Вставить описание
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` (category_id, language_id, name, description, meta_title) VALUES ('" . (int)$category_id . "', '" . (int)$this->config->get('config_language_id') . "', '" . $this->db->escape($input['name']) . "', '" . $this->db->escape($description) . "', '" . $this->db->escape($input['name']) . "')");
        
        $this->response->setOutput(json_encode([
            'success' => true,
            'data' => [
                'category_id' => $category_id,
                'message' => 'Category created successfully'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
}
