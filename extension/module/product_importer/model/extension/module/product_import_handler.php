<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Model\Extension\Module;

use Opencart\Catalog\Extension\Module\ProductImporter\Library\ProductValidator;
use Opencart\Catalog\Extension\Module\ProductImporter\Library\ImportLogger;

class ProductImportHandler {
    private $db;
    private $validator;
    private $logger;
    private $batch_id;
    
    public function __construct($db, ProductValidator $validator, ImportLogger $logger) {
        $this->db = $db;
        $this->validator = $validator;
        $this->logger = $logger;
    }
    
    /**
     * Импортировать товары
     * 
     * @param array $products Массив товаров
     * @param string $mode Режим импорта (add, update, merge)
     * @param int $chunkSize Размер пакета
     * @return array Результаты импорта
     */
    public function import(array $products, string $mode = 'merge', int $chunkSize = 100): array {
        $this->batch_id = $this->logger->createBatch(
            'api_import_' . date('Y-m-d_H-i-s'),
            'json',
            count($products),
            $mode,
            0
        );
        
        $this->logger->updateBatch($this->batch_id, 'processing');
        
        $total = count($products);
        $success = 0;
        $failed = 0;
        $processed = 0;
        
        // Обработка пакетами
        $chunks = array_chunk($products, $chunkSize);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $index => $product) {
                try {
                    $result = $this->processProduct($product, $mode);
                    
                    if ($result['success']) {
                        $success++;
                        $this->logger->logProduct(
                            $this->batch_id,
                            $result['product_id'],
                            $product['sku'] ?? '',
                            $product['name'] ?? '',
                            $result['action'],
                            'success'
                        );
                    } else {
                        $failed++;
                        $this->logger->logProduct(
                            $this->batch_id,
                            0,
                            $product['sku'] ?? '',
                            $product['name'] ?? '',
                            'error',
                            'error',
                            $result['error']
                        );
                    }
                    
                    $processed++;
                    
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->logProduct(
                        $this->batch_id,
                        0,
                        $product['sku'] ?? '',
                        $product['name'] ?? '',
                        'error',
                        'error',
                        $e->getMessage()
                    );
                    $processed++;
                }
            }
        }
        
        // Обновить финальный статус
        $status = ($failed > 0) ? 'completed' : 'completed';
        $this->logger->updateBatch($this->batch_id, $status, $processed, $success, $failed);
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'batch_id' => $this->batch_id
        ];
    }
    
    /**
     * Обработать один товар
     * 
     * @param array $product Данные товара
     * @param string $mode Режим импорта
     * @return array Результат обработки
     */
    private function processProduct(array $product, string $mode): array {
        // Валидация
        if (!$this->validator->validate($product)) {
            return [
                'success' => false,
                'error' => implode(', ', $this->validator->getErrors())
            ];
        }
        
        // Проверка категории
        if (!$this->validator->validateCategory($product['category_id'], $this->db)) {
            return [
                'success' => false,
                'error' => 'Category not found'
            ];
        }
        
        // Проверка производителя
        if (isset($product['manufacturer_id']) && 
            !$this->validator->validateManufacturer($product['manufacturer_id'], $this->db)) {
            return [
                'success' => false,
                'error' => 'Manufacturer not found'
            ];
        }
        
        $existingProduct = null;
        
        // Поиск существующего товара по SKU
        if (!empty($product['sku'])) {
            $existingProduct = $this->findProductBySku($product['sku']);
        }
        
        $action = '';
        
        // Логика в зависимости от режима
        switch ($mode) {
            case 'add':
                if ($existingProduct) {
                    return [
                        'success' => false,
                        'error' => 'Product already exists'
                    ];
                }
                $product_id = $this->createProduct($product);
                $action = 'insert';
                break;
                
            case 'update':
                if (!$existingProduct) {
                    return [
                        'success' => false,
                        'error' => 'Product not found'
                    ];
                }
                $this->updateProduct($existingProduct['product_id'], $product);
                $product_id = $existingProduct['product_id'];
                $action = 'update';
                break;
                
            case 'merge':
            default:
                if ($existingProduct) {
                    $this->updateProduct($existingProduct['product_id'], $product);
                    $product_id = $existingProduct['product_id'];
                    $action = 'update';
                } else {
                    $product_id = $this->createProduct($product);
                    $action = 'insert';
                }
                break;
        }
        
        return [
            'success' => true,
            'product_id' => $product_id,
            'action' => $action
        ];
    }
    
    /**
     * Найти товар по SKU
     * 
     * @param string $sku
     * @return array|null
     */
    private function findProductBySku($sku) {
        $query = $this->db->query(
            "SELECT p.* FROM `oc_product` p WHERE p.sku = ?",
            [$sku]
        );
        
        return $query->row ?: null;
    }
    
    /**
     * Создать товар
     * 
     * @param array $product
     * @return int ID созданного товара
     */
    private function createProduct($product) {
        // Вставить основной товар
        $this->db->query(
            "INSERT INTO `oc_product` (model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $product['model'] ?? '',
                $product['sku'] ?? '',
                $product['upc'] ?? '',
                $product['ean'] ?? '',
                $product['jan'] ?? '',
                $product['isbn'] ?? '',
                $product['mpn'] ?? '',
                $product['location'] ?? '',
                $product['quantity'] ?? 0,
                $product['stock_status_id'] ?? 7,
                $product['image'] ?? '',
                $product['manufacturer_id'] ?? 0,
                $product['shipping'] ?? 1,
                $product['price'] ?? 0,
                $product['points'] ?? 0,
                $product['tax_class_id'] ?? 0,
                $product['date_available'] ?? date('Y-m-d'),
                $product['weight'] ?? 0,
                $product['weight_class_id'] ?? 1,
                $product['length'] ?? 0,
                $product['width'] ?? 0,
                $product['height'] ?? 0,
                $product['length_class_id'] ?? 1,
                $product['subtract'] ?? 1,
                $product['minimum'] ?? 1,
                $product['sort_order'] ?? 0,
                $product['status'] ?? 1
            ]
        );
        
        $product_id = $this->db->getLastId();
        
        // Вставить описание
        $this->db->query(
            "INSERT INTO `oc_product_description` (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $product_id,
                $product['language_id'] ?? 1,
                $product['name'],
                $product['description'] ?? '',
                $product['tag'] ?? '',
                $product['meta_title'] ?? $product['name'],
                $product['meta_description'] ?? '',
                $product['meta_keyword'] ?? ''
            ]
        );
        
        // Привязать к категории
        if (!empty($product['category_id'])) {
            $this->db->query(
                "INSERT INTO `oc_product_to_category` (product_id, category_id) VALUES (?, ?)",
                [$product_id, $product['category_id']]
            );
        }
        
        // Добавить атрибуты если есть
        if (!empty($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
                $this->db->query(
                    "INSERT INTO `oc_product_attribute` (product_id, attribute_id, language_id, text) VALUES (?, ?, ?, ?)",
                    [$product_id, $attribute['attribute_id'], $product['language_id'] ?? 1, $attribute['text']]
                );
            }
        }
        
        return $product_id;
    }
    
    /**
     * Обновить товар
     * 
     * @param int $product_id
     * @param array $product
     */
    private function updateProduct($product_id, $product) {
        // Обновить основной товар
        $updates = [];
        $params = [];
        
        $fields = ['model', 'sku', 'quantity', 'price', 'status', 'weight', 'length', 'width', 'height'];
        
        foreach ($fields as $field) {
            if (isset($product[$field])) {
                $updates[] = "$field = ?";
                $params[] = $product[$field];
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
        if (isset($product['name']) || isset($product['description'])) {
            $desc_updates = [];
            $desc_params = [];
            
            if (isset($product['name'])) {
                $desc_updates[] = "name = ?";
                $desc_params[] = $product['name'];
            }
            
            if (isset($product['description'])) {
                $desc_updates[] = "description = ?";
                $desc_params[] = $product['description'];
            }
            
            if (!empty($desc_updates)) {
                $desc_params[] = $product_id;
                $desc_params[] = $product['language_id'] ?? 1;
                $this->db->query(
                    "UPDATE `oc_product_description` SET " . implode(', ', $desc_updates) . " WHERE product_id = ? AND language_id = ?",
                    $desc_params
                );
            }
        }
        
        // Обновить категорию
        if (isset($product['category_id'])) {
            $this->db->query(
                "DELETE FROM `oc_product_to_category` WHERE product_id = ?",
                [$product_id]
            );
            
            $this->db->query(
                "INSERT INTO `oc_product_to_category` (product_id, category_id) VALUES (?, ?)",
                [$product_id, $product['category_id']]
            );
        }
    }
}
