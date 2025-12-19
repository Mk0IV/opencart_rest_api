<?php
// Скрипт установки расширения Product Importer

class ControllerExtensionModuleProductImporterInstall extends Controller {
    public function index() {
        $this->load->language('extension/module/product_importer');
        
        // Проверить права доступа
        if (!$this->user->hasPermission('modify', 'extension/module/product_importer')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token']));
        }
        
        // Выполнить SQL для создания таблиц
        $this->load->model('extension/module/product_importer');
        $this->model_extension_module_product_importer->install();
        
        // Добавить модуль в систему
        $this->load->model('setting/extension');
        $this->load->model('setting/module');
        
        // Установить расширение
        $this->model_setting_extension->install('module', 'product_importer');
        
        // Добавить настройки по умолчанию
        $module_data = [
            'name' => $this->language->get('heading_title'),
            'code' => 'product_importer',
            'setting' => [
                'product_importer_status' => 1,
                'product_importer_chunk_size' => 100,
                'product_importer_max_file_size' => 100,
                'product_importer_api_enabled' => 1,
                'product_importer_log_retention_days' => 30
            ]
        ];
        
        $this->model_setting_module->addModule('product_importer', $module_data);
        
        // Установить события
        $this->load->model('setting/event');
        
        $events = [
            'admin/model/catalog/product/addProduct/after' => 'extension/module/product_importer/event.productImport',
            'admin/model/catalog/product/editProduct/after' => 'extension/module/product_importer/event.productImport',
        ];
        
        foreach ($events as $trigger => $action) {
            $this->model_setting_event->addEvent([
                'code' => 'product_importer',
                'trigger' => $trigger,
                'action' => $action,
                'status' => 1,
                'sort_order' => 0
            ]);
        }
        
        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'));
    }
}
