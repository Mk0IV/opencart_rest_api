<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ImportLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Создать новую запись batch для импорта
     * 
     * @param string $filename Имя файла
     * @param string $file_type Тип файла (csv, xlsx, json)
     * @param int $total_records Всего записей
     * @param string $mode Режим импорта (add, update, merge)
     * @param int $admin_id ID администратора
     * @return int ID созданного batch
     */
    public function createBatch($filename, $file_type, $total_records, $mode, $admin_id = 0) {
        $this->db->query(
            "INSERT INTO `oc_import_batch` (filename, file_type, total_records, mode, admin_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            [$filename, $file_type, $total_records, $mode, $admin_id]
        );
        
        return $this->db->getLastId();
    }
    
    /**
     * Обновить статус batch
     * 
     * @param int $batch_id ID batch
     * @param string $status Статус (pending, processing, completed, failed)
     * @param int $processed Обработано записей
     * @param int $success Успешно
     * @param int $failed С ошибками
     */
    public function updateBatch($batch_id, $status, $processed = 0, $success = 0, $failed = 0) {
        $this->db->query(
            "UPDATE `oc_import_batch` SET status = ?, processed_records = ?, success_records = ?, failed_records = ?, updated_at = NOW() WHERE id = ?",
            [$status, $processed, $success, $failed, $batch_id]
        );
    }
    
    /**
     * Логировать операцию с товаром
     * 
     * @param int $batch_id ID batch
     * @param int $product_id ID товара
     * @param string $sku SKU товара
     * @param string $name Название товара
     * @param string $action Действие (insert, update, skip)
     * @param string $status Статус (success, error)
     * @param string $error_message Сообщение об ошибке
     */
    public function logProduct($batch_id, $product_id, $sku, $name, $action, $status, $error_message = null) {
        $this->db->query(
            "INSERT INTO `oc_product_import_log` (import_batch_id, product_id, sku, name, action, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$batch_id, $product_id, $sku, $name, $action, $status, $error_message]
        );
    }
    
    /**
     * Получить информацию о batch
     * 
     * @param int $batch_id ID batch
     * @return array|null Данные batch
     */
    public function getBatch($batch_id) {
        $query = $this->db->query(
            "SELECT * FROM `oc_import_batch` WHERE id = ?",
            [$batch_id]
        );
        
        return $query->row ?: null;
    }
    
    /**
     * Получить все batch
     * 
     * @param int $limit Лимит записей
     * @param int $offset Смещение
     * @return array Список batch
     */
    public function getBatches($limit = 20, $offset = 0) {
        $query = $this->db->query(
            "SELECT b.*, a.username as admin_name FROM `oc_import_batch` b 
             LEFT JOIN `oc_user` a ON b.admin_id = a.user_id 
             ORDER BY b.created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        
        return $query->rows;
    }
    
    /**
     * Получить логи для batch
     * 
     * @param int $batch_id ID batch
     * @param int $limit Лимит записей
     * @param string $status Фильтр по статусу
     * @return array Список логов
     */
    public function getBatchLogs($batch_id, $limit = 100, $status = null) {
        $sql = "SELECT * FROM `oc_product_import_log` WHERE import_batch_id = ?";
        $params = [$batch_id];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $query = $this->db->query($sql, $params);
        
        return $query->rows;
    }
    
    /**
     * Получить статистику по batch
     * 
     * @param int $batch_id ID batch
     * @return array Статистика
     */
    public function getBatchStats($batch_id) {
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
    
    /**
     * Очистить старые логи
     * 
     * @param int $days Количество дней для хранения
     * @return int Количество удаленных записей
     */
    public function cleanOldLogs($days = 30) {
        // Удалить старые batch
        $this->db->query(
            "DELETE FROM `oc_import_batch` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        
        // Удалить старые логи
        $query = $this->db->query(
            "DELETE FROM `oc_product_import_log` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        
        return $this->db->countAffected();
    }
    
    /**
     * Записать ошибку в лог
     * 
     * @param string $message Сообщение об ошибке
     * @param array $context Контекст
     */
    public function error($message, $context = []) {
        $context_str = !empty($context) ? json_encode($context) : '';
        
        $this->db->query(
            "INSERT INTO `oc_import_error_log` (message, context, created_at) VALUES (?, ?, NOW())",
            [$message, $context_str]
        );
    }
    
    /**
     * Получить ошибки импорта
     * 
     * @param int $batch_id ID batch
     * @return array Список ошибок
     */
    public function getImportErrors($batch_id) {
        $query = $this->db->query(
            "SELECT * FROM `oc_product_import_log` 
             WHERE import_batch_id = ? AND status = 'error' 
             ORDER BY created_at DESC",
            [$batch_id]
        );
        
        return $query->rows;
    }
}
