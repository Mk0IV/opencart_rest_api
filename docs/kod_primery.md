# ПРИМЕРЫ КОДА И ГОТОВЫЕ КЛАССЫ
## Полный исходный код для разработки модуля OpenCart

---

## 1. ПАРСЕР CSV

```php
<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ProductImporterCSVParser {
    private $file_path;
    private $delimiter = ',';
    private $enclosure = '"';
    
    public function __construct($file_path) {
        $this->file_path = $file_path;
    }
    
    private function detectDelimiter() {
        $delimiters = [',', ';', "\t"];
        $counts = [];
        
        if (($handle = fopen($this->file_path, 'r')) !== FALSE) {
            $first_line = fgets($handle);
            fclose($handle);
            
            foreach ($delimiters as $delimiter) {
                $counts[$delimiter] = count(str_getcsv($first_line, $delimiter));
            }
            
            $this->delimiter = array_key_first($counts);
        }
    }
    
    public function parse() {
        $this->detectDelimiter();
        $data = [];
        $header = [];
        
        if (($handle = fopen($this->file_path, 'r')) !== FALSE) {
            $row = 0;
            
            while (($cols = fgetcsv($handle, 0, $this->delimiter, $this->enclosure)) !== FALSE) {
                if ($row == 0) {
                    $header = $cols;
                } else {
                    $record = [];
                    foreach ($header as $index => $col_name) {
                        $record[trim($col_name)] = $cols[$index] ?? null;
                    }
                    $data[] = $record;
                }
                $row++;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    public function getPreview($limit = 10) {
        $this->detectDelimiter();
        $data = [];
        $header = [];
        $count = 0;
        
        if (($handle = fopen($this->file_path, 'r')) !== FALSE) {
            $row = 0;
            
            while (($cols = fgetcsv($handle, 0, $this->delimiter, $this->enclosure)) !== FALSE && $count < $limit + 1) {
                if ($row == 0) {
                    $header = $cols;
                } else {
                    $record = [];
                    foreach ($header as $index => $col_name) {
                        $record[trim($col_name)] = $cols[$index] ?? null;
                    }
                    $data[] = $record;
                    $count++;
                }
                $row++;
            }
            fclose($handle);
        }
        
        return ['header' => array_map('trim', $header), 'data' => $data];
    }
}
```

---

## 2. ВАЛИДАТОР ТОВАРОВ

```php
<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ProductValidator {
    private $errors = [];
    private $warnings = [];
    
    public function validate($product) {
        $this->errors = [];
        $this->warnings = [];
        
        // Обязательные поля
        if (empty($product['name'])) {
            $this->errors[] = 'Field "name" is required';
        }
        
        if (empty($product['price']) || !is_numeric($product['price']) || $product['price'] < 0) {
            $this->errors[] = 'Field "price" must be a positive number';
        }
        
        if (empty($product['category_id']) || !is_numeric($product['category_id'])) {
            $this->errors[] = 'Field "category_id" must be a valid number';
        }
        
        if (isset($product['quantity']) && !is_numeric($product['quantity'])) {
            $this->errors[] = 'Field "quantity" must be a number';
        }
        
        if (isset($product['weight']) && !is_numeric($product['weight'])) {
            $this->errors[] = 'Field "weight" must be a number';
        }
        
        if (isset($product['status']) && !in_array($product['status'], [0, 1, '0', '1'])) {
            $this->errors[] = 'Field "status" must be 0 or 1';
        }
        
        return empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public static function sanitize($product) {
        $sanitized = [];
        
        $allowed_fields = [
            'product_id', 'sku', 'name', 'description',
            'meta_description', 'model', 'image', 'price', 'quantity', 'weight',
            'manufacturer_id', 'category_id', 'status', 'sort_order'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($product[$field])) {
                $sanitized[$field] = trim($product[$field]);
            }
        }
        
        return $sanitized;
    }
}
```

---

## 3. ЛОГГЕР

```php
<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ImportLogger {
    private $db;
    private $batch_id;
    
    public function __construct($db, $batch_id = null) {
        $this->db = $db;
        $this->batch_id = $batch_id;
    }
    
    public function createBatch($filename, $file_type, $total_records, $mode, $admin_id) {
        $this->db->query("INSERT INTO `oc_import_batch` 
            (filename, file_type, total_records, processed_records, success_records, failed_records, status, mode, admin_id, created_at) 
            VALUES (?, ?, ?, 0, 0, 0, 'processing', ?, ?, NOW())", 
            [$filename, $file_type, $total_records, $mode, $admin_id]);
        
        $this->batch_id = $this->db->getLastId();
        
        return $this->batch_id;
    }
    
    public function logProduct($product_id, $sku, $name, $action, $status, $error_message = '') {
        $this->db->query("INSERT INTO `oc_product_import_log` 
            (import_batch_id, product_id, sku, name, action, status, error_message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 
            [$this->batch_id, $product_id ?? null, $sku, $name, $action, $status, $error_message]);
    }
    
    public function updateBatchStatus($success_count, $failed_count) {
        $this->db->query("UPDATE `oc_import_batch` 
            SET processed_records = ?, success_records = ?, failed_records = ?, status = ?, updated_at = NOW() 
            WHERE id = ?", 
            [$success_count + $failed_count, $success_count, $failed_count, 'completed', $this->batch_id]);
    }
}
```

---

## 4. ОБРАБОТЧИК ИМПОРТА

```php
<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Model\Extension\Module;

use Opencart\Catalog\Extension\Module\ProductImporter\Library\ProductValidator;
use Opencart\Catalog\Extension\Module\ProductImporter\Library\ImportLogger;

class ProductImportHandler {
    private $db;
    private $validator;
    private $logger;
    
    public function __construct($db, $validator, $logger) {
        $this->db = $db;
        $this->validator = $validator;
        $this->logger = $logger;
    }
    
    public function import($products, $mode = 'merge') {
        $success_count = 0;
        $failed_count = 0;
        $chunk_size = 100;
        
        $chunks = array_chunk($products, $chunk_size);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $product_data) {
                try {
                    $product = ProductValidator::sanitize($product_data);
                    
                    if (!$this->validator->validate($product)) {
                        $errors = implode('; ', $this->validator->getErrors());
                        $this->logger->logProduct(null, $product['sku'] ?? 'unknown', 
                            $product['name'] ?? 'unknown', 'error', 'failed', $errors);
                        $failed_count++;
                        continue;
                    }
                    
                    $existing_product = $this->findProduct($product['sku'] ?? null, $product['product_id'] ?? null);
                    
                    if ($existing_product && in_array($mode, ['update', 'merge'])) {
                        $this->updateProduct($existing_product['product_id'], $product);
                        $this->logger->logProduct($existing_product['product_id'], $product['sku'], 
                            $product['name'], 'update', 'success');
                    } elseif (!$existing_product && in_array($mode, ['add', 'merge'])) {
                        $new_product_id = $this->addProduct($product);
                        $this->logger->logProduct($new_product_id, $product['sku'], 
                            $product['name'], 'insert', 'success');
                    } else {
                        continue;
                    }
                    
                    $success_count++;
                    
                } catch (\Exception $e) {
                    $this->logger->logProduct(null, $product_data['sku'] ?? 'unknown', 
                        $product_data['name'] ?? 'unknown', 'error', 'failed', $e->getMessage());
                    $failed_count++;
                }
            }
            
            gc_collect_cycles();
        }
        
        $this->logger->updateBatchStatus($success_count, $failed_count);
        
        return [
            'total' => count($products),
            'success' => $success_count,
            'failed' => $failed_count
        ];
    }
    
    private function findProduct($sku, $product_id) {
        if ($product_id) {
            $query = $this->db->query("SELECT * FROM `oc_product` WHERE product_id = ?", [$product_id]);
            return $query->row;
        }
        
        if ($sku) {
            $query = $this->db->query("SELECT * FROM `oc_product` WHERE sku = ?", [$sku]);
            return $query->row;
        }
        
        return false;
    }
    
    private function addProduct($product) {
        $this->db->query("INSERT INTO `oc_product` 
            (model, sku, quantity, stock_status_id, image, manufacturer_id, price, weight, 
             status, sort_order, date_added) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $product['model'] ?? '',
                $product['sku'] ?? '',
                $product['quantity'] ?? 0,
                ($product['quantity'] ?? 0) > 0 ? 5 : 7,
                $product['image'] ?? '',
                $product['manufacturer_id'] ?? 0,
                $product['price'] ?? 0,
                $product['weight'] ?? 0,
                $product['status'] ?? 1,
                $product['sort_order'] ?? 0
            ]
        );
        
        $product_id = $this->db->getLastId();
        
        $this->db->query("INSERT INTO `oc_product_description` 
            (product_id, language_id, name, description) 
            VALUES (?, ?, ?, ?)",
            [$product_id, 1, $product['name'], $product['description'] ?? '']
        );
        
        $this->db->query("INSERT INTO `oc_product_to_category` (product_id, category_id) VALUES (?, ?)",
            [$product_id, $product['category_id'] ?? 0]);
        
        $this->db->query("INSERT INTO `oc_product_to_store` (product_id, store_id) VALUES (?, ?)",
            [$product_id, 0]);
        
        return $product_id;
    }
    
    private function updateProduct($product_id, $product) {
        $update_data = [];
        $params = [];
        
        $updateable_fields = ['model', 'sku', 'quantity', 'image', 'manufacturer_id', 'price', 'weight', 'status'];
        
        foreach ($updateable_fields as $field) {
            if (isset($product[$field])) {
                $update_data[] = "`{$field}` = ?";
                $params[] = $product[$field];
            }
        }
        
        if (!empty($update_data)) {
            $params[] = $product_id;
            $this->db->query("UPDATE `oc_product` SET " . implode(', ', $update_data) . " WHERE product_id = ?", $params);
        }
        
        if (isset($product['name']) || isset($product['description'])) {
            $desc_params = [];
            $update_desc = [];
            
            if (isset($product['name'])) {
                $update_desc[] = "`name` = ?";
                $desc_params[] = $product['name'];
            }
            
            if (isset($product['description'])) {
                $update_desc[] = "`description` = ?";
                $desc_params[] = $product['description'];
            }
            
            if (!empty($update_desc)) {
                $desc_params[] = $product_id;
                $desc_params[] = 1;
                $this->db->query("UPDATE `oc_product_description` SET " . implode(', ', $update_desc) . " WHERE product_id = ? AND language_id = ?", $desc_params);
            }
        }
    }
}
```

---

## 5. SQL ДЛЯ ТАБЛИЦ

```sql
CREATE TABLE IF NOT EXISTS `oc_import_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `total_records` int(11) NOT NULL DEFAULT 0,
  `processed_records` int(11) NOT NULL DEFAULT 0,
  `success_records` int(11) NOT NULL DEFAULT 0,
  `failed_records` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `mode` varchar(20) NOT NULL DEFAULT 'merge',
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `oc_product_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_batch_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `error_message` longtext,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `import_batch_id` (`import_batch_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 6. ПРИМЕРЫ ДАННЫХ

### CSV пример:
```csv
sku,name,price,category_id,description,quantity,status,manufacturer_id
SKU001,Смартфон Samsung,45000,5,Флагманский смартфон,50,1,2
SKU002,Ноутбук Dell,75000,6,Мощный ноутбук,30,1,3
SKU003,Наушники Sony,35000,7,Беспроводные наушники,100,1,4
```

### JSON пример:
```json
{
  "products": [
    {
      "sku": "SKU001",
      "name": "Смартфон Samsung",
      "price": 45000,
      "category_id": 5,
      "description": "Флагманский смартфон",
      "quantity": 50,
      "status": 1,
      "manufacturer_id": 2
    }
  ]
}
```

---

**Версия:** 1.0  
**Дата:** 19.12.2025