-- Таблица для отслеживания партий импорта
CREATE TABLE IF NOT EXISTS `os4_import_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `total_records` int(11) NOT NULL DEFAULT 0,
  `processed_records` int(11) NOT NULL DEFAULT 0,
  `success_records` int(11) NOT NULL DEFAULT 0,
  `failed_records` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `mode` enum('add','update','merge') NOT NULL,
  `admin_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для логирования операций импорта товаров
CREATE TABLE IF NOT EXISTS `os4_product_import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_batch_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `action` enum('insert','update','skip','error') NOT NULL,
  `status` enum('success','error') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_id` (`import_batch_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`import_batch_id`) REFERENCES `os4_import_batch` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для API токенов
CREATE TABLE IF NOT EXISTS `oc_api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL UNIQUE,
  `name` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица для логирования ошибок импорта
CREATE TABLE IF NOT EXISTS `oc_import_error_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставить тестовый API токен (для разработки)
INSERT INTO `oc_api_tokens` (`user_id`, `token`, `name`, `status`) VALUES 
(1, 'test_api_token_12345', 'Development Token', 1);

-- Создать индексы для оптимизации запросов
CREATE INDEX IF NOT EXISTS `idx_product_sku` ON `oc_product` (`sku`);
CREATE INDEX IF NOT EXISTS `idx_product_model` ON `oc_product` (`model`);
CREATE INDEX IF NOT EXISTS `idx_category_parent_id` ON `oc_category` (`parent_id`);
CREATE INDEX IF NOT EXISTS `idx_category_status` ON `oc_category` (`status`);
