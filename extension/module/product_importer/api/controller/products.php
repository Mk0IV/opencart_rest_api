<?php
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
            
            $query = $this->db->query("SELECT * FROM `oc_import_batch` WHERE id = '" . (int)$batch_id . "'");
            
            if (!$query->row) {
                return $this->error('Import batch not found', 404);
            }
            
            $batch = $query->row;
            
            // Получить логи для этого batch
            $log_query = $this->db->query("SELECT * FROM `oc_product_import_log` WHERE import_batch_id = '" . (int)$batch_id . "' LIMIT 100");
            
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
        $query = $this->db->query("SELECT * FROM `oc_api_tokens` WHERE token = '" . $this->db->escape($token) . "' AND status = 1");
        
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
