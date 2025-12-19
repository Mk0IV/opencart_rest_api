# Product Importer REST API Documentation

## Overview
REST API extension for OpenCart 4.x that allows importing products and managing categories via HTTP requests.

## Installation
1. Upload `product_importer.tar.gz` via OpenCart Admin Panel → Extensions → Installer
2. Go to Extensions → Modules → Product Importer → Install
3. Enable the module

## Authentication
All API requests require an API token sent in the `X-API-Token` header.

**Default Development Token:** `test_api_token_12345`

## API Endpoints

### Categories

#### Get All Categories
```bash
curl -X GET "http://your-store.com/index.php?route=tool/product_importer|categories" \
  -H "X-API-Token: test_api_token_12345"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "category_id": "1",
        "name": "Desktops",
        "parent_id": "0",
        "status": "1",
        "product_count": "3"
      }
    ],
    "total": 1
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Create Category
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|createCategory" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "name": "New Category",
    "description": "Category description",
    "parent_id": 0,
    "status": 1,
    "meta_title": "Category Meta Title",
    "meta_description": "Category Meta Description",
    "keyword": "category-seo-keyword"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "category_id": 25,
    "message": "Category created successfully"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Update Category
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|updateCategory" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "category_id": 25,
    "name": "Updated Category Name",
    "description": "Updated description",
    "parent_id": 0,
    "status": 1,
    "meta_title": "Updated Meta Title",
    "meta_description": "Updated meta description",
    "keyword": "updated-seo-keyword"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "category_id": 25,
    "message": "Category updated successfully"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Delete Category
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|deleteCategory" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "category_id": 25
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "category_id": 25,
    "message": "Category deleted successfully"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

### Products

#### Get All Products
```bash
curl -X GET "http://your-store.com/index.php?route=tool/product_importer|products" \
  -H "X-API-Token: test_api_token_12345"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "product_id": "1",
        "name": "Test Product",
        "model": "TP001",
        "sku": "TEST-001",
        "price": "99.9900",
        "quantity": "100",
        "status": "1",
        "date_added": "2025-12-19 17:53:00",
        "date_modified": "2025-12-19 17:53:00",
        "category_name": "Test Category"
      }
    ],
    "total": 1
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Import Products
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|importProducts" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "mode": "merge",
    "products": [
      {
        "name": "Test Product",
        "model": "TP001",
        "sku": "TEST-001",
        "price": 99.99,
        "quantity": 100,
        "description": "Product description",
        "category_id": 1,
        "status": 1
      }
    ]
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "batch_id": 1,
    "total": 1,
    "success": 1,
    "failed": 0,
    "message": "Import completed. Added: 1, Updated: 1, Failed: 0"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Update Product
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|updateProduct" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "product_id": 1,
    "name": "Updated Product Name",
    "model": "UPD-001",
    "sku": "UPDATED-001",
    "price": 149.99,
    "quantity": 50,
    "description": "<p>Updated product description</p>",
    "category_id": 2,
    "status": 1,
    "meta_title": "Updated Meta Title",
    "meta_description": "Updated meta description",
    "weight": 1.5,
    "length": 10,
    "width": 5,
    "height": 3
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "product_id": 1,
    "message": "Product updated successfully"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Delete Product
```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|deleteProduct" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "product_id": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "product_id": 1,
    "message": "Product deleted successfully"
  },
  "timestamp": "2025-12-19 17:53:00"
}
```

#### Upload Image
Upload a product image via base64 encoding.

```bash
curl -X POST "http://your-store.com/index.php?route=tool/product_importer|uploadImage" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "filename": "product_image.jpg",
    "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "image_path": "catalog/product_image.jpg",
    "filename": "product_image.jpg"
  },
  "timestamp": "2025-12-19 23:50:00"
}
```

**Notes:**
- `image_data` should be a base64-encoded image with optional data URL prefix
- Supported formats: JPEG, PNG, GIF, BMP
- If file already exists, it will be reused (no overwrite)
- Maximum file size is limited by PHP settings (upload_max_filesize, post_max_size)
- The returned `image_path` can be used in product `image` field

## Error Responses

All errors return JSON with error details:
```json
{
  "success": false,
  "error": "Error message",
  "code": 400,
  "timestamp": "2025-12-19 17:53:00"
}
```

## Common Error Codes
- `401` - Unauthorized (missing or invalid API token)
- `400` - Bad Request (invalid JSON or missing required fields)
- `404` - Not Found (resource doesn't exist)
- `405` - Method Not Allowed (incorrect HTTP method)
- `500` - Internal Server Error

## Database Tables
The extension creates the following tables:
- `os4_import_batch` - Tracks import batches
- `os4_product_import_log` - Logs import operations
- `os4_api_tokens` - Stores API authentication tokens
- `os4_import_error_log` - Error logging

## File Structure
```
extension/product_importer/
├── install.json
├── catalog/
│   ├── controller/api/
│   │   ├── categories.php
│   │   └── products.php
│   ├── model/extension/module/product_importer/
│   └── library/product_importer/
└── admin/
    ├── controller/extension/module/product_importer.php
    ├── view/template/extension/module/product_importer.twig
    └── language/en-gb/extension/module/product_importer.php
```

## Import Modes
- `add` - Only create new products (fails if SKU exists)
- `update` - Only update existing products (fails if SKU doesn't exist)
- `merge` - Create new products or update existing ones (default)

## Rate Limiting
No rate limiting is implemented. Consider implementing based on your requirements.
