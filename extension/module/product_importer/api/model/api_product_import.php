<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Api\Model;

class ApiProductImport {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Создать запись о batch импорта
     */
    public function createBatch($filename, $file_type, $total_records, $mode, $admin_id) {
        $this->db->query(
            "INSERT INTO `oc_import_batch` (filename, file_type, total_records, mode, admin_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            [$filename, $file_type, $total_records, $mode, $admin_id]
        );
        
        return $this->db->getLastId();
    }
    
    /**
     * Обновить статус batch
     */
    public function updateBatch($batch_id, $status, $processed = 0, $success = 0, $failed = 0) {
        $this->db->query(
            "UPDATE `oc_import_batch` SET status = ?, processed_records = ?, success_records = ?, failed_records = ?, updated_at = NOW() WHERE id = ?",
            [$status, $processed, $success, $failed, $batch_id]
        );
    }
    
    /**
     * Получить информацию о batch
     */
    public function getBatch($batch_id) {
        $query = $this->db->query(
            "SELECT * FROM `oc_import_batch` WHERE id = ?",
            [$batch_id]
        );
        
        return $query->row;
    }
    
    /**
     * Логировать операцию импорта
     */
    public function logImport($batch_id, $product_id, $sku, $name, $action, $status, $error_message = null) {
        $this->db->query(
            "INSERT INTO `oc_product_import_log` (import_batch_id, product_id, sku, name, action, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$batch_id, $product_id, $sku, $name, $action, $status, $error_message]
        );
    }
    
    /**
     * Найти товар по SKU
     */
    public function findProductBySku($sku) {
        $query = $this->db->query(
            "SELECT p.* FROM `oc_product` p LEFT JOIN `oc_product_description` pd ON p.product_id = pd.product_id WHERE p.sku = ? AND pd.language_id = 1",
            [$sku]
        );
        
        return $query->row;
    }
    
    /**
     * Создать товар
     */
    public function createProduct($product_data) {
        // Вставить основной товар
        $this->db->query(
            "INSERT INTO `oc_product` (model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $product_data['model'] ?? '',
                $product_data['sku'] ?? '',
                $product_data['upc'] ?? '',
                $product_data['ean'] ?? '',
                $product_data['jan'] ?? '',
                $product_data['isbn'] ?? '',
                $product_data['mpn'] ?? '',
                $product_data['location'] ?? '',
                $product_data['quantity'] ?? 0,
                $product_data['stock_status_id'] ?? 7,
                $product_data['image'] ?? '',
                $product_data['manufacturer_id'] ?? 0,
                $product_data['shipping'] ?? 1,
                $product_data['price'] ?? 0,
                $product_data['points'] ?? 0,
                $product_data['tax_class_id'] ?? 0,
                $product_data['date_available'] ?? date('Y-m-d'),
                $product_data['weight'] ?? 0,
                $product_data['weight_class_id'] ?? 1,
                $product_data['length'] ?? 0,
                $product_data['width'] ?? 0,
                $product_data['height'] ?? 0,
                $product_data['length_class_id'] ?? 1,
                $product_data['subtract'] ?? 1,
                $product_data['minimum'] ?? 1,
                $product_data['sort_order'] ?? 0,
                $product_data['status'] ?? 1
            ]
        );
        
        $product_id = $this->db->getLastId();
        
        // Вставить описание
        $this->db->query(
            "INSERT INTO `oc_product_description` (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $product_id,
                $product_data['language_id'] ?? 1,
                $product_data['name'],
                $product_data['description'] ?? '',
                $product_data['tag'] ?? '',
                $product_data['meta_title'] ?? $product_data['name'],
                $product_data['meta_description'] ?? '',
                $product_data['meta_keyword'] ?? ''
            ]
        );
        
        // Привязать к категории
        if (!empty($product_data['category_id'])) {
            $this->db->query(
                "INSERT INTO `oc_product_to_category` (product_id, category_id) VALUES (?, ?)",
                [$product_id, $product_data['category_id']]
            );
        }
        
        return $product_id;
    }
    
    /**
     * Обновить товар
     */
    public function updateProduct($product_id, $product_data) {
        // Обновить основной товар
        $updates = [];
        $params = [];
        
        $fields = ['model', 'sku', 'quantity', 'price', 'status', 'weight', 'length', 'width', 'height'];
        
        foreach ($fields as $field) {
            if (isset($product_data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $product_data[$field];
            }
        }
        
        if (!empty($updates)) {
            $params[] = $product_id;
            $this->db->query(
                "UPDATE `oc_product` SET " . implode(', ', $updates) . " WHERE product_id = ?",
                $params
            );
        }
        
        // Обновить описание
        if (isset($product_data['name']) || isset($product_data['description'])) {
            $desc_updates = [];
            $desc_params = [];
            
            if (isset($product_data['name'])) {
                $desc_updates[] = "name = ?";
                $desc_params[] = $product_data['name'];
            }
            
            if (isset($product_data['description'])) {
                $desc_updates[] = "description = ?";
                $desc_params[] = $product_data['description'];
            }
            
            if (!empty($desc_updates)) {
                $desc_params[] = $product_id;
                $desc_params[] = $product_data['language_id'] ?? 1;
                $this->db->query(
                    "UPDATE `oc_product_description` SET " . implode(', ', $desc_updates) . " WHERE product_id = ? AND language_id = ?",
                    $desc_params
                );
            }
        }
        
        // Обновить категорию
        if (isset($product_data['category_id'])) {
            $this->db->query(
                "DELETE FROM `oc_product_to_category` WHERE product_id = ?",
                [$product_id]
            );
            
            $this->db->query(
                "INSERT INTO `oc_product_to_category` (product_id, category_id) VALUES (?, ?)",
                [$product_id, $product_data['category_id']]
            );
        }
    }
}
