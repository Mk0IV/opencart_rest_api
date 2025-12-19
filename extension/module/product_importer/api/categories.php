<?php
// API контроллер для категорий в правильной структуре OpenCart 4.x
class ControllerApiCategories extends Controller {
    private $error = array();
    
    public function index() {
        $this->load->language('api/categories');
        $json = array();
        
        // Проверка API ключа
        if (!$this->user->hasPermission('modify', 'api/categories')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('extension/module/product_importer/api_category');
            
            $categories = $this->model_extension_module_product_importer_api_category->getCategories();
            
            $json['categories'] = $categories;
            $json['success'] = true;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function create() {
        $this->load->language('api/categories');
        $json = array();
        
        if (!$this->user->hasPermission('modify', 'api/categories')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
                $this->load->model('extension/module/product_importer/api_category');
                
                $category_data = array(
                    'name' => $this->request->post['name'] ?? '',
                    'description' => $this->request->post['description'] ?? '',
                    'parent_id' => $this->request->post['parent_id'] ?? 0,
                    'status' => $this->request->post['status'] ?? 1
                );
                
                $category_id = $this->model_extension_module_product_importer_api_category->addCategory($category_data);
                
                $json['category_id'] = $category_id;
                $json['success'] = $this->language->get('text_success');
            } else {
                $json['error'] = $this->language->get('error_method');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
