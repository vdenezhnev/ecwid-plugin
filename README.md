# Ecwid-WooCommerce Integration Plugin

WordPress плагин для двусторонней синхронизации данных между Ecwid и WooCommerce.

## Описание

Этот плагин позволяет:
- Импортировать продукты, заказы и клиентов из Ecwid в WooCommerce
- Экспортировать данные из WooCommerce в Ecwid
- Поддерживать синхронизацию в реальном времени через webhooks
- Разрешать конфликты при двусторонней синхронизации

## Требования

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Доступ к Ecwid API (Store ID + Access Token)

## Документация

| Документ | Описание |
|----------|----------|
| [Техническая спецификация](docs/TECHNICAL_SPEC.md) | Детальное описание архитектуры, API интеграции, конфигурации |
| [Схема маппинга данных](docs/DATA_MAPPING.md) | Соответствие полей между Ecwid и WooCommerce |
| [Архитектура и диаграммы](docs/ARCHITECTURE.md) | Визуальные диаграммы компонентов, потоков данных, классов |

## Архитектура

```
┌─────────────────────────────────────────────────────┐
│                ECWID-WOOCOMMERCE PLUGIN             │
├─────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │  Admin UI   │  │ Sync Layer  │  │ API Clients │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │   Mappers   │  │    Queue    │  │   Logger    │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────┘
         │                                  │
         ▼                                  ▼
┌─────────────────┐              ┌─────────────────┐
│  Ecwid API v3   │              │ WooCommerce API │
│                 │              │      v3         │
└─────────────────┘              └─────────────────┘
```

## Синхронизируемые сущности

### Products (Товары)
- Основная информация (название, описание, SKU, цена)
- Изображения и галерея
- Вариации и опции
- Категории
- Запасы и вес

### Orders (Заказы)
- Детали заказа и позиции
- Статусы оплаты и доставки
- Адреса биллинга и доставки
- Скидки и купоны

### Customers (Клиенты)
- Контактная информация
- Адреса
- Группы клиентов

## Режимы синхронизации

| Режим | Описание |
|-------|----------|
| Ecwid → WooCommerce | Импорт данных из Ecwid в WooCommerce |
| WooCommerce → Ecwid | Экспорт данных из WooCommerce в Ecwid |
| Bidirectional | Двусторонняя синхронизация с разрешением конфликтов |

## API интеграция

### Ecwid REST API v3
- **Base URL**: `https://app.ecwid.com/api/v3/{storeId}/`
- **Auth**: Bearer Token
- **Rate Limit**: 600 requests/min

### WooCommerce REST API v3
- **Base URL**: `{site}/wp-json/wc/v3/`
- **Auth**: OAuth 1.0a / Basic Auth
- **Format**: JSON

## Структура проекта

```
ecwid-woocommerce/
├── ecwid-woocommerce.php      # Main plugin file
├── includes/
│   ├── api/                   # API clients
│   │   └── class-ecwid-api.php
│   ├── sync/                  # Sync services
│   │   ├── class-product-sync.php
│   │   ├── class-order-sync.php
│   │   └── class-sync-hooks.php
│   ├── mappers/               # Data mappers
│   │   ├── class-product-mapper.php
│   │   ├── class-order-mapper.php
│   │   └── class-customer-mapper.php
│   ├── webhooks/              # Webhook handlers
│   │   └── class-webhook-handler.php
│   └── utils/                 # Utilities
│       ├── class-logger.php
│       ├── class-encryption.php
│       └── class-mapping-repository.php
├── admin/                     # Admin interface
├── docs/                      # Documentation
│   ├── TECHNICAL_SPEC.md
│   ├── DATA_MAPPING.md
│   └── ARCHITECTURE.md
└── tests/                     # PHPUnit tests
```

## Возможности импорта заказов

### Webhook-based синхронизация
Плагин поддерживает получение webhook-уведомлений от Ecwid для:
- `order.created` - новый заказ
- `order.updated` - обновление заказа
- `order.deleted` - удаление заказа

**Webhook URL**: `{site}/wp-json/ecwid-wc/v1/webhook`

### WP-Cron периодическая проверка
Если webhook-и не настроены, плагин автоматически проверяет новые заказы каждые 15 минут через WP-Cron.

### Маппинг статусов заказа

| Ecwid Payment Status | WooCommerce Status |
|---------------------|-------------------|
| AWAITING_PAYMENT | pending |
| PAID | processing |
| CANCELLED | cancelled |
| REFUNDED | refunded |

| Ecwid Fulfillment Status | WooCommerce Status |
|-------------------------|-------------------|
| AWAITING_PROCESSING | processing |
| PROCESSING | processing |
| SHIPPED | completed |
| DELIVERED | completed |

### Двунаправленное обновление статусов
При изменении статуса заказа в WooCommerce (который связан с Ecwid), статус автоматически синхронизируется обратно в Ecwid.

## Roadmap

### Фаза 1: MVP ✅
- [x] Базовая структура плагина
- [x] API-клиент Ecwid
- [x] Шифрование API ключей (AES-256)
- [x] Маппинг продуктов
- [x] Экспорт продуктов WC → Ecwid
- [x] Админ-интерфейс

### Фаза 2: Расширение ✅
- [x] Импорт заказов из Ecwid
- [x] Маппинг клиентов
- [x] Webhook Handler для Ecwid
- [x] WP-Cron для периодической проверки
- [x] Двунаправленное обновление статусов

### Фаза 3: Продвинутые функции
- [ ] Полная двусторонняя синхронизация
- [ ] Импорт продуктов из Ecwid
- [ ] REST API плагина
- [ ] Разрешение конфликтов

## Лицензия

GPL v2 или выше
