# AGENTS.md
## Инструкции для AI агентов разработки OpenCart Product Importer

Этот документ содержит техническую информацию, инструкции по сборке, тестированию и конвенции кода, которые помогут AI агентам эффективно работать с проектом.

---

## 📋 ОБЗОР ПРОЕКТА

**Название:** OpenCart Product Importer  
**Версия:** 1.0.0  
**Назначение:** Модуль для импорта товаров и управления категориями в OpenCart 3.x / 4.x  
**Тип:** OpenCart Extension (Admin Module + REST API)  
**Язык:** PHP 7.4+, JavaScript ES6+  
**БД:** MySQL/MariaDB  

**Основные компоненты:**
- Admin Panel (UI для импорта товаров)
- REST API (HTTP endpoints для интеграций)
- Парсеры файлов (CSV, XLSX, JSON)
- Система логирования (БД + файлы)
- Валидация данных (перед импортом)

---

## 🏗️ СТРУКТУРА ПРОЕКТА

```
catalog/extension/module/product_importer/
├── admin/
│   ├── controller/extension/module/product_importer.php
│   ├── language/ru-ru/extension/module/product_importer.php
│   └── view/template/extension/module/
│       ├── product_importer.twig
│       ├── import_form.twig
│       ├── category_manager.twig
│       └── import_log.twig
├── api/
│   ├── controller/
│   │   ├── products.php
│   │   ├── categories.php
│   │   └── import_logs.php
│   ├── model/
│   │   ├── api_product_import.php
│   │   ├── api_category_manager.php
│   │   └── api_logger.php
│   └── config/api.php
├── model/extension/module/
│   ├── product_importer.php
│   ├── product_import_handler.php
│   └── category_import_handler.php
├── library/
│   ├── ProductImporterCSVParser.php
│   ├── ProductImporterXLSXParser.php
│   ├── ProductImporterJSONParser.php
│   ├── ProductValidator.php
│   ├── CategoryValidator.php
│   └── ImportLogger.php
├── install.sql
├── install.php
├── uninstall.php
├── routes.php
├── config.php
└── README.md
```

---

## 🔧 ТРЕБОВАНИЯ К СРЕДЕ РАЗРАБОТКИ

### Минимальные требования
- **PHP:** 7.4 или выше
- **MySQL:** 5.7 или выше (рекомендуется 8.0+)
- **OpenCart:** 3.0, 3.1, 3.2, 4.0, 4.1
- **Память:** 256 MB минимум
- **Дисковое пространство:** 50 MB

### Дополнительные библиотеки
```php
// composer.json (если используется)
{
    "require": {
        "phpoffice/phpspreadsheet": "^1.20",
        "psr/http-message": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phpstan/phpstan": "^1.0"
    }
}
```

### Рекомендуемые инструменты для агентов
- Git для версионирования
- PHP Code Sniffer (PHPCS) для линтинга
- PHPUnit для тестирования
- PHPStan для статического анализа
- Xdebug для отладки

---

## 🚀 КОМАНДЫ СБОРКИ И УСТАНОВКИ

### Установка модуля в OpenCart

#### Шаг 1: Копирование файлов
```bash
# Скопировать все файлы в папку расширения
cp -r catalog/extension/module/product_importer /path/to/opencart/catalog/extension/module/

# Убедитесь в правах доступа
chmod -R 755 /path/to/opencart/catalog/extension/module/product_importer
```

#### Шаг 2: Создание таблиц БД
```bash
# Выполнить SQL из install.sql
mysql -u root -p opencart_db < install.sql

# Или через PHP интерфейс админ-панели
php install.php
```

#### Шаг 3: Активация модуля
```bash
# В админ-панели OpenCart:
# 1. Перейти: Extensions → Modules
# 2. Найти "Product Importer"
# 3. Нажать "Install"
```

### Установка зависимостей
```bash
# Если используется Composer
composer install --no-dev

# Для полной разработки
composer install
```

### Линтинг кода
```bash
# PHP CodeSniffer (PSR-12 стандарт)
./vendor/bin/phpcs --standard=PSR12 catalog/extension/module/product_importer/

# Исправить автоматически
./vendor/bin/phpcbf --standard=PSR12 catalog/extension/module/product_importer/

# PHPStan (статический анализ)
./vendor/bin/phpstan analyse catalog/extension/module/product_importer/
```

---

## 🧪 ТЕСТИРОВАНИЕ

### Структура тестов
```
tests/
├── unit/
│   ├── ProductValidatorTest.php
│   ├── ProductImporterCSVParserTest.php
│   ├── ProductImporterJSONParserTest.php
│   └── ImportLoggerTest.php
├── integration/
│   ├── ProductImportHandlerTest.php
│   ├── CategoryManagementTest.php
│   └── APIEndpointsTest.php
└── fixtures/
    ├── sample_products.csv
    ├── sample_products.json
    ├── sample_products.xlsx
    └── test_data.sql
```

### Запуск тестов

#### Unit тесты
```bash
# Запустить все unit тесты
./vendor/bin/phpunit tests/unit/

# Запустить конкретный тест
./vendor/bin/phpunit tests/unit/ProductValidatorTest.php

# С покрытием кода
./vendor/bin/phpunit --coverage-html coverage tests/unit/

# Конкретный метод теста
./vendor/bin/phpunit tests/unit/ProductValidatorTest.php --filter testValidation
```

#### Integration тесты
```bash
# Требуют подключения к БД
./vendor/bin/phpunit tests/integration/

# С логированием
./vendor/bin/phpunit --verbose tests/integration/
```

#### API тесты
```bash
# Тестирование REST API endpoints
./vendor/bin/phpunit tests/integration/APIEndpointsTest.php

# С отправкой реальных HTTP запросов
INTEGRATION_TEST=1 ./vendor/bin/phpunit tests/integration/
```

### Требования для тестирования
```php
<?php
// phpunit.xml
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
    colors="true"
    stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
    
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">catalog/extension/module/product_importer</directory>
        </include>
        <report>
            <html outputDirectory="coverage"/>
        </report>
    </coverage>
</phpunit>
```

### Проверки перед коммитом
```bash
#!/bin/bash
# pre-commit.sh

# 1. Линтинг
./vendor/bin/phpcs --standard=PSR12 catalog/extension/module/product_importer/
if [ $? -ne 0 ]; then
    echo "❌ Code style issues found"
    exit 1
fi

# 2. Статический анализ
./vendor/bin/phpstan analyse catalog/extension/module/product_importer/
if [ $? -ne 0 ]; then
    echo "❌ Static analysis failed"
    exit 1
fi

# 3. Unit тесты
./vendor/bin/phpunit tests/unit/
if [ $? -ne 0 ]; then
    echo "❌ Unit tests failed"
    exit 1
fi

echo "✅ All checks passed!"
```

---

## 📐 КОНВЕНЦИИ И СТАНДАРТЫ КОДА

### Стиль кода
- **Standard:** PSR-12 (PHP-FIG)
- **Naming:** camelCase для методов и свойств, PascalCase для классов
- **Indentation:** 4 пробела (не табуляции)
- **Line length:** 120 символов максимум

### Пример правильного кода
```php
<?php
namespace Opencart\Catalog\Extension\Module\ProductImporter\Library;

class ProductValidator {
    private $errors = [];
    
    /**
     * Валидировать товар перед импортом
     * 
     * @param array $product Данные товара
     * @return bool True если валидна, False если ошибки
     */
    public function validate(array $product): bool {
        $this->errors = [];
        
        // Проверка обязательных полей
        if (empty($product['name'])) {
            $this->errors[] = 'Field "name" is required';
            return false;
        }
        
        if (!is_numeric($product['price']) || $product['price'] < 0) {
            $this->errors[] = 'Field "price" must be a positive number';
            return false;
        }
        
        return true;
    }
    
    /**
     * Получить список ошибок валидации
     */
    public function getErrors(): array {
        return $this->errors;
    }
}
```

### Документирование кода
```php
<?php
/**
 * Импортировать товары в OpenCart
 * 
 * Метод выполняет валидацию и импорт товаров используя выбранный режим.
 * Поддерживаемые режимы: add (добавить), update (обновить), merge (оба).
 * 
 * @param array $products Массив товаров для импорта
 * @param string $mode Режим импорта (add|update|merge)
 * @param int $chunkSize Размер пакета для обработки (по умолчанию 100)
 * 
 * @return array {
 *     'total': int,    // Всего товаров
 *     'success': int,  // Успешно импортировано
 *     'failed': int    // Ошибок при импорте
 * }
 * 
 * @throws Exception Если режим импорта неверный
 */
public function import(array $products, string $mode = 'merge', int $chunkSize = 100): array {
    // Реализация
}
```

### Обработка ошибок
```php
<?php
try {
    $parser = new ProductImporterCSVParser($filepath);
    $data = $parser->parse();
    
    if (empty($data)) {
        throw new \Exception('No data found in file');
    }
    
} catch (\Exception $e) {
    // Логировать ошибку
    error_log('Import error: ' . $e->getMessage());
    
    // Вернуть безопасное сообщение пользователю
    return ['success' => false, 'error' => 'Failed to import data'];
}
```

### Безопасность
- **SQL Injection:** Всегда использовать prepared statements
- **XSS:** Экранировать все выходные данные в шаблонах
- **CSRF:** Проверять токены в формах админ-панели
- **Input Validation:** Валидировать ВСЕ входные данные перед обработкой

```php
<?php
// ✅ ПРАВИЛЬНО: Prepared statement
$query = $this->db->query(
    "SELECT * FROM `oc_product` WHERE product_id = ? AND status = ?",
    [$product_id, 1]
);

// ❌ НЕПРАВИЛЬНО: String concatenation (SQL Injection!)
$query = $this->db->query(
    "SELECT * FROM `oc_product` WHERE product_id = $product_id"
);
```

---

## 📦 ЖИЗНЕННЫЙ ЦИКЛ РАЗРАБОТКИ

### Фаза 1: Инфраструктура (3 дня)
- [ ] Создать структуру папок модуля
- [ ] Написать install.sql для таблиц
- [ ] Создать базовый контроллер админ-панели
- [ ] Создать базовые модели

**Команды:**
```bash
mkdir -p catalog/extension/module/product_importer/{admin,api,library,model}
phpcs --standard=PSR12 catalog/extension/module/product_importer/
```

### Фаза 2: Парсеры файлов (4 дня)
- [ ] Реализовать CSV парсер
- [ ] Реализовать XLSX парсер (используя phpoffice/phpspreadsheet)
- [ ] Реализовать JSON парсер
- [ ] Написать unit тесты для парсеров

**Тесты:**
```bash
./vendor/bin/phpunit tests/unit/ProductImporterCSVParserTest.php
./vendor/bin/phpunit tests/unit/ProductImporterXLSXParserTest.php
./vendor/bin/phpunit tests/unit/ProductImporterJSONParserTest.php
```

### Фаза 3: Импорт товаров (4 дня)
- [ ] Реализовать валидатор товаров
- [ ] Реализовать обработчик импорта (Add/Update/Merge)
- [ ] Реализовать логирование импорта
- [ ] Написать интеграционные тесты

**Тесты:**
```bash
./vendor/bin/phpunit tests/integration/ProductImportHandlerTest.php
```

### Фаза 4: REST API (3 дня)
- [ ] Реализовать API для импорта товаров
- [ ] Реализовать API для управления категориями
- [ ] Реализовать аутентификацию по токену
- [ ] Написать API тесты

**Тесты:**
```bash
./vendor/bin/phpunit tests/integration/APIEndpointsTest.php --verbose
```

### Фаза 5: Admin Panel UI (3 дня)
- [ ] Создать интерфейс импорта товаров
- [ ] Создать интерфейс управления категориями
- [ ] Создать страницу логов и отчетов
- [ ] Добавить валидацию на фронтенде

### Фаза 6: Тестирование и оптимизация (2 дня)
- [ ] Функциональное тестирование всех операций
- [ ] Тестирование безопасности
- [ ] Тестирование производительности (1000 товаров)
- [ ] Code review и исправления

---

## 🔍 ПРОВЕРКИ КАЧЕСТВА КОДА

### Перед каждым коммитом
```bash
# 1. Проверить синтаксис PHP
php -l catalog/extension/module/product_importer/library/ProductValidator.php

# 2. Линтинг (PSR-12)
./vendor/bin/phpcs --standard=PSR12 \
    catalog/extension/module/product_importer/ \
    --ignore=vendor

# 3. Статический анализ
./vendor/bin/phpstan analyse \
    --level 7 \
    catalog/extension/module/product_importer/

# 4. Unit тесты
./vendor/bin/phpunit tests/unit/ \
    --coverage-minimum-percentage=80

# 5. Security check
./vendor/bin/security-checker security:check composer.lock
```

### Критерии приемки кода
- ✅ Все тесты проходят (100% зелёного)
- ✅ Code coverage ≥ 80%
- ✅ Линтинг: 0 ошибок (PSR-12)
- ✅ Статический анализ: уровень 7
- ✅ Нет warning'ов при выполнении
- ✅ Документация обновлена

---

## 🔐 БЕЗОПАСНОСТЬ

### Проверки безопасности
```bash
# Проверить известные уязвимости в зависимостях
composer audit

# Проверить на SQL Injection (поиск опасных паттернов)
grep -r "query.*\$_" catalog/extension/module/product_importer/
# Результат должен быть пустой!

# Проверить на XSS (неэкранированный вывод)
grep -r "echo.*\$" catalog/extension/module/product_importer/admin/
# Результат должен быть пустой!
```

### Требования безопасности
- [ ] Все SQL запросы используют prepared statements
- [ ] Все выходные данные экранированы (в шаблонах)
- [ ] Проверены права доступа пользователя
- [ ] Проверены CSRF токены в формах
- [ ] Валидированы все входные данные
- [ ] Отсутствуют hardcoded пароли/ключи
- [ ] Все ошибки логируются, но не показываются пользователю

---

## 📝 GIT КОНВЕНЦИИ

### Формат коммитов
```
[FEATURE|BUGFIX|DOCS|TEST] <module>: <description>

Более детальное описание изменений (если нужно).
Может быть несколько строк.

Closes #123
Relates to #456
```

### Примеры коммитов
```bash
# Feature коммит
git commit -m "[FEATURE] product_importer: Add CSV parser with delimiter detection"

# Bugfix коммит
git commit -m "[BUGFIX] import_handler: Fix N+1 query problem in category lookup"

# Документация
git commit -m "[DOCS] Add REST API examples for import endpoint"

# Тест
git commit -m "[TEST] Add unit tests for ProductValidator class"
```

### Pull Request требования
- [ ] Название: `[FEATURE|BUGFIX] <title>`
- [ ] Описание: что изменилось и почему
- [ ] Все тесты проходят (CI должен быть зелёным)
- [ ] Code review от минимум одного другого разработчика
- [ ] Обновлена документация (если нужно)

---

## 📚 ВАЖНЫЕ ФАЙЛЫ ДЛЯ АГЕНТОВ

| Файл | Назначение | Приоритет |
|------|-----------|----------|
| `catalog/extension/module/product_importer/library/ProductValidator.php` | Валидация товаров | HIGH |
| `catalog/extension/module/product_importer/model/extension/module/ProductImportHandler.php` | Главная логика импорта | HIGH |
| `catalog/extension/module/product_importer/api/controller/products.php` | REST API для товаров | HIGH |
| `install.sql` | SQL таблицы (CRITICAL - не менять без миграции!) | HIGH |
| `tests/integration/ProductImportHandlerTest.php` | Интеграционные тесты | MEDIUM |
| `admin/view/template/extension/module/product_importer.twig` | Admin Panel UI | MEDIUM |
| `config.php` | Конфигурация модуля | MEDIUM |
| `README.md` | Документация для пользователей | LOW |

---

## 🐛 DEBUG И ОТЛАДКА

### Логирование
```php
<?php
// Стандартное логирование OpenCart
error_log('Message: ' . print_r($data, true), 3, 'catalog/logs/product_importer.log');

// Или используя встроенный логгер
$log_file = DIR_LOGS . 'product_importer.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
```

### Включение debug режима
```php
<?php
// В catalog/extension/module/product_importer/config.php
define('PRODUCT_IMPORTER_DEBUG', true);

// Тогда в коде:
if (defined('PRODUCT_IMPORTER_DEBUG') && PRODUCT_IMPORTER_DEBUG) {
    echo "Debug info: " . json_encode($data);
}
```

### Использование Xdebug
```bash
# Установить Xdebug
pecl install xdebug

# Настроить php.ini
echo "xdebug.mode=debug" >> /etc/php/8.1/cli/php.ini
echo "xdebug.start_with_request=yes" >> /etc/php/8.1/cli/php.ini

# Запустить с отладкой
XDEBUG_SESSION=vscode php -S localhost:8000
```

---

## 🚨 РЕШЕНИЕ РАСПРОСТРАНЁННЫХ ПРОБЛЕМ

### Проблема: "Fatal error: Class not found"
**Решение:** Проверить namespace и import statements
```bash
# Проверить файл exists
ls catalog/extension/module/product_importer/library/ProductValidator.php

# Проверить namespace в файле совпадает с путем
grep "namespace" catalog/extension/module/product_importer/library/ProductValidator.php
```

### Проблема: "SQL error 1054: Unknown column"
**Решение:** Проверить что таблицы созданы
```bash
mysql -u root -p opencart_db -e "SHOW TABLES LIKE 'oc_import%';"
mysql -u root -p opencart_db -e "DESC oc_import_batch;"
```

### Проблема: "CORS error" в REST API
**Решение:** Проверить CORS настройки в config/api.php
```php
'cors' => [
    'enabled' => true,
    'allowed_origins' => ['*'],  // или конкретные домены
    'allowed_headers' => ['Content-Type', 'X-API-Token'],
]
```

---

## 📞 КОНТАКТЫ И РЕСУРСЫ

### Документация
- OpenCart Docs: https://docs.opencart.com/
- PHP PSR-12: https://www.php-fig.org/psr/psr-12/
- PHPUnit Docs: https://phpunit.de/documentation.html

### Утилиты для разработки
- PHPStan: https://phpstan.org/
- PHP CodeSniffer: https://github.com/squizlabs/PHP_CodeSniffer
- Xdebug: https://xdebug.org/

### Тестовые данные
- `tests/fixtures/sample_products.csv` - Sample CSV для тестирования
- `tests/fixtures/sample_products.json` - Sample JSON для тестирования
- `tests/fixtures/test_data.sql` - SQL для подготовки test БД

---

## ✅ ЧЕКЛИСТ ПЕРЕД РЕЛИЗОМ

- [ ] Все функции реализованы согласно ТЗ
- [ ] Все тесты проходят (100% зелёного)
- [ ] Code coverage ≥ 80%
- [ ] Линтинг: 0 ошибок
- [ ] Документация актуальна
- [ ] CHANGELOG.md обновлен
- [ ] README.md обновлен
- [ ] Version bumped в config.php
- [ ] Git tags созданы (v1.0.0, etc)
- [ ] Модуль тестирован на OpenCart 3.x и 4.x

---

## 📌 ДОПОЛНИТЕЛЬНАЯ ИНФОРМАЦИЯ ДЛЯ АГЕНТОВ

### Как запросить изменение в файлах
```
ВМЕСТО: "Добавь функцию X"
НАПИШИ: "Добавь функцию importBatch() в файл ProductImportHandler.php, 
         которая принимает массив товаров и возвращает результат импорта"
```

### Как описать баг для агента
```
Компонент: REST API
Эндпоинт: POST /api/products/import
Ошибка: Возвращается 500 при импорте товаров с пустым SKU
Файл: api/controller/products.php, строка 45
Ожидаемое поведение: Должна быть валидация на обязательное поле SKU
```

### Как просить код review
```
Файл: catalog/extension/module/product_importer/library/ProductValidator.php
Проверить: Покрыта ли валидация всех полей?
           Правильная ли обработка ошибок?
           Соответствует ли PSR-12?
```

---

**Последний апдейт:** 19.12.2025  
**Версия документа:** 1.0  
**Статус:** Активно используется