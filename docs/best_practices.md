# КОНФИГУРАЦИЯ И BEST PRACTICES
## Рекомендации для разработки модуля OpenCart

---

## 1. КОНФИГУРАЦИОННЫЙ ФАЙЛ

```php
<?php
return [
    'module_name' => 'product_importer',
    'version' => '1.0.0',
    'support_opencart_versions' => ['3.0', '3.1', '3.2', '4.0', '4.1'],
    
    'import' => [
        'max_file_size_mb' => 100,
        'chunk_size' => 100,
        'timeout_seconds' => 300,
        'allowed_file_types' => ['csv', 'xlsx', 'json'],
    ],
    
    'validation' => [
        'require_fields' => ['name', 'price', 'category_id'],
        'min_price' => 0,
        'max_name_length' => 255,
    ],
    
    'performance' => [
        'enable_cache' => true,
        'cache_categories' => true,
        'cache_ttl_minutes' => 60,
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'log_to_file' => true,
        'log_to_database' => true,
        'keep_logs_days' => 30,
    ],
    
    'security' => [
        'verify_csrf' => true,
        'check_admin_permission' => true,
        'sanitize_input' => true,
        'use_prepared_statements' => true,
    ],
];
```

---

## 2. ЯЗЫКОВЫЕ СТРОКИ НА РУССКОМ

```php
<?php
$_['heading_title'] = 'Импорт товаров';
$_['text_import_products'] = 'Импортировать товары';
$_['text_manage_categories'] = 'Управлять категориями';
$_['text_upload_file'] = 'Выберите файл или перетащите его сюда';
$_['text_supported_formats'] = 'Поддерживаемые форматы: CSV, XLSX, JSON';
$_['text_max_file_size'] = 'Максимальный размер файла: 100 MB';

$_['text_mode_add'] = 'Добавить только новые товары';
$_['text_mode_update'] = 'Обновить только существующие товары';
$_['text_mode_merge'] = 'Добавить новые и обновить существующие';

$_['button_upload'] = 'Загрузить файл';
$_['button_import'] = 'Импортировать';
$_['button_cancel'] = 'Отмена';

$_['column_total'] = 'Всего записей';
$_['column_success'] = 'Успешно';
$_['column_failed'] = 'Ошибок';
$_['column_status'] = 'Статус';

$_['error_permission'] = 'У вас нет прав доступа к этому модулю';
$_['error_file_empty'] = 'Файл не выбран';
$_['error_file_type'] = 'Неподдерживаемый тип файла';
$_['error_file_size'] = 'Файл слишком большой. Максимум 100 MB';
$_['error_upload'] = 'Ошибка при загрузке файла';
$_['error_parse'] = 'Ошибка при чтении файла. Проверьте формат';

$_['success_imported'] = 'Товары успешно импортированы. Добавлено: %d, Обновлено: %d';
```

---

## 3. BEST PRACTICES

### 3.1 Обработка ошибок

```php
try {
    $parser = new ProductImporterCSVParser($filepath);
    $data = $parser->parse();
    
    if (empty($data)) {
        throw new \Exception('No data found in file');
    }
    
    $handler = new ProductImportHandler($db, $validator, $logger);
    $result = $handler->import($data, $mode);
    
    return ['success' => true, 'result' => $result];
    
} catch (\Exception $e) {
    $logger->logProduct(null, 'unknown', 'unknown', 'error', 'failed', $e->getMessage());
    return ['success' => false, 'error' => $e->getMessage()];
}
```

### 3.2 Кэширование категорий

```php
class CategoryCache {
    private $cache = [];
    
    public function load($db) {
        $query = $db->query("SELECT * FROM `oc_category`");
        foreach ($query->rows as $row) {
            $this->cache[$row['category_id']] = $row;
        }
    }
    
    public function exists($category_id) {
        return isset($this->cache[$category_id]);
    }
}

// Использование
$category_cache = new CategoryCache();
$category_cache->load($db);

if ($category_cache->exists($product['category_id'])) {
    // Товар может быть добавлен в эту категорию
}
```

### 3.3 Транзакции БД

```php
try {
    $db->query("START TRANSACTION");
    
    $db->query("INSERT INTO `oc_product` ...");
    $product_id = $db->getLastId();
    
    $db->query("INSERT INTO `oc_product_description` ...");
    $db->query("INSERT INTO `oc_product_to_category` ...");
    
    $db->query("COMMIT");
    
} catch (\Exception $e) {
    $db->query("ROLLBACK");
    throw $e;
}
```

### 3.4 Оптимизация SQL запросов

```php
// ❌ ПЛОХО: N+1 query problem
foreach ($products as $product) {
    $query = $db->query("SELECT * FROM `oc_product` WHERE id = ?", [$product['id']]);
}

// ✅ ХОРОШО: Single query with IN clause
$ids = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$query = $db->query("SELECT * FROM `oc_product` WHERE id IN ($placeholders)", $ids);
```

### 3.5 Пакетная обработка

```php
$chunk_size = 100;
$records = array_chunk($importData, $chunk_size);

foreach ($records as $chunk) {
    foreach ($chunk as $record) {
        // Обработка товара
    }
    // Очистка памяти после каждого чанка
    gc_collect_cycles();
}
```

### 3.6 Логирование

```php
class Logger {
    private $log_file;
    
    public function __construct($log_dir) {
        $this->log_file = $log_dir . 'import_' . date('Y-m-d') . '.log';
    }
    
    public function info($message) {
        $this->write('INFO', $message);
    }
    
    public function error($message) {
        $this->write('ERROR', $message);
    }
    
    private function write($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message\n";
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }
}

// Использование
$logger = new Logger(DIR_LOGS . 'product_importer/');
$logger->info('Import started for file: products.csv');
$logger->error('Failed to add product: SKU001');
```

---

## 4. ПРИМЕРЫ ТЕСТИРОВАНИЯ

### Unit тест валидатора

```php
class ProductValidatorTest {
    
    public function testValidation() {
        $validator = new ProductValidator();
        
        // Test 1: Valid product
        $product = [
            'name' => 'Test Product',
            'price' => 100,
            'category_id' => 1,
            'quantity' => 50
        ];
        
        $this->assertTrue($validator->validate($product));
        
        // Test 2: Missing required field
        unset($product['name']);
        $this->assertFalse($validator->validate($product));
        $this->assertContains('name is required', $validator->getErrors());
    }
}
```

### Integration тест импорта

```php
class ImportHandlerTest {
    
    public function testImportProducts() {
        $handler = new ProductImportHandler($db, $validator, $logger);
        
        $products = [
            ['sku' => 'SKU001', 'name' => 'Product 1', 'price' => 100, 'category_id' => 1],
            ['sku' => 'SKU002', 'name' => 'Product 2', 'price' => 200, 'category_id' => 1],
        ];
        
        $result = $handler->import($products, 'add');
        
        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }
}
```

---

## 5. МОНИТОРИНГ И ОБСЛУЖИВАНИЕ

### Очистка старых логов

```php
$keep_days = 30;
$log_dir = DIR_LOGS . 'product_importer/';

foreach (scandir($log_dir) as $file) {
    $filepath = $log_dir . $file;
    if (filemtime($filepath) < time() - ($keep_days * 86400)) {
        unlink($filepath);
    }
}

// Удалить старые записи из БД
$db->query("
    DELETE FROM `oc_product_import_log` 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
", [$keep_days]);

$db->query("
    DELETE FROM `oc_import_batch` 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
", [$keep_days]);
```

### Отслеживание производительности

```php
class PerformanceMonitor {
    private $start_time;
    private $total_records;
    
    public function start($total) {
        $this->start_time = microtime(true);
        $this->total_records = $total;
    }
    
    public function getStats($processed) {
        $elapsed = microtime(true) - $this->start_time;
        $records_per_second = $processed / $elapsed;
        $eta_seconds = ($this->total_records - $processed) / $records_per_second;
        
        return [
            'elapsed_seconds' => round($elapsed, 2),
            'records_per_second' => round($records_per_second, 2),
            'eta_readable' => gmdate('H:i:s', $eta_seconds)
        ];
    }
}
```

---

## 6. РЕКОМЕНДАЦИИ

1. **Используйте подготовленные запросы** для всех SQL операций
2. **Валидируйте все входные данные** перед обработкой
3. **Кэшируйте часто используемые данные** (категории, производители)
4. **Логируйте все операции** для отладки и аудита
5. **Тестируйте на разных версиях** OpenCart (3.x и 4.x)
6. **Обрабатывайте исключения** на всех уровнях
7. **Очищайте память** после больших операций (gc_collect_cycles())
8. **Используйте транзакции** для критичных операций

---

## 7. КОНТРОЛЬНЫЙ СПИСОК РАЗРАБОТКИ

- [ ] Все требования из ТЗ реализованы
- [ ] Код протестирован и документирован
- [ ] Безопасность проверена (SQL-injection, XSS, CSRF)
- [ ] Производительность оптимизирована (1000 товаров < 60 сек)
- [ ] Логирование функционирует
- [ ] Совместимость с OpenCart 3.x и 4.x подтверждена
- [ ] README.md и документация написаны
- [ ] Модуль готов к релизу

---

**Версия:** 1.0  
**Дата:** 19.12.2025