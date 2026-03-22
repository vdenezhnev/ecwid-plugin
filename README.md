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
│   ├── sync/                  # Sync services
│   ├── mappers/               # Data mappers
│   └── utils/                 # Utilities
├── admin/                     # Admin interface
├── docs/                      # Documentation
│   ├── TECHNICAL_SPEC.md
│   ├── DATA_MAPPING.md
│   └── ARCHITECTURE.md
└── tests/                     # PHPUnit tests
```

## Roadmap

### Фаза 1: MVP
- [ ] Базовая структура плагина
- [ ] API-клиенты Ecwid и WooCommerce
- [ ] Маппинг продуктов
- [ ] Синхронизация Ecwid → WooCommerce
- [ ] Админ-интерфейс

### Фаза 2: Расширение
- [ ] Синхронизация заказов
- [ ] Синхронизация клиентов
- [ ] Обратная синхронизация
- [ ] Webhooks

### Фаза 3: Продвинутые функции
- [ ] Двусторонняя синхронизация
- [ ] Разрешение конфликтов
- [ ] REST API плагина

## Лицензия

GPL v2 или выше
