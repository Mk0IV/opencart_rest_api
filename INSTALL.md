# Установка обновленного контроллера с поддержкой загрузки изображений

## Быстрая установка

### Шаг 1: Подключение к серверу
```bash
ssh mybot@192.168.1.130
```

### Шаг 2: Резервное копирование
```bash
docker exec opencart4_130 cp /var/www/html/catalog/controller/tool/product_importer.php \
   /var/www/html/catalog/controller/tool/product_importer.php.backup.$(date +%Y%m%d_%H%M%S)
```

### Шаг 3: Загрузка нового контроллера
```bash
# На локальной машине (Windows)
scp d:\Git\opencart\opencart_rest_api\catalog_controller_tool_product_importer.php \
    mybot@192.168.1.130:/tmp/product_importer.php

# На сервере
ssh mybot@192.168.1.130
docker cp /tmp/product_importer.php opencart4_130:/var/www/html/catalog/controller/tool/product_importer.php
```

### Шаг 4: Проверка установки
```bash
# Тест через curl
curl -X POST "http://192.168.1.130:8080/index.php?route=tool/product_importer|uploadImage" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: test_api_token_12345" \
  -d '{
    "filename": "test.jpg",
    "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A8A"
  }'
```

Ожидаемый результат:
```json
{
  "success": true,
  "data": {
    "image_path": "catalog/test.jpg",
    "filename": "test.jpg"
  }
}
```

## Детальная инструкция

### Что изменилось

В контроллер `catalog/controller/tool/product_importer.php` добавлен новый метод `uploadImage()` который:

1. **Принимает два формата данных:**
   - Новый формат: `filename` + `image_data` (для скрипта синхронизации)
   - Старый формат: `type` + `id` + `image` (для обратной совместимости)

2. **Обрабатывает base64-encoded изображения:**
   - Автоматически удаляет data URL prefix (`data:image/jpeg;base64,`)
   - Валидирует изображение через `getimagesize()`
   - Сохраняет в директорию `image/catalog/`

3. **Предотвращает дублирование:**
   - Если файл уже существует, возвращает существующий путь
   - Не перезаписывает существующие файлы

### Структура файлов

После установки структура будет:
```
/var/www/html/
├── catalog/
│   └── controller/
│       └── tool/
│           ├── product_importer.php          # Обновленный файл
│           └── product_importer.php.backup   # Резервная копия
└── image/
    └── catalog/                              # Директория для загруженных изображений
        ├── DbImage12345.jpg
        ├── DbImage12346.jpg
        └── ...
```

### Использование с Python скриптом

После установки контроллера, запустите скрипт синхронизации:

```bash
cd d:\Git\opencart
python aw2000\script\sync_to_opencart_windows.py
```

Скрипт автоматически:
1. Прочитает маппинг изображений из `InvImages.DB`
2. Загрузит изображения через API
3. Обновит товары с путями к изображениям

### Проверка логов

```bash
# На Windows
type D:\aw2000\database\sync_to_opencart.log

# Должны быть сообщения:
# INFO - Uploading image: DbImage12345.jpg (size: 45678 bytes)
# INFO - Successfully uploaded image: DbImage12345.jpg -> catalog/DbImage12345.jpg
```

### Проверка в OpenCart Admin

1. Откройте http://192.168.1.130:8080/admin
2. Перейдите в Catalog → Products
3. Откройте любой товар
4. Проверьте наличие изображения на вкладке Image

### Откат изменений

Если что-то пошло не так:

```bash
ssh mybot@192.168.1.130
docker exec opencart4_130 bash -c "
  cp /var/www/html/catalog/controller/tool/product_importer.php.backup \
     /var/www/html/catalog/controller/tool/product_importer.php
"
```

## Требования

- OpenCart 4.x
- PHP 7.4+
- Расширение GD или Imagick для валидации изображений
- Достаточно места в директории `image/catalog/`
- Настроенные PHP параметры:
  - `upload_max_filesize` >= 10M
  - `post_max_size` >= 10M
  - `memory_limit` >= 128M

## Troubleshooting

### Ошибка: "Invalid API token"
Проверьте токен в базе данных:
```sql
SELECT * FROM os4_api_tokens WHERE token = 'test_api_token_12345';
```

### Ошибка: "Failed to save image"
Проверьте права доступа:
```bash
docker exec opencart4_130 chmod 755 /var/www/html/image/catalog
docker exec opencart4_130 chown www-data:www-data /var/www/html/image/catalog
```

### Ошибка: "Invalid base64 image data"
Проверьте формат данных:
- Должен быть корректный base64
- Может содержать data URL prefix (будет удален автоматически)

### Изображения не отображаются в OpenCart
Проверьте:
1. Файлы существуют: `docker exec opencart4_130 ls -la /var/www/html/image/catalog/`
2. Права доступа: файлы должны быть читаемыми для www-data
3. Путь в БД: `SELECT image FROM os4_product WHERE product_id = X;`

## Поддержка

- GitHub: https://github.com/Mk0IV/opencart_rest_api
- Issues: https://github.com/Mk0IV/opencart_rest_api/issues

## История изменений

### 2025-12-19
- Добавлен метод `uploadImage()` с поддержкой base64
- Добавлена валидация изображений
- Добавлена защита от перезаписи существующих файлов
