<?php
// Скрипт удаления расширения Product Importer

class ControllerExtensionModuleProductImporterUninstall extends Controller {
    public function index() {
        $this->load->language('extension/module/product_importer');
        
        // Проверить права доступа
        if (!$this->user->hasPermission('modify', 'extension/module/product_importer')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token']));
        }
        
        // Удалить таблицы
        $this->load->model('extension/module/product_importer');
        $this->model_extension_module_product_importer->uninstall();
        
        // Удалить модуль
        $this->load->model('setting/module');
        $this->model_setting_module->deleteModuleByCode('product_importer');
        
        // Удалить расширение
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('module', 'product_importer');
        
        // Удалить события
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('product_importer');
        
        // Удалить настройки
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('product_importer');
        
        $this->session->data['success'] = $this->language->get('text_success_uninstall');
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'));
    }
}
