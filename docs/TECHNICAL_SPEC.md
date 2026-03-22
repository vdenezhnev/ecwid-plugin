# Техническая спецификация: Плагин Ecwid-WooCommerce Integration

## 1. Обзор проекта

### 1.1 Назначение
WordPress-плагин для двусторонней синхронизации данных между Ecwid и WooCommerce. Позволяет мигрировать продукты, заказы и клиентов между платформами, а также поддерживать актуальность данных в режиме реального времени.

### 1.2 Целевая аудитория
- Владельцы интернет-магазинов, переходящие с Ecwid на WooCommerce
- Владельцы магазинов, использующие обе платформы одновременно
- Разработчики, интегрирующие e-commerce решения

### 1.3 Технические требования
- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Доступ к Ecwid API (Store ID + Access Token)
- Доступ к WooCommerce REST API (Consumer Key + Secret)

---

## 2. Архитектура плагина

### 2.1 Структура каталогов

```
ecwid-woocommerce/
├── ecwid-woocommerce.php          # Главный файл плагина
├── includes/
│   ├── class-plugin.php           # Основной класс плагина
│   ├── class-activator.php        # Активация плагина
│   ├── class-deactivator.php      # Деактивация плагина
│   ├── api/
│   │   ├── class-ecwid-api.php    # Клиент Ecwid API
│   │   ├── class-wc-api.php       # Клиент WooCommerce API
│   │   └── interface-api.php      # Интерфейс API-клиента
│   ├── sync/
│   │   ├── class-sync-manager.php # Менеджер синхронизации
│   │   ├── class-product-sync.php # Синхронизация продуктов
│   │   ├── class-order-sync.php   # Синхронизация заказов
│   │   └── class-customer-sync.php# Синхронизация клиентов
│   ├── mappers/
│   │   ├── class-product-mapper.php   # Маппер продуктов
│   │   ├── class-order-mapper.php     # Маппер заказов
│   │   └── class-customer-mapper.php  # Маппер клиентов
│   └── utils/
│       ├── class-logger.php       # Логирование
│       └── class-queue.php        # Очередь задач
├── admin/
│   ├── class-admin.php            # Админ-панель
│   ├── views/
│   │   ├── settings.php           # Страница настроек
│   │   ├── sync-dashboard.php     # Дашборд синхронизации
│   │   └── logs.php               # Просмотр логов
│   ├── css/
│   │   └── admin.css              # Стили админки
│   └── js/
│       └── admin.js               # Скрипты админки
├── languages/
│   └── ecwid-woocommerce.pot      # Файл локализации
└── docs/
    ├── TECHNICAL_SPEC.md          # Техническая спецификация
    └── DATA_MAPPING.md            # Схема маппинга данных
```

### 2.2 Компоненты системы

#### 2.2.1 API Layer
- **Ecwid API Client**: Взаимодействие с Ecwid REST API v3
- **WooCommerce API Client**: Взаимодействие с WC REST API v3
- **Rate Limiter**: Контроль частоты запросов (600 req/min для Ecwid)

#### 2.2.2 Sync Layer
- **Sync Manager**: Оркестрация процессов синхронизации
- **Product Sync**: Синхронизация товаров и вариаций
- **Order Sync**: Синхронизация заказов и статусов
- **Customer Sync**: Синхронизация клиентов

#### 2.2.3 Data Layer
- **Mappers**: Преобразование данных между форматами
- **Queue**: Очередь асинхронных задач
- **Logger**: Система логирования

#### 2.2.4 Admin Layer
- **Settings Page**: Настройки API ключей
- **Dashboard**: Статус синхронизации
- **Log Viewer**: Просмотр журнала событий

---

## 3. API Integration

### 3.1 Ecwid REST API v3

**Base URL**: `https://app.ecwid.com/api/v3/{storeId}/`

**Аутентификация**: Bearer Token в заголовке Authorization

**Основные endpoints**:
| Endpoint | Метод | Описание |
|----------|-------|----------|
| `/products` | GET | Получение списка продуктов |
| `/products/{id}` | GET | Получение продукта по ID |
| `/products` | POST | Создание продукта |
| `/products/{id}` | PUT | Обновление продукта |
| `/orders` | GET | Получение списка заказов |
| `/orders/{id}` | GET | Получение заказа по ID |
| `/orders` | POST | Создание заказа |
| `/customers` | GET | Получение списка клиентов |
| `/customers/{id}` | GET | Получение клиента по ID |

**Лимиты**:
- 600 запросов в минуту на токен
- При превышении возвращается HTTP 429

### 3.2 WooCommerce REST API v3

**Base URL**: `{site_url}/wp-json/wc/v3/`

**Аутентификация**: 
- HTTPS: HTTP Basic Auth (Consumer Key:Secret)
- HTTP: OAuth 1.0a

**Основные endpoints**:
| Endpoint | Метод | Описание |
|----------|-------|----------|
| `/products` | GET | Получение списка продуктов |
| `/products/{id}` | GET | Получение продукта по ID |
| `/products` | POST | Создание продукта |
| `/products/{id}` | PUT | Обновление продукта |
| `/orders` | GET | Получение списка заказов |
| `/orders/{id}` | GET | Получение заказа по ID |
| `/orders` | POST | Создание заказа |
| `/customers` | GET | Получение списка клиентов |
| `/customers/{id}` | GET | Получение клиента по ID |

---

## 4. Режимы синхронизации

### 4.1 Ecwid → WooCommerce (Import)
Импорт данных из Ecwid в WooCommerce:
- Полный импорт (начальная миграция)
- Инкрементальный импорт (только изменения)
- Поддержка webhooks для real-time обновлений

### 4.2 WooCommerce → Ecwid (Export)
Экспорт данных из WooCommerce в Ecwid:
- Полный экспорт
- Инкрементальный экспорт
- Поддержка WooCommerce hooks для real-time обновлений

### 4.3 Двусторонняя синхронизация (Bidirectional)
- Определение "источника истины" (master system)
- Разрешение конфликтов по временной метке
- Предотвращение циклических обновлений

---

## 5. Обработка ошибок

### 5.1 Стратегия повторных попыток
```
Retry Strategy:
├── Max retries: 3
├── Backoff: Exponential (2s, 4s, 8s)
├── Recoverable errors: 429, 500, 502, 503, 504
└── Non-recoverable: 400, 401, 403, 404
```

### 5.2 Логирование
- Все API запросы и ответы
- Ошибки с полным контекстом
- Действия синхронизации
- Ротация логов (по размеру и времени)

### 5.3 Уведомления
- Email-уведомления при критических ошибках
- Admin notices в WordPress
- Dashboard с метриками синхронизации

---

## 6. Безопасность

### 6.1 Хранение учетных данных
- API ключи шифруются перед сохранением в БД
- Использование WordPress Transients API для кэширования токенов
- Права доступа: только администраторы

### 6.2 Валидация данных
- Sanitization всех входящих данных
- Validation по схемам API
- Escaping при выводе

### 6.3 Защита endpoints
- Nonce verification для AJAX-запросов
- Capability checks для всех действий
- Rate limiting на уровне плагина

---

## 7. Производительность

### 7.1 Batch Processing
- Пакетная обработка: 50 записей за раз
- Background processing через WP-Cron
- Progress tracking для длительных операций

### 7.2 Кэширование
- Кэширование API-ответов (15 минут)
- Кэширование результатов маппинга
- Инвалидация при изменениях

### 7.3 Оптимизация запросов
- Параллельные запросы где возможно
- Запрос только необходимых полей
- Пагинация с поддержкой offset/cursor

---

## 8. База данных

### 8.1 Таблицы плагина

```sql
-- Таблица маппинга ID между системами
CREATE TABLE {prefix}ecwid_wc_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,         -- product, order, customer
    ecwid_id VARCHAR(100) NOT NULL,
    wc_id BIGINT UNSIGNED NOT NULL,
    ecwid_updated_at DATETIME,
    wc_updated_at DATETIME,
    sync_status VARCHAR(20) DEFAULT 'synced', -- synced, pending, error
    last_sync_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity_ecwid (entity_type, ecwid_id),
    INDEX idx_entity_wc (entity_type, wc_id),
    INDEX idx_sync_status (sync_status)
);

-- Таблица очереди синхронизации
CREATE TABLE {prefix}ecwid_wc_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,              -- import, export, update
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(100) NOT NULL,
    source_system VARCHAR(20) NOT NULL,       -- ecwid, woocommerce
    payload LONGTEXT,
    priority INT DEFAULT 10,
    attempts INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',     -- pending, processing, completed, failed
    error_message TEXT,
    scheduled_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority),
    INDEX idx_scheduled (scheduled_at)
);

-- Таблица логов
CREATE TABLE {prefix}ecwid_wc_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,               -- debug, info, warning, error
    message TEXT NOT NULL,
    context LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level_date (level, created_at)
);
```

---

## 9. Конфигурация

### 9.1 Настройки плагина
```php
[
    // Ecwid API
    'ecwid_store_id' => '',
    'ecwid_access_token' => '',
    
    // WooCommerce API (для внешнего WC)
    'wc_site_url' => '',
    'wc_consumer_key' => '',
    'wc_consumer_secret' => '',
    
    // Синхронизация
    'sync_direction' => 'ecwid_to_wc',  // ecwid_to_wc, wc_to_ecwid, bidirectional
    'sync_products' => true,
    'sync_orders' => true,
    'sync_customers' => true,
    'sync_interval' => 'hourly',         // realtime, hourly, daily, manual
    
    // Продвинутые
    'batch_size' => 50,
    'conflict_resolution' => 'latest',   // latest, source, manual
    'log_level' => 'info',
    'log_retention_days' => 30,
]
```

---

## 10. Webhooks

### 10.1 Ecwid Webhooks
Ecwid отправляет webhook-уведомления на указанный URL:
- `order.created` - новый заказ
- `order.updated` - изменение заказа
- `product.created` - новый продукт
- `product.updated` - изменение продукта
- `product.deleted` - удаление продукта

### 10.2 WooCommerce Hooks
Используем стандартные WooCommerce action hooks:
- `woocommerce_new_product` - создание продукта
- `woocommerce_update_product` - обновление продукта
- `woocommerce_delete_product` - удаление продукта
- `woocommerce_new_order` - новый заказ
- `woocommerce_order_status_changed` - изменение статуса заказа

---

## 11. Тестирование

### 11.1 Unit Tests
- Тесты mappers
- Тесты API-клиентов (с mock)
- Тесты utility-функций

### 11.2 Integration Tests
- Тесты полного цикла синхронизации
- Тесты обработки ошибок
- Тесты очереди задач

### 11.3 E2E Tests
- Тесты UI админки
- Тесты webhook-обработчиков

---

## 12. Roadmap

### Фаза 1: MVP
- [ ] Базовая структура плагина
- [ ] API-клиенты Ecwid и WooCommerce
- [ ] Маппинг продуктов
- [ ] Односторонняя синхронизация Ecwid → WC
- [ ] Админ-интерфейс настроек

### Фаза 2: Расширение
- [ ] Синхронизация заказов
- [ ] Синхронизация клиентов
- [ ] Обратная синхронизация WC → Ecwid
- [ ] Webhook-интеграция

### Фаза 3: Продвинутые функции
- [ ] Двусторонняя синхронизация
- [ ] Разрешение конфликтов
- [ ] Расширенное логирование
- [ ] REST API для внешней интеграции

---

## 13. Зависимости

### 13.1 PHP Пакеты (Composer)
```json
{
    "require": {
        "php": ">=7.4",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "mockery/mockery": "^1.4",
        "squizlabs/php_codesniffer": "^3.6"
    }
}
```

### 13.2 WordPress/WooCommerce
- WordPress >= 5.0
- WooCommerce >= 5.0

---

## 14. Лицензия

GPL v2 или выше (совместимо с WordPress)
