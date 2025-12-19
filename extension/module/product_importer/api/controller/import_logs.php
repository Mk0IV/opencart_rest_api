<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Api\Controller;

class ImportLogsController {
    private $db;
    private $request;
    private $response;
    
    public function __construct($db, $request, $response) {
        $this->db = $db;
        $this->request = $request;
        $this->response = $response;
    }
    
    /**
     * GET /api/import/logs
     * Получить список всех импортов
     */
    public function getLogs() {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $limit = (int)($this->request->get['limit'] ?? 20);
            $offset = (int)($this->request->get['offset'] ?? 0);
            $status = $this->request->get['status'] ?? null;
            
            $sql = "SELECT b.*, a.username as admin_name FROM `oc_import_batch` b 
                    LEFT JOIN `oc_user` a ON b.admin_id = a.user_id";
            $params = [];
            
            if ($status) {
                $sql .= " WHERE b.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $query = $this->db->query($sql, $params);
            
            // Получить статистику для каждого batch
            foreach ($query->rows as &$batch) {
                $stats = $this->getBatchStats($batch['id']);
                $batch['stats'] = $stats;
            }
            
            return $this->success([
                'logs' => $query->rows,
                'total' => count($query->rows)
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/import/logs/{id}
     * Получить детальные логи для конкретного импорта
     */
    public function getBatchLogs($batch_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Проверить существует ли batch
            $batch_query = $this->db->query(
                "SELECT * FROM `oc_import_batch` WHERE id = ?",
                [$batch_id]
            );
            
            if (!$batch_query->row) {
                return $this->error('Import batch not found', 404);
            }
            
            $limit = (int)($this->request->get['limit'] ?? 100);
            $status = $this->request->get['status'] ?? null;
            
            // Получить логи
            $sql = "SELECT * FROM `oc_product_import_log` WHERE import_batch_id = ?";
            $params = [$batch_id];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $logs_query = $this->db->query($sql, $params);
            
            // Получить статистику
            $stats = $this->getBatchStats($batch_id);
            
            return $this->success([
                'batch' => $batch_query->row,
                'stats' => $stats,
                'logs' => $logs_query->rows
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/import/logs/{id}/errors
     * Получить только ошибки импорта
     */
    public function getBatchErrors($batch_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $query = $this->db->query(
                "SELECT * FROM `oc_product_import_log` 
                 WHERE import_batch_id = ? AND status = 'error' 
                 ORDER BY created_at DESC",
                [$batch_id]
            );
            
            return $this->success([
                'errors' => $query->rows,
                'total' => count($query->rows)
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /api/import/logs/{id}
     * Удалить логи импорта
     */
    public function deleteBatch($batch_id) {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Проверить права (только admin или создатель)
            $batch_query = $this->db->query(
                "SELECT * FROM `oc_import_batch` WHERE id = ?",
                [$batch_id]
            );
            
            if (!$batch_query->row) {
                return $this->error('Import batch not found', 404);
            }
            
            // Удалить логи
            $this->db->query(
                "DELETE FROM `oc_product_import_log` WHERE import_batch_id = ?",
                [$batch_id]
            );
            
            // Удалить batch
            $this->db->query(
                "DELETE FROM `oc_import_batch` WHERE id = ?",
                [$batch_id]
            );
            
            return $this->success([
                'message' => 'Import logs deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/import/logs/stats
     * Получить общую статистику импортов
     */
    public function getStats() {
        try {
            if (!$this->authenticate()) {
                return $this->error('Unauthorized', 401);
            }
            
            $query = $this->db->query(
                "SELECT 
                    COUNT(*) as total_imports,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(total_records) as total_records,
                    SUM(success_records) as total_success,
                    SUM(failed_records) as total_failed,
                    DATE(created_at) as import_date
                 FROM `oc_import_batch` 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY import_date DESC"
            );
            
            return $this->success([
                'stats' => $query->rows
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Получить статистику для batch
     */
    private function getBatchStats($batch_id) {
        $query = $this->db->query(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                SUM(CASE WHEN action = 'insert' THEN 1 ELSE 0 END) as inserted,
                SUM(CASE WHEN action = 'update' THEN 1 ELSE 0 END) as updated,
                SUM(CASE WHEN action = 'skip' THEN 1 ELSE 0 END) as skipped
             FROM `oc_product_import_log` 
             WHERE import_batch_id = ?",
            [$batch_id]
        );
        
        return $query->row;
    }
    
    private function authenticate() {
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        if (empty($token)) {
            return false;
        }
        
        $query = $this->db->query(
            "SELECT * FROM `oc_api_tokens` WHERE token = ? AND status = 1",
            [$token]
        );
        
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
