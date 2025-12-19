<?php
// Маршруты для REST API Product Importer

// API маршруты для импорта товаров
$_['route/api/products/import'] = 'extension/module/product_importer/api/products.import';
$_['route/api/products/import/status'] = 'extension/module/product_importer/api/products.getImportStatus';

// API маршруты для управления категориями
$_['route/api/categories'] = 'extension/module/product_importer/api/categories.getAll';
$_['route/api/categories/create'] = 'extension/module/product_importer/api/categories.create';
$_['route/api/categories/{id}'] = 'extension/module/product_importer/api/categories.getById';
$_['route/api/categories/{id}/update'] = 'extension/module/product_importer/api/categories.update';
$_['route/api/categories/{id}/delete'] = 'extension/module/product_importer/api/categories.delete';

// API маршруты для логов
$_['route/api/import/logs'] = 'extension/module/product_importer/api/import_logs.getLogs';
$_['route/api/import/logs/{id}'] = 'extension/module/product_importer/api/import_logs.getBatchLogs';

// Admin маршруты
$_['route/extension/module/product_importer'] = 'extension/module/product_importer';
$_['route/extension/module/product_importer/import'] = 'extension/module/product_importer/import';
$_['route/extension/module/product_importer/categories'] = 'extension/module/product_importer/categories';
$_['route/extension/module/product_importer/logs'] = 'extension/module/product_importer/logs';
