# Ecwid WooCommerce Sync

WordPress плагин для синхронизации заказов между Ecwid и WooCommerce.

## Функциональность

- **Импорт заказов из Ecwid в WooCommerce** - автоматический и ручной импорт заказов
- **Вебхуки Ecwid** - мгновенная синхронизация при создании/обновлении/удалении заказов
- **WP-Cron синхронизация** - периодическая проверка новых заказов (настраиваемый интервал)
- **Маппинг клиентов** - автоматическое создание или связывание клиентов WooCommerce с Ecwid
- **Маппинг платежей** - конвертация платежных методов Ecwid в WooCommerce
- **Обновление статусов** - двусторонняя синхронизация статусов заказов
- **Логирование** - детальные логи всех операций синхронизации

## Требования

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+
- Ecwid Store с API доступом

## Установка

1. Загрузите папку плагина в `/wp-content/plugins/`
2. Активируйте плагин через меню 'Плагины' в WordPress
3. Перейдите в WooCommerce → Ecwid Sync для настройки

## Настройка

### API Credentials

1. Получите Store ID и API Token из панели Ecwid
2. Введите их на странице настроек плагина
3. Нажмите "Test Connection" для проверки

### Вебхуки

Плагин поддерживает два способа получения вебхуков:

1. **REST API** (рекомендуется): `https://your-site.com/wp-json/ecwid-sync/v1/webhook`
2. **Legacy endpoint**: `https://your-site.com/ecwid-webhook/`

Вы можете автоматически зарегистрировать вебхук через кнопку "Register Webhook Automatically".

### Интервалы синхронизации

Доступные интервалы:
- Каждые 5 минут
- Каждые 15 минут
- Каждые 30 минут
- Каждый час (по умолчанию)
- Дважды в день
- Ежедневно

## Маппинг статусов

### Ecwid → WooCommerce

| Ecwid Payment | Ecwid Fulfillment | WooCommerce Status |
|--------------|-------------------|-------------------|
| PAID | AWAITING_PROCESSING | processing |
| PAID | PROCESSING | processing |
| PAID | SHIPPED | completed |
| PAID | DELIVERED | completed |
| AWAITING_PAYMENT | * | pending |
| CANCELLED | * | cancelled |
| REFUNDED | * | refunded |

### WooCommerce → Ecwid

| WooCommerce Status | Ecwid Fulfillment |
|-------------------|-------------------|
| pending | AWAITING_PROCESSING |
| processing | PROCESSING |
| completed | SHIPPED |
| cancelled | WILL_NOT_DELIVER |
| refunded | RETURNED |

## Маппинг платежей

Поддерживаемые методы:
- PayPal → paypal
- Stripe → stripe
- Square → square
- Cash/CashOnDelivery → cod
- BankTransfer/WireTransfer → bacs
- Check/Cheque → cheque

## Хуки и фильтры

### Фильтры

```php
// Изменить маппинг статусов
add_filter( 'ecwid_status_mappings', function( $mappings ) {
    $mappings['PAID_SHIPPED'] = 'completed';
    return $mappings;
});

// Изменить маппинг платежных методов
add_filter( 'ecwid_payment_method_mappings', function( $mappings ) {
    $mappings['CustomPayment'] = array(
        'id'    => 'custom',
        'title' => 'Custom Payment',
    );
    return $mappings;
});
```

## База данных

Плагин создает две таблицы:

- `wp_ecwid_synced_orders` - связь между Ecwid и WooCommerce заказами
- `wp_ecwid_sync_logs` - логи синхронизации

## Разработка

### Структура файлов

```
ecwid-woocommerce-sync/
├── ecwid-woocommerce-sync.php  # Основной файл плагина
├── includes/
│   ├── class-ecwid-api-client.php      # Клиент Ecwid API
│   ├── class-ecwid-order-sync.php      # Синхронизация заказов
│   ├── class-ecwid-webhook-handler.php # Обработка вебхуков
│   ├── class-ecwid-cron-handler.php    # WP-Cron задания
│   ├── class-ecwid-customer-mapper.php # Маппинг клиентов
│   ├── class-ecwid-payment-mapper.php  # Маппинг оплат
│   ├── class-ecwid-status-mapper.php   # Маппинг статусов
│   └── class-ecwid-logger.php          # Логирование
├── admin/
│   └── class-ecwid-admin.php           # Админ-панель
└── assets/
    ├── css/admin.css                   # Стили админки
    └── js/admin.js                     # Скрипты админки
```

## Лицензия

GPL v2 or later
