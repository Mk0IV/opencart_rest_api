<?php
namespace Opencart\Catalog\Controller\Api;

class ProductImporter extends \Opencart\System\Engine\Controller {
    public function categories() {
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
    
    public function createCategory() {
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
    
    public function importProducts() {
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
        
        if (!$input) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Invalid JSON',
                'code' => 400
            ]));
            return;
        }
        
        if (empty($input['products']) || !is_array($input['products'])) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Field "products" is required and must be an array',
                'code' => 400
            ]));
            return;
        }
        
        $mode = $input['mode'] ?? 'merge';
        if (!in_array($mode, ['add', 'update', 'merge'])) {
            $this->response->setOutput(json_encode([
                'success' => false,
                'error' => 'Field "mode" must be add, update, or merge',
                'code' => 400
            ]));
            return;
        }
        
        $products = $input['products'];
        $total = count($products);
        $success = 0;
        $failed = 0;
        
        // Создать запись batch
        $this->db->query("INSERT INTO `" . DB_PREFIX . "import_batch` (filename, file_type, total_records, mode, admin_id, status, created_at) VALUES ('api_import_" . date('Y-m-d_H-i-s') . "', 'json', '" . (int)$total . "', '" . $this->db->escape($mode) . "', 0, 'processing', NOW())");
        
        $batch_id = $this->db->getLastId();
        
        // Обработка товаров
        foreach ($products as $product) {
            try {
                $result = $this->processProduct($product, $mode);
                
                if ($result['success']) {
                    $success++;
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_import_log` (import_batch_id, product_id, sku, name, action, status, created_at) VALUES ('" . (int)$batch_id . "', '" . (int)$result['product_id'] . "', '" . $this->db->escape($product['sku'] ?? '') . "', '" . $this->db->escape($product['name'] ?? '') . "', '" . $result['action'] . "', 'success', NOW())");
                } else {
                    $failed++;
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_import_log` (import_batch_id, product_id, sku, name, action, status, error_message, created_at) VALUES ('" . (int)$batch_id . "', 0, '" . $this->db->escape($product['sku'] ?? '') . "', '" . $this->db->escape($product['name'] ?? '') . "', 'error', 'error', '" . $this->db->escape($result['error']) . "', NOW())");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_import_log` (import_batch_id, product_id, sku, name, action, status, error_message, created_at) VALUES ('" . (int)$batch_id . "', 0, '" . $this->db->escape($product['sku'] ?? '') . "', '" . $this->db->escape($product['name'] ?? '') . "', 'error', 'error', '" . $this->db->escape($e->getMessage()) . "', NOW())");
            }
        }
        
        // Обновить batch
        $this->db->query("UPDATE `" . DB_PREFIX . "import_batch` SET status = 'completed', processed_records = '" . (int)($success + $failed) . "', success_records = '" . (int)$success . "', failed_records = '" . (int)$failed . "', updated_at = NOW() WHERE id = '" . (int)$batch_id . "'");
        
        $this->response->setOutput(json_encode([
            'success' => true,
            'data' => [
                'batch_id' => $batch_id,
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'message' => sprintf('Import completed. Added: %d, Updated: %d, Failed: %d', $success, $success, $failed)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
    
    private function processProduct($product, $mode) {
        // Валидация обязательных полей
        if (empty($product['name'])) {
            return ['success' => false, 'error' => 'Field "name" is required'];
        }
        
        if (!isset($product['price']) || !is_numeric($product['price']) || $product['price'] < 0) {
            return ['success' => false, 'error' => 'Field "price" must be a positive number'];
        }
        
        // Поиск существующего товара по SKU
        $existingProduct = null;
        if (!empty($product['sku'])) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE sku = '" . $this->db->escape($product['sku']) . "'");
            if ($query->num_rows) {
                $existingProduct = $query->row;
            }
        }
        
        switch ($mode) {
            case 'add':
                if ($existingProduct) {
                    return ['success' => false, 'error' => 'Product already exists'];
                }
                $product_id = $this->createProduct($product);
                return ['success' => true, 'product_id' => $product_id, 'action' => 'insert'];
                
            case 'update':
                if (!$existingProduct) {
                    return ['success' => false, 'error' => 'Product not found'];
                }
                $this->updateProduct($existingProduct['product_id'], $product);
                return ['success' => true, 'product_id' => $existingProduct['product_id'], 'action' => 'update'];
                
            case 'merge':
            default:
                if ($existingProduct) {
                    $this->updateProduct($existingProduct['product_id'], $product);
                    return ['success' => true, 'product_id' => $existingProduct['product_id'], 'action' => 'update'];
                } else {
                    $product_id = $this->createProduct($product);
                    return ['success' => true, 'product_id' => $product_id, 'action' => 'insert'];
                }
        }
    }
    
    private function createProduct($product) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` (model, sku, quantity, price, status, date_added, date_modified) VALUES ('" . $this->db->escape($product['model'] ?? '') . "', '" . $this->db->escape($product['sku'] ?? '') . "', '" . (int)($product['quantity'] ?? 0) . "', '" . (float)($product['price'] ?? 0) . "', '" . (int)($product['status'] ?? 1) . "', NOW(), NOW())");
        
        $product_id = $this->db->getLastId();
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` (product_id, language_id, name, description, meta_title) VALUES ('" . (int)$product_id . "', '" . (int)$this->config->get('config_language_id') . "', '" . $this->db->escape($product['name']) . "', '" . $this->db->escape($product['description'] ?? '') . "', '" . $this->db->escape($product['name']) . "')");
        
        if (!empty($product['category_id'])) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` (product_id, category_id) VALUES ('" . (int)$product_id . "', '" . (int)$product['category_id'] . "')");
        }
        
        return $product_id;
    }
    
    private function updateProduct($product_id, $product) {
        $updates = [];
        
        if (isset($product['model'])) {
            $updates[] = "model = '" . $this->db->escape($product['model']) . "'";
        }
        if (isset($product['sku'])) {
            $updates[] = "sku = '" . $this->db->escape($product['sku']) . "'";
        }
        if (isset($product['quantity'])) {
            $updates[] = "quantity = '" . (int)$product['quantity'] . "'";
        }
        if (isset($product['price'])) {
            $updates[] = "price = '" . (float)$product['price'] . "'";
        }
        if (isset($product['status'])) {
            $updates[] = "status = '" . (int)$product['status'] . "'";
        }
        
        if (!empty($updates)) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $updates) . ", date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        }
        
        if (isset($product['name']) || isset($product['description'])) {
            $desc_updates = [];
            if (isset($product['name'])) {
                $desc_updates[] = "name = '" . $this->db->escape($product['name']) . "'";
                $desc_updates[] = "meta_title = '" . $this->db->escape($product['name']) . "'";
            }
            if (isset($product['description'])) {
                $desc_updates[] = "description = '" . $this->db->escape($product['description']) . "'";
            }
            
            if (!empty($desc_updates)) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET " . implode(', ', $desc_updates) . " WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
            }
        }
        
        if (isset($product['category_id'])) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` (product_id, category_id) VALUES ('" . (int)$product_id . "', '" . (int)$product['category_id'] . "')");
        }
    }
}
