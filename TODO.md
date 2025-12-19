# TODO: OpenCart REST API - Image Upload Support

## Текущий статус
Расширение OpenCart REST API работает для создания/обновления/удаления товаров и категорий, но требует доработки для поддержки загрузки изображений через API.

## Проблема
При попытке загрузить изображение через endpoint `tool/product_importer|uploadImage` возникает ошибка:
```
"Type, ID and image data are required"
```

Это происходит потому, что существующий метод `uploadImage()` в контроллере `/catalog/controller/tool/product_importer.php` ожидает параметры:
- `type` (product/category)
- `id` (ID товара/категории)
- `image` (данные изображения)

Но скрипт синхронизации отправляет:
- `filename` (имя файла)
- `image_data` (base64-encoded изображение)

## Задачи для выполнения

### 1. Модификация контроллера product_importer.php
**Файл:** `/catalog/controller/tool/product_importer.php` на сервере OpenCart

**Что нужно сделать:**
Добавить поддержку нового формата загрузки изображений в метод `uploadImage()`. Метод должен поддерживать оба формата:

#### Формат 1 (существующий):
```json
{
  "type": "product",
  "id": 123,
  "image": "base64_data"
}
```

#### Формат 2 (новый, для скрипта синхронизации):
```json
{
  "filename": "DbImage12345.jpg",
  "image_data": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

**Код для добавления:**
Вставить следующий код в метод `uploadImage()` ПЕРЕД проверкой `if (!isset($json['type']) || !isset($json['id']) || !isset($json['image']))`:

```php
// Support both formats: old (type, id, image) and new (filename, image_data)
if (isset($json['filename']) && isset($json['image_data'])) {
    // New format: simple file upload for sync script
    $filename = basename($json['filename']);
    $imageData = $json['image_data'];
    
    // Validate filename
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        $this->response->setOutput(json_encode([
            'success' => false,
            'error' => 'Invalid filename',
            'code' => 400
        ]));
        return;
    }
    
    // Decode base64 (remove data URL prefix if present)
    $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
    $imageData = base64_decode($imageData);
    
    if ($imageData === false) {
        $this->response->setOutput(json_encode([
            'success' => false,
            'error' => 'Invalid base64 image data',
            'code' => 400
        ]));
        return;
    }
    
    // Validate image data
    $tempFile = tempnam(sys_get_temp_dir(), 'img_upload_');
    file_put_contents($tempFile, $imageData);
    
    $imageInfo = @getimagesize($tempFile);
    if ($imageInfo === false) {
        unlink($tempFile);
        $this->response->setOutput(json_encode([
            'success' => false,
            'error' => 'Invalid image file',
            'code' => 400
        ]));
        return;
    }
    
    // Ensure catalog directory exists
    $catalogDir = DIR_IMAGE . 'catalog';
    if (!is_dir($catalogDir)) {
        mkdir($catalogDir, 0755, true);
    }
    
    // Save the image
    $targetPath = $catalogDir . '/' . $filename;
    
    // Check if file already exists - reuse existing file
    if (file_exists($targetPath)) {
        unlink($tempFile);
        $this->response->setOutput(json_encode([
            'success' => true,
            'data' => [
                'image_path' => 'catalog/' . $filename,
                'filename' => $filename,
                'existed' => true
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return;
    }
    
    // Move temp file to target location
    if (rename($tempFile, $targetPath)) {
        chmod($targetPath, 0644);
        $this->response->setOutput(json_encode([
            'success' => true,
            'data' => [
                'image_path' => 'catalog/' . $filename,
                'filename' => $filename
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return;
    } else {
        @unlink($tempFile);
        $this->response->setOutput(json_encode([
            'success' => false,
            'error' => 'Failed to save image',
            'code' => 500
        ]));
        return;
    }
}

// Continue with original format check...
```

### 2. Инструкция по применению изменений

#### Вариант А: Ручное редактирование через SSH
```bash
# Подключиться к серверу
ssh mybot@192.168.1.130

# Войти в контейнер
docker exec -it opencart4_130 bash

# Создать резервную копию
cp /var/www/html/catalog/controller/tool/product_importer.php \
   /var/www/html/catalog/controller/tool/product_importer.php.backup

# Отредактировать файл
nano /var/www/html/catalog/controller/tool/product_importer.php

# Найти строку:
# if (!isset($json['type']) || !isset($json['id']) || !isset($json['image']))

# Вставить код выше ПЕРЕД этой строкой
```

#### Вариант Б: Автоматическое применение через скрипт
Создать файл `patch_controller.sh`:

```bash
#!/bin/bash
ssh mybot@192.168.1.130 << 'ENDSSH'
docker exec opencart4_130 bash << 'ENDDOCKER'
# Backup
cp /var/www/html/catalog/controller/tool/product_importer.php \
   /var/www/html/catalog/controller/tool/product_importer.php.backup

# Apply patch
# (код патча здесь)
ENDDOCKER
ENDSSH
```

### 3. Тестирование

#### Тест 1: Проверка endpoint через curl
```bash
curl -X POST "http://192.168.1.130:8080/index.php?route=tool/product_importer|uploadImage" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "filename": "test.jpg",
    "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD..."
  }'
```

Ожидаемый результат:
```json
{
  "success": true,
  "data": {
    "image_path": "catalog/test.jpg",
    "filename": "test.jpg"
  },
  "timestamp": "2025-12-19 23:50:00"
}
```

#### Тест 2: Запуск скрипта синхронизации
```bash
cd d:\Git\opencart
python aw2000\script\sync_to_opencart_windows.py
```

Проверить логи:
```
D:\aw2000\database\sync_to_opencart.log
```

Должны появиться сообщения:
```
INFO - Uploading image: DbImage12345.jpg (size: 45678 bytes)
INFO - Successfully uploaded image: DbImage12345.jpg -> catalog/DbImage12345.jpg
```

#### Тест 3: Проверка в OpenCart Admin
1. Открыть http://192.168.1.130:8080/admin
2. Перейти в Catalog → Products
3. Открыть любой товар с ItemNumber, для которого есть изображение
4. Проверить, что изображение отображается

### 4. Обновление документации

После успешного применения патча обновить `API_DOCUMENTATION.md`:

Добавить секцию:

```markdown
#### Upload Image
Upload a product image via base64 encoding.

**Endpoint:** `POST /index.php?route=tool/product_importer|uploadImage`

**Request:**
```json
{
  "filename": "product_image.jpg",
  "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
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
```

## Структура проекта AW2000 Sync

### Скрипты синхронизации
- `aw2000/script/sync_to_opencart_windows.py` - основной скрипт синхронизации (Windows)
- `aw2000/script/sync_to_opencart.py` - старая версия (Linux, deprecated)
- `aw2000/script/windows_demo_import.py` - демо-импорт для тестирования

### Конфигурация
В начале файла `sync_to_opencart_windows.py`:
```python
DB_DIR = r"D:\aw2000\database"
IMAGES_DIR = r"D:\aw2000\database\Imagedata_compact"
OPENCART_API_URL = "http://192.168.1.130:8080/index.php"
API_TOKEN = "test_api_token_12345"
MAX_PRODUCTS = 50  # Для тестирования
DRY_RUN = False
COPY_IMAGES = True
```

### Логика работы с изображениями
1. Чтение `InvImages.DB` для получения маппинга `ItemNumber` → `InvImageNumber`
2. Формирование имени файла: `DbImage{InvImageNumber}.jpg`
3. Проверка существования файла в `IMAGES_DIR`
4. Загрузка изображения через API в base64
5. Кэширование загруженных изображений для избежания повторной загрузки

## Известные проблемы

### Проблема 1: Размер изображений
Некоторые изображения могут быть слишком большими для передачи через JSON.

**Решение:** Добавить проверку размера и сжатие:
```python
from PIL import Image
import io

def compress_image(image_path, max_size_kb=500):
    img = Image.open(image_path)
    # Resize if too large
    if img.width > 1200 or img.height > 1200:
        img.thumbnail((1200, 1200), Image.Resampling.LANCZOS)
    # Save with compression
    buffer = io.BytesIO()
    img.save(buffer, format='JPEG', quality=85, optimize=True)
    return buffer.getvalue()
```

### Проблема 2: Timeout при загрузке множества изображений
**Решение:** Добавить батчевую загрузку с паузами:
```python
import time

for i, (sku, desired) in enumerate(desired_by_sku.items()):
    # Upload image
    if i > 0 and i % 10 == 0:
        time.sleep(1)  # Pause every 10 images
```

## Следующие шаги

1. ✅ Исправлен маппинг изображений через InvImages.DB
2. ✅ Создан Windows-совместимый скрипт синхронизации
3. ⏳ **Применить патч к контроллеру OpenCart** (текущая задача)
4. ⏳ Протестировать загрузку изображений
5. ⏳ Запустить полную синхронизацию
6. ⏳ Проверить результаты в OpenCart Admin
7. ⏳ Обновить документацию API

## Контакты и ссылки

- GitHub: https://github.com/Mk0IV/opencart_rest_api
- OpenCart Server: http://192.168.1.130:8080
- SSH: mybot@192.168.1.130
- Docker Container: opencart4_130

## Дата создания
2025-12-19
