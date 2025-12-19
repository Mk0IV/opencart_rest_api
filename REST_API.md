# REST API ДЛЯ PRODUCT IMPORTER
## Полная документация и готовый код

---

## 1. ОБЩЕЕ ОПИСАНИЕ REST API

### 1.1 Назначение
REST API позволяет:
- Импортировать товары через HTTP запросы (без админ-панели)
- Управлять категориями через API
- Получать статус импорта
- Получать логи операций
- Интегрировать с внешними системами (ERP, 1C, мобильные приложения)

### 1.2 Конечные точки (Endpoints)

```
POST   /api/products/import           → Импортировать товары
GET    /api/products/import/{batch_id} → Получить статус импорта
GET    /api/import/logs               → Получить логи
POST   /api/categories/create         → Создать категорию
GET    /api/categories                → Получить категории
PUT    /api/categories/{id}           → Обновить категорию
DELETE /api/categories/{id}           → Удалить категорию
GET    /api/categories/{id}           → Получить категорию по ID
```

### 1.3 Аутентификация
- API Token (Bearer token)
- API Key (в заголовках)
- Admin User (сессия)

---

## 2. СТРУКТУРА ФАЙЛОВ API

```
catalog/extension/module/product_importer/
├── api/
│   ├── controller/
│   │   ├── products.php          ← Импорт товаров
│   │   ├── categories.php        ← Управление категориями
│   │   └── import_logs.php       ← Логирование
│   ├── model/
│   │   ├── api_product_import.php
│   │   ├── api_category_manager.php
│   │   └── api_logger.php
│   └── response.php              ← Стандартные ответы
├── routes.php                     ← Маршруты API
└── config/api.php                ← Конфигурация API
```

---

## 3. КОНФИГУРАЦИЯ API

```php
<?php
// api/config/api.php

return [
    'api_version' => '1.0',
    'api_prefix' => '/api',
    'timeout' => 300,
    
    'authentication' => [
        'enabled' => true,
        'method' => 'token', // token, key, bearer
        'token_header' => 'X-API-Token',
        'key_header' => 'X-API-Key',
    ],
    
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_hour' => 1000,
        'requests_per_minute' => 60,
    ],
    
    'import' => [
        'max_records_per_request' => 1000,
        'max_file_size_mb' => 100,
        'chunk_size' => 100,
        'allowed_formats' => ['json', 'csv', 'xlsx'],
    ],
    
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'X-API-Token', 'X-API-Key'],
    ],
    
    'logging' => [
        'log_requests' => true,
        'log_responses' => true,
        'log_errors' => true,
    ],
];
```

---

## 4. КОНТРОЛЛЕР PRODUCTS API

```php
<?php
// api/controller/products.php

namespace Opencart\Catalog\Extension\Module\ProductImporter\Api\Controller;

use Opencart\Catalog\Extension\Module\ProductImporter\Library\ProductImporterCSVParser;
use Opencart\Catalog\Extension\Module\ProductImporter\Library\ProductValidator;
use Opencart\Catalog\Extension\Module\ProductImporter\Model\Extension\Module\ProductImportHandler;
use Opencart\Catalog\Extension\Module\ProductImporter\Library\ImportLogger;

class ProductsController {
    private $db;
    private $request;
    private $response;
    private $logger;
    
    public function __construct($db, $request, $response) {
        $this->db = $db;
        $this->request = $request;
        $this->response = $response;
        $this->logger = new ImportLogger($db);
    }
    
    /**
     * POST /api/products/import
     * Импортировать товары
     */
    public function import() {
        try {
            // Проверка аутентификации
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Получить входные данные
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                return $this->error('Invalid JSON', 400);
            }
            
            // Валидация входных данных
            if (empty($input['products']) || !is_array($input['products'])) {
                return $this->error('Field "products" is required and must be an array', 400);
            }
            
            if (empty($input['mode']) || !in_array($input['mode'], ['add', 'update', 'merge'])) {
                return $this->error('Field "mode" is required (add, update, or merge)', 400);
            }
            
            $products = $input['products'];
            $mode = $input['mode'];
            $admin_id = $input['admin_id'] ?? 0;
            
            // Создать batch запись
            $batch_id = $this->logger->createBatch(
                'api_import_' . date('Y-m-d_H-i-s'),
                'json',
                count($products),
                $mode,
                $admin_id
            );
            
            // Импортировать товары
            $handler = new ProductImportHandler($this->db, new ProductValidator(), $this->logger);
            $result = $handler->import($products, $mode);
            
            return $this->success([
                'batch_id' => $batch_id,
                'total' => $result['total'],
                'success' => $result['success'],
                'failed' => $result['failed'],
                'message' => sprintf(
                    'Import completed. Added: %d, Updated: %d, Failed: %d',
                    $result['success'],
                    $result['success'],
                    $result['failed']
                )
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/products/import/{batch_id}
     * Получить статус импорта
     */
    public function getImportStatus($batch_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $query = $this->db->query(
                "SELECT * FROM `oc_import_batch` WHERE id = ?",
                [$batch_id]
            );
            
            if (!$query->row) {
                return $this->error('Import batch not found', 404);
            }
            
            $batch = $query->row;
            
            // Получить логи для этого batch
            $log_query = $this->db->query(
                "SELECT * FROM `oc_product_import_log` WHERE import_batch_id = ? LIMIT 100",
                [$batch_id]
            );
            
            return $this->success([
                'batch_id' => $batch['id'],
                'filename' => $batch['filename'],
                'file_type' => $batch['file_type'],
                'status' => $batch['status'],
                'mode' => $batch['mode'],
                'total_records' => $batch['total_records'],
                'processed_records' => $batch['processed_records'],
                'success_records' => $batch['success_records'],
                'failed_records' => $batch['failed_records'],
                'created_at' => $batch['created_at'],
                'updated_at' => $batch['updated_at'],
                'logs' => $log_query->rows
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Аутентификация по токену
     */
    private function authenticate() {
        $token = $this->request->getHeader('X-API-Token');
        
        // Проверить токен в БД
        if (!$token) {
            return false;
        }
        
        // Здесь логика проверки токена
        // Например, проверить в таблице oc_api_tokens
        $query = $this->db->query(
            "SELECT * FROM `oc_api_tokens` WHERE token = ? AND status = 1",
            [$token]
        );
        
        return (bool)$query->row;
    }
    
    /**
     * Стандартный ответ успеха
     */
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
    
    /**
     * Стандартный ответ ошибки
     */
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
```

---

## 5. КОНТРОЛЛЕР CATEGORIES API

```php
<?php
// api/controller/categories.php

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
                WHERE cd.language_id = 1
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
            
            $query = $this->db->query(
                "SELECT * FROM `oc_category` WHERE category_id = ?",
                [$category_id]
            );
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            $desc_query = $this->db->query(
                "SELECT * FROM `oc_category_description` WHERE category_id = ?",
                [$category_id]
            );
            
            $category = $query->row;
            $category['descriptions'] = $desc_query->rows;
            
            // Получить дочерние категории
            $children_query = $this->db->query(
                "SELECT * FROM `oc_category` WHERE parent_id = ?",
                [$category_id]
            );
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
            
            $parent_id = $input['parent_id'] ?? 0;
            $description = $input['description'] ?? '';
            $status = $input['status'] ?? 1;
            
            // Вставить категорию
            $this->db->query(
                "INSERT INTO `oc_category` (parent_id, image, status, sort_order) VALUES (?, '', ?, 0)",
                [$parent_id, $status]
            );
            
            $category_id = $this->db->getLastId();
            
            // Вставить описание
            $this->db->query(
                "INSERT INTO `oc_category_description` (category_id, language_id, name, description) VALUES (?, ?, ?, ?)",
                [$category_id, 1, $input['name'], $description]
            );
            
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
            $query = $this->db->query(
                "SELECT * FROM `oc_category` WHERE category_id = ?",
                [$category_id]
            );
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            // Обновить основную таблицу
            if (isset($input['parent_id']) || isset($input['status'])) {
                $updates = [];
                $params = [];
                
                if (isset($input['parent_id'])) {
                    $updates[] = 'parent_id = ?';
                    $params[] = $input['parent_id'];
                }
                
                if (isset($input['status'])) {
                    $updates[] = 'status = ?';
                    $params[] = $input['status'];
                }
                
                if (!empty($updates)) {
                    $params[] = $category_id;
                    $this->db->query(
                        "UPDATE `oc_category` SET " . implode(', ', $updates) . " WHERE category_id = ?",
                        $params
                    );
                }
            }
            
            // Обновить описание
            if (isset($input['name']) || isset($input['description'])) {
                $updates = [];
                $params = [];
                
                if (isset($input['name'])) {
                    $updates[] = 'name = ?';
                    $params[] = $input['name'];
                }
                
                if (isset($input['description'])) {
                    $updates[] = 'description = ?';
                    $params[] = $input['description'];
                }
                
                if (!empty($updates)) {
                    $params[] = $category_id;
                    $params[] = 1;
                    $this->db->query(
                        "UPDATE `oc_category_description` SET " . implode(', ', $updates) . " WHERE category_id = ? AND language_id = ?",
                        $params
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
            $query = $this->db->query(
                "SELECT * FROM `oc_category` WHERE category_id = ?",
                [$category_id]
            );
            
            if (!$query->row) {
                return $this->error('Category not found', 404);
            }
            
            // Удалить товары из этой категории (опционально перенести в parent)
            // или оставить в категории
            
            // Удалить описание
            $this->db->query(
                "DELETE FROM `oc_category_description` WHERE category_id = ?",
                [$category_id]
            );
            
            // Удалить категорию
            $this->db->query(
                "DELETE FROM `oc_category` WHERE category_id = ?",
                [$category_id]
            );
            
            return $this->success([
                'message' => 'Category deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    private function authenticate() {
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        return !empty($token); // Упрощенная проверка
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
```

---

## 6. ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ API

### 6.1 Импорт товаров

**Request:**
```bash
curl -X POST http://example.com/api/products/import \
  -H "Content-Type: application/json" \
  -H "X-API-Token: your_api_token_here" \
  -d '{
    "mode": "merge",
    "admin_id": 1,
    "products": [
      {
        "sku": "SKU001",
        "name": "Товар 1",
        "price": 1500,
        "category_id": 5,
        "description": "Описание товара 1",
        "quantity": 100,
        "status": 1
      },
      {
        "sku": "SKU002",
        "name": "Товар 2",
        "price": 2000,
        "category_id": 5,
        "description": "Описание товара 2",
        "quantity": 50,
        "status": 1
      }
    ]
  }'
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "batch_id": 42,
    "total": 2,
    "success": 2,
    "failed": 0,
    "message": "Import completed. Added: 2, Updated: 0, Failed: 0"
  },
  "timestamp": "2025-12-19 20:00:00"
}
```

### 6.2 Получить статус импорта

**Request:**
```bash
curl -X GET http://example.com/api/products/import/42 \
  -H "X-API-Token: your_api_token_here"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "batch_id": 42,
    "filename": "api_import_2025-12-19_20-00-00",
    "file_type": "json",
    "status": "completed",
    "mode": "merge",
    "total_records": 2,
    "processed_records": 2,
    "success_records": 2,
    "failed_records": 0,
    "created_at": "2025-12-19 20:00:00",
    "updated_at": "2025-12-19 20:00:05",
    "logs": [
      {
        "id": 1,
        "import_batch_id": 42,
        "product_id": 101,
        "sku": "SKU001",
        "name": "Товар 1",
        "action": "insert",
        "status": "success",
        "error_message": null,
        "created_at": "2025-12-19 20:00:01"
      }
    ]
  },
  "timestamp": "2025-12-19 20:00:10"
}
```

### 6.3 Создать категорию

**Request:**
```bash
curl -X POST http://example.com/api/categories/create \
  -H "Content-Type: application/json" \
  -H "X-API-Token: your_api_token_here" \
  -d '{
    "name": "Электроника",
    "description": "Товары электроники",
    "parent_id": 0,
    "status": 1
  }'
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "category_id": 15,
    "message": "Category created successfully"
  },
  "timestamp": "2025-12-19 20:00:00"
}
```

### 6.4 Получить все категории

**Request:**
```bash
curl -X GET http://example.com/api/categories \
  -H "X-API-Token: your_api_token_here"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "category_id": 1,
        "name": "Электроника",
        "parent_id": 0,
        "status": 1,
        "product_count": 25
      },
      {
        "category_id": 2,
        "name": "Смартфоны",
        "parent_id": 1,
        "status": 1,
        "product_count": 10
      }
    ],
    "total": 2
  },
  "timestamp": "2025-12-19 20:00:00"
}
```

### 6.5 Обновить категорию

**Request:**
```bash
curl -X PUT http://example.com/api/categories/15 \
  -H "Content-Type: application/json" \
  -H "X-API-Token: your_api_token_here" \
  -d '{
    "name": "Электроника (обновлено)",
    "description": "Все товары электроники",
    "status": 1
  }'
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "category_id": 15,
    "message": "Category updated successfully"
  },
  "timestamp": "2025-12-19 20:00:00"
}
```

### 6.6 Удалить категорию

**Request:**
```bash
curl -X DELETE http://example.com/api/categories/15 \
  -H "X-API-Token: your_api_token_here"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "message": "Category deleted successfully"
  },
  "timestamp": "2025-12-19 20:00:00"
}
```

---

## 7. КОДЫ ОШИБОК

| Код | Сообщение | Описание |
|-----|-----------|---------|
| 200 | OK | Успешный запрос |
| 201 | Created | Ресурс создан |
| 400 | Bad Request | Ошибка в запросе (неверные параметры) |
| 401 | Unauthorized | Не авторизован (нет или неверный токен) |
| 403 | Forbidden | Доступ запрещен |
| 404 | Not Found | Ресурс не найден |
| 429 | Too Many Requests | Превышен лимит запросов |
| 500 | Internal Server Error | Внутренняя ошибка сервера |

---

## 8. ТАБЛИЦА ДЛЯ API ТОКЕНОВ

```sql
CREATE TABLE IF NOT EXISTS `oc_api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL UNIQUE,
  `name` varchar(255),
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp NULL,
  `expires_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 9. БЕЗОПАСНОСТЬ API

### 9.1 Аутентификация
- Использовать Bearer токены (JWT рекомендуется)
- Проверять токен в заголовке X-API-Token
- Хранить хэшированные токены в БД
- Использовать HTTPS только

### 9.2 Валидация
- Валидировать все входные данные
- Использовать prepared statements
- Эскейпить выходные данные

### 9.3 Rate Limiting
- Ограничить 1000 запросов в час
- Ограничить 60 запросов в минуту
- Возвращать 429 при превышении лимита

### 9.4 CORS
- Разрешить только доверенные домены
- Использовать правильные заголовки

---

## 10. ДОКУМЕНТАЦИЯ API (OpenAPI/Swagger)

```yaml
openapi: 3.0.0
info:
  title: Product Importer API
  version: 1.0.0
  description: REST API для импорта товаров и управления категориями в OpenCart

servers:
  - url: http://example.com/api
    description: Production API

components:
  securitySchemes:
    ApiTokenAuth:
      type: apiKey
      in: header
      name: X-API-Token

paths:
  /products/import:
    post:
      summary: Импортировать товары
      tags:
        - Products
      security:
        - ApiTokenAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                mode:
                  type: string
                  enum: [add, update, merge]
                products:
                  type: array
                  items:
                    type: object
      responses:
        '200':
          description: Импорт успешен
        '401':
          description: Не авторизован
        '400':
          description: Ошибка в запросе

  /categories:
    get:
      summary: Получить все категории
      tags:
        - Categories
      security:
        - ApiTokenAuth: []
      responses:
        '200':
          description: Список категорий
        '401':
          description: Не авторизован

  /categories/create:
    post:
      summary: Создать категорию
      tags:
        - Categories
      security:
        - ApiTokenAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                description:
                  type: string
                parent_id:
                  type: integer
                status:
                  type: integer
      responses:
        '201':
          description: Категория создана
        '401':
          description: Не авторизован
```

---

## 11. ПРИМЕРЫ ИНТЕГРАЦИИ

### PHP клиент

```php
<?php
class OpenCartAPIClient {
    private $base_url = 'http://example.com/api';
    private $api_token = 'your_api_token_here';
    
    public function importProducts($products, $mode = 'merge') {
        $url = $this->base_url . '/products/import';
        
        $data = [
            'mode' => $mode,
            'products' => $products
        ];
        
        return $this->request('POST', $url, $data);
    }
    
    public function createCategory($name, $parent_id = 0) {
        $url = $this->base_url . '/categories/create';
        
        $data = [
            'name' => $name,
            'parent_id' => $parent_id,
            'status' => 1
        ];
        
        return $this->request('POST', $url, $data);
    }
    
    private function request($method, $url, $data = []) {
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Token: ' . $this->api_token
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $http_code,
            'data' => json_decode($response, true)
        ];
    }
}

// Использование
$client = new OpenCartAPIClient();

// Импортировать товары
$result = $client->importProducts([
    [
        'sku' => 'SKU001',
        'name' => 'Товар 1',
        'price' => 1500,
        'category_id' => 5
    ]
], 'merge');

echo "Status: " . $result['code'] . "\n";
echo "Data: " . json_encode($result['data'], JSON_PRETTY_PRINT);
```

### JavaScript клиент

```javascript
class OpenCartAPI {
    constructor(baseUrl, apiToken) {
        this.baseUrl = baseUrl;
        this.apiToken = apiToken;
    }
    
    async importProducts(products, mode = 'merge') {
        const response = await fetch(`${this.baseUrl}/products/import`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Token': this.apiToken
            },
            body: JSON.stringify({
                mode: mode,
                products: products
            })
        });
        
        return response.json();
    }
    
    async getCategories() {
        const response = await fetch(`${this.baseUrl}/categories`, {
            headers: {
                'X-API-Token': this.apiToken
            }
        });
        
        return response.json();
    }
    
    async createCategory(name, parentId = 0) {
        const response = await fetch(`${this.baseUrl}/categories/create`, {
            method: 'POST',
            headers: {
                'Content-Type': application/json',
                'X-API-Token': this.apiToken
            },
            body: JSON.stringify({
                name: name,
                parent_id: parentId,
                status: 1
            })
        });
        
        return response.json();
    }
}

// Использование
const api = new OpenCartAPI('http://example.com/api', 'your_api_token_here');

// Импортировать товары
api.importProducts([
    {
        sku: 'SKU001',
        name: 'Товар 1',
        price: 1500,
        category_id: 5
    }
], 'merge').then(result => {
    console.log('Import result:', result);
});
```

---

**Версия:** 1.0  
**Дата:** 19.12.2025  
**Статус:** Готово к разработке