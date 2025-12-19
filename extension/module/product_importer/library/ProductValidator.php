<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ProductValidator {
    private $errors = [];
    private $config;
    
    public function __construct($config = null) {
        $this->config = $config;
    }
    
    /**
     * Валидировать товар перед импортом
     * 
     * @param array $product Данные товара
     * @return bool True если валидна, False если ошибки
     */
    public function validate(array $product): bool {
        $this->errors = [];
        
        // Проверка обязательных полей
        if (empty($product['name'])) {
            $this->errors[] = 'Field "name" is required';
        }
        
        if (!isset($product['price']) || !is_numeric($product['price']) || $product['price'] < 0) {
            $this->errors[] = 'Field "price" must be a positive number';
        }
        
        if (!isset($product['quantity']) || !is_numeric($product['quantity']) || $product['quantity'] < 0) {
            $this->errors[] = 'Field "quantity" must be a positive integer';
        }
        
        if (!isset($product['category_id']) || !is_numeric($product['category_id']) || $product['category_id'] < 0) {
            $this->errors[] = 'Field "category_id" must be a positive integer';
        }
        
        // Валидация SKU если указан
        if (isset($product['sku']) && !empty($product['sku'])) {
            if (strlen($product['sku']) > 64) {
                $this->errors[] = 'Field "sku" cannot exceed 64 characters';
            }
        }
        
        // Валидация статуса
        if (isset($product['status']) && !in_array($product['status'], [0, 1])) {
            $this->errors[] = 'Field "status" must be 0 or 1';
        }
        
        // Валидация веса
        if (isset($product['weight'])) {
            if (!is_numeric($product['weight']) || $product['weight'] < 0) {
                $this->errors[] = 'Field "weight" must be a positive number';
            }
        }
        
        // Валидация размеров
        $dimensions = ['length', 'width', 'height'];
        foreach ($dimensions as $dim) {
            if (isset($product[$dim])) {
                if (!is_numeric($product[$dim]) || $product[$dim] < 0) {
                    $this->errors[] = "Field \"$dim\" must be a positive number";
                }
            }
        }
        
        // Валидация длины названия
        if (isset($product['name']) && strlen($product['name']) > 255) {
            $this->errors[] = 'Field "name" cannot exceed 255 characters';
        }
        
        // Валидация описания
        if (isset($product['description']) && strlen($product['description']) > 65535) {
            $this->errors[] = 'Field "description" cannot exceed 65535 characters';
        }
        
        return empty($this->errors);
    }
    
    /**
     * Валидировать список товаров
     * 
     * @param array $products Массив товаров
     * @return array Список ошибок по каждому товару
     */
    public function validateBatch(array $products): array {
        $errors = [];
        
        foreach ($products as $index => $product) {
            if (!$this->validate($product)) {
                $errors[$index] = $this->getErrors();
            }
        }
        
        return $errors;
    }
    
    /**
     * Получить список ошибок валидации
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Проверить существует ли категория
     * 
     * @param int $category_id ID категории
     * @param object $db Объект базы данных
     * @return bool
     */
    public function validateCategory($category_id, $db): bool {
        $query = $db->query(
            "SELECT category_id FROM `oc_category` WHERE category_id = ?",
            [$category_id]
        );
        
        return !empty($query->row);
    }
    
    /**
     * Проверить существует ли производитель
     * 
     * @param int $manufacturer_id ID производителя
     * @param object $db Объект базы данных
     * @return bool
     */
    public function validateManufacturer($manufacturer_id, $db): bool {
        if (empty($manufacturer_id) || $manufacturer_id == 0) {
            return true; // Производитель не обязателен
        }
        
        $query = $db->query(
            "SELECT manufacturer_id FROM `oc_manufacturer` WHERE manufacturer_id = ?",
            [$manufacturer_id]
        );
        
        return !empty($query->row);
    }
    
    /**
     * Проверить уникальность SKU для новых товаров
     * 
     * @param string $sku SKU товара
     * @param object $db Объект базы данных
     * @param int $exclude_product_id ID товара для исключения (при обновлении)
     * @return bool True если SKU уникален
     */
    public function validateUniqueSku($sku, $db, $exclude_product_id = 0): bool {
        if (empty($sku)) {
            return true; // SKU не обязателен
        }
        
        $sql = "SELECT product_id FROM `oc_product` WHERE sku = ?";
        $params = [$sku];
        
        if ($exclude_product_id > 0) {
            $sql .= " AND product_id != ?";
            $params[] = $exclude_product_id;
        }
        
        $query = $db->query($sql, $params);
        
        return empty($query->row);
    }
    
    /**
     * Очистить ошибки
     */
    public function clearErrors(): void {
        $this->errors = [];
    }
}
