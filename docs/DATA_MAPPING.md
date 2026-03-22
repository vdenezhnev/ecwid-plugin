# Схема маппинга данных: Ecwid ↔ WooCommerce

## Обзор

Данный документ описывает соответствие полей между Ecwid REST API v3 и WooCommerce REST API v3 для трех основных сущностей: Products, Orders, Customers.

---

## 1. Products (Товары)

### 1.1 Основные поля продукта

| Ecwid Field | WooCommerce Field | Тип | Примечания |
|-------------|-------------------|-----|------------|
| `id` | - | int | Сохраняется в mapping table |
| - | `id` | int | Генерируется WC |
| `sku` | `sku` | string | 1:1 маппинг |
| `name` | `name` | string | 1:1 маппинг |
| `nameTranslated` | - | object | Требует WPML/Polylang |
| `description` | `description` | string | 1:1 маппинг |
| `descriptionTranslated` | - | object | Требует WPML/Polylang |
| `price` | `regular_price` | decimal | 1:1 маппинг |
| `compareToPrice` | `regular_price` | decimal | При наличии sale |
| `price` (при compareToPrice) | `sale_price` | decimal | Текущая цена = sale |
| `enabled` | `status` | boolean/string | `true` → `publish`, `false` → `draft` |
| `quantity` | `stock_quantity` | int | 1:1 маппинг |
| `unlimited` | `manage_stock` | boolean | `true` → `false`, `false` → `true` |
| `inStock` | `stock_status` | boolean/string | `true` → `instock`, `false` → `outofstock` |
| `weight` | `weight` | decimal | Конвертация единиц |
| `dimensions.length` | `dimensions.length` | decimal | Конвертация единиц |
| `dimensions.width` | `dimensions.width` | decimal | Конвертация единиц |
| `dimensions.height` | `dimensions.height` | decimal | Конвертация единиц |
| `categoryIds` | `categories` | array | Требует маппинг категорий |
| `defaultCategoryId` | `categories[0]` | int | Первая категория = основная |
| `imageUrl` | `images[0].src` | string | Основное изображение |
| `galleryImages` | `images` | array | Галерея изображений |
| `url` | `permalink` | string | Read-only в WC |
| `created` | `date_created` | datetime | Формат ISO 8601 |
| `updated` | `date_modified` | datetime | Формат ISO 8601 |

### 1.2 Расширенные поля продукта

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `isTaxable` | `tax_status` | `true` → `taxable`, `false` → `none` |
| `taxClassCode` | `tax_class` | Требует маппинг классов |
| `isShippingRequired` | `shipping_class_id` | Требует настройки |
| `productClassId` | - | Через атрибуты/метаданные |
| `attributes` | `attributes` | Комплексный маппинг |
| `seoTitle` | `yoast_wpseo_title` | Требует Yoast SEO |
| `seoDescription` | `yoast_wpseo_metadesc` | Требует Yoast SEO |
| `showOnFrontpage` | `featured` | `true` → `true` |
| `wholesalePrices` | - | Требует B2B плагин |
| `relatedProducts.productIds` | `upsell_ids` | 1:1 маппинг |
| `relatedProducts.relatedCategory.enabled` | `cross_sell_ids` | Автоматические связи |

### 1.3 Вариации продукта (Product Variations)

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `combinations` | `variations` | Массив вариаций |
| `combinations[].id` | `variations[].id` | Mapping table |
| `combinations[].sku` | `variations[].sku` | 1:1 маппинг |
| `combinations[].price` | `variations[].regular_price` | 1:1 маппинг |
| `combinations[].compareToPrice` | `variations[].sale_price` | При наличии |
| `combinations[].quantity` | `variations[].stock_quantity` | 1:1 маппинг |
| `combinations[].weight` | `variations[].weight` | 1:1 маппинг |
| `combinations[].options` | `variations[].attributes` | Маппинг атрибутов |
| `combinations[].imageUrl` | `variations[].image` | 1:1 маппинг |

### 1.4 Опции продукта (Product Options → Attributes)

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `options` | `attributes` | Массив атрибутов |
| `options[].name` | `attributes[].name` | 1:1 маппинг |
| `options[].type` | `attributes[].variation` | `SELECT/RADIO/SIZE` → `true` |
| `options[].choices` | `attributes[].options` | Значения опций |
| `options[].required` | - | Не поддерживается WC |
| `options[].defaultChoice` | `default_attributes` | Значение по умолчанию |

### 1.5 Тип опций Ecwid → WooCommerce

| Ecwid Option Type | WooCommerce Handling |
|-------------------|---------------------|
| `SELECT` | Variable product attribute |
| `RADIO` | Variable product attribute |
| `SIZE` | Variable product attribute |
| `CHECKBOX` | Product add-on (плагин) |
| `TEXTFIELD` | Product add-on (плагин) |
| `TEXTAREA` | Product add-on (плагин) |
| `DATE` | Product add-on (плагин) |
| `FILES` | Product add-on (плагин) |

---

## 2. Orders (Заказы)

### 2.1 Основные поля заказа

| Ecwid Field | WooCommerce Field | Тип | Примечания |
|-------------|-------------------|-----|------------|
| `id` | - | int | Mapping table |
| `orderNumber` | `number` | string | Может отличаться |
| - | `id` | int | Генерируется WC |
| `email` | `billing.email` | string | 1:1 маппинг |
| `subtotal` | `subtotal` | decimal | Read-only в WC |
| `total` | `total` | decimal | Read-only в WC |
| `tax` | `total_tax` | decimal | Read-only в WC |
| `paymentMethod` | `payment_method_title` | string | Название метода |
| `paymentModule` | `payment_method` | string | ID метода |
| `createDate` | `date_created` | datetime | ISO 8601 |
| `updateDate` | `date_modified` | datetime | ISO 8601 |
| `ipAddress` | `customer_ip_address` | string | 1:1 маппинг |
| `orderComments` | `customer_note` | string | 1:1 маппинг |
| `privateAdminNotes` | - | string | Через meta_data |
| `customerId` | `customer_id` | int | Требует маппинг |
| `couponDiscount` | `discount_total` | decimal | Read-only |
| `trackingNumber` | - | string | Через meta/плагин |

### 2.2 Статусы заказа

#### Payment Status (Ecwid → WooCommerce)

| Ecwid paymentStatus | WooCommerce status | Примечания |
|---------------------|-------------------|------------|
| `AWAITING_PAYMENT` | `pending` | Ожидает оплаты |
| `PAID` | `processing` | Оплачен |
| `CANCELLED` | `cancelled` | Отменен |
| `REFUNDED` | `refunded` | Возврат |
| `PARTIALLY_REFUNDED` | `refunded` | Частичный возврат |
| `INCOMPLETE` | `failed` | Ошибка оплаты |

#### Fulfillment Status (Ecwid → WooCommerce)

| Ecwid fulfillmentStatus | WooCommerce status | Примечания |
|-------------------------|-------------------|------------|
| `AWAITING_PROCESSING` | `processing` | В обработке |
| `PROCESSING` | `processing` | В обработке |
| `SHIPPED` | `completed` | Отправлен |
| `DELIVERED` | `completed` | Доставлен |
| `WILL_NOT_DELIVER` | `cancelled` | Не будет доставлен |
| `RETURNED` | `refunded` | Возврат |
| `READY_FOR_PICKUP` | `on-hold` | Готов к выдаче |

#### Комбинированный маппинг статусов

| Ecwid Payment | Ecwid Fulfillment | → WC Status |
|---------------|-------------------|-------------|
| `AWAITING_PAYMENT` | * | `pending` |
| `PAID` | `AWAITING_PROCESSING` | `processing` |
| `PAID` | `PROCESSING` | `processing` |
| `PAID` | `SHIPPED` | `completed` |
| `PAID` | `DELIVERED` | `completed` |
| `CANCELLED` | * | `cancelled` |
| `REFUNDED` | * | `refunded` |

### 2.3 Позиции заказа (Order Items)

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `items` | `line_items` | Массив товаров |
| `items[].productId` | `line_items[].product_id` | Требует маппинг |
| `items[].name` | `line_items[].name` | 1:1 маппинг |
| `items[].sku` | `line_items[].sku` | 1:1 маппинг |
| `items[].quantity` | `line_items[].quantity` | 1:1 маппинг |
| `items[].price` | `line_items[].price` | 1:1 маппинг |
| `items[].weight` | - | Через meta_data |
| `items[].tax` | `line_items[].total_tax` | 1:1 маппинг |
| `items[].combinationId` | `line_items[].variation_id` | Требует маппинг |
| `items[].selectedOptions` | `line_items[].meta_data` | Атрибуты вариации |
| `items[].couponApplied` | - | Не прямой аналог |

### 2.4 Адреса

#### Billing Address

| Ecwid Field | WooCommerce Field |
|-------------|-------------------|
| `billingPerson.name` | `billing.first_name` + `billing.last_name` |
| `billingPerson.companyName` | `billing.company` |
| `billingPerson.street` | `billing.address_1` + `billing.address_2` |
| `billingPerson.city` | `billing.city` |
| `billingPerson.countryCode` | `billing.country` |
| `billingPerson.stateOrProvinceCode` | `billing.state` |
| `billingPerson.postalCode` | `billing.postcode` |
| `billingPerson.phone` | `billing.phone` |

#### Shipping Address

| Ecwid Field | WooCommerce Field |
|-------------|-------------------|
| `shippingPerson.name` | `shipping.first_name` + `shipping.last_name` |
| `shippingPerson.companyName` | `shipping.company` |
| `shippingPerson.street` | `shipping.address_1` + `shipping.address_2` |
| `shippingPerson.city` | `shipping.city` |
| `shippingPerson.countryCode` | `shipping.country` |
| `shippingPerson.stateOrProvinceCode` | `shipping.state` |
| `shippingPerson.postalCode` | `shipping.postcode` |
| `shippingPerson.phone` | `shipping.phone` |

### 2.5 Доставка

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `shippingOption.shippingMethodName` | `shipping_lines[].method_title` | 1:1 |
| `shippingOption.shippingRate` | `shipping_lines[].total` | 1:1 |
| `shippingOption.fulfillmentType` | - | meta_data |
| `shippingOption.isPickup` | - | `local_pickup` method |

### 2.6 Скидки и купоны

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `discountCoupon.code` | `coupon_lines[].code` | 1:1 |
| `discountCoupon.discount` | `coupon_lines[].discount` | 1:1 |
| `volumeDiscount` | - | Через meta_data |
| `membershipBasedDiscount` | - | Через meta_data |
| `customDiscount` | - | Через meta_data |

---

## 3. Customers (Клиенты)

### 3.1 Основные поля клиента

| Ecwid Field | WooCommerce Field | Тип | Примечания |
|-------------|-------------------|-----|------------|
| `id` | - | int | Mapping table |
| - | `id` | int | Генерируется WC |
| `email` | `email` | string | 1:1, уникальный |
| `name` | `first_name` + `last_name` | string | Разделить по пробелу |
| `registered` | `date_created` | datetime | ISO 8601 |
| `updated` | `date_modified` | datetime | ISO 8601 |

### 3.2 Адреса клиента

#### Billing Address

| Ecwid Field | WooCommerce Field |
|-------------|-------------------|
| `billingPerson.name` | `billing.first_name` + `billing.last_name` |
| `billingPerson.companyName` | `billing.company` |
| `billingPerson.street` | `billing.address_1` |
| `billingPerson.city` | `billing.city` |
| `billingPerson.countryCode` | `billing.country` |
| `billingPerson.stateOrProvinceCode` | `billing.state` |
| `billingPerson.postalCode` | `billing.postcode` |
| `billingPerson.phone` | `billing.phone` |

#### Shipping Address

| Ecwid Field | WooCommerce Field |
|-------------|-------------------|
| `shippingAddresses[0].name` | `shipping.first_name` + `shipping.last_name` |
| `shippingAddresses[0].companyName` | `shipping.company` |
| `shippingAddresses[0].street` | `shipping.address_1` |
| `shippingAddresses[0].city` | `shipping.city` |
| `shippingAddresses[0].countryCode` | `shipping.country` |
| `shippingAddresses[0].stateOrProvinceCode` | `shipping.state` |
| `shippingAddresses[0].postalCode` | `shipping.postcode` |

### 3.3 Группы клиентов

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `customerGroupId` | `role` | Требует маппинг |
| `customerGroupName` | - | Создать WP role |

#### Маппинг групп клиентов

| Ecwid Group | WooCommerce Role | Примечания |
|-------------|------------------|------------|
| General (default) | `customer` | Стандартный клиент |
| Wholesale | `wholesale_customer` | Требует B2B плагин |
| VIP | `vip_customer` | Кастомная роль |

### 3.4 Дополнительные поля

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `acceptMarketing` | - | meta_data / mailchimp |
| `taxId` | - | meta_data |
| `taxIdValid` | - | meta_data |
| `taxExempt` | `is_vat_exempt` | 1:1 |
| `contacts.phone` | `billing.phone` | Основной телефон |

---

## 4. Categories (Категории)

### 4.1 Основные поля категории

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `id` | - | Mapping table |
| - | `id` | Генерируется WC |
| `name` | `name` | 1:1 |
| `nameTranslated` | - | WPML/Polylang |
| `description` | `description` | 1:1 |
| `parentId` | `parent` | Требует маппинг |
| `orderBy` | `menu_order` | 1:1 |
| `enabled` | - | Нет прямого аналога |
| `productCount` | `count` | Read-only |
| `imageUrl` | `image.src` | 1:1 |

---

## 5. Coupons (Купоны)

### 5.1 Основные поля купона

| Ecwid Field | WooCommerce Field | Примечания |
|-------------|-------------------|------------|
| `id` | - | Mapping table |
| `code` | `code` | 1:1 |
| `name` | `description` | Название → описание |
| `discount` | `amount` | Значение скидки |
| `discountType` | `discount_type` | См. таблицу ниже |
| `status` | `status` | См. таблицу ниже |
| `launchDate` | `date_created` | Дата создания |
| `expirationDate` | `date_expires` | Дата истечения |
| `totalLimit` | `minimum_amount` | Мин. сумма заказа |
| `usesLimit` | `usage_limit` | Лимит использований |
| `applicationLimit` | `usage_limit_per_user` | Лимит на клиента |

### 5.2 Типы скидок

| Ecwid discountType | WooCommerce discount_type |
|-------------------|--------------------------|
| `ABS` | `fixed_cart` |
| `PERCENT` | `percent` |
| `SHIPPING` | `fixed_cart` + free_shipping |
| `ABS_AND_SHIPPING` | `fixed_cart` + free_shipping |
| `PERCENT_AND_SHIPPING` | `percent` + free_shipping |

### 5.3 Статусы купонов

| Ecwid status | WooCommerce Handling |
|--------------|---------------------|
| `ACTIVE` | Публикуется |
| `PAUSED` | Draft или scheduled |
| `EXPIRED` | Автоматически (date_expires) |
| `USEDUP` | Проверяется usage_count |

---

## 6. Конвертация единиц измерения

### 6.1 Вес

| Ecwid Unit | WooCommerce Unit | Коэффициент |
|------------|------------------|-------------|
| `kg` | `kg` | 1 |
| `lb` | `lbs` | 1 |
| `oz` | `oz` | 1 |
| `g` | `g` | 1 |

### 6.2 Размеры

| Ecwid Unit | WooCommerce Unit | Коэффициент |
|------------|------------------|-------------|
| `cm` | `cm` | 1 |
| `in` | `in` | 1 |
| `mm` | `mm` | 1 |
| `m` | `m` | 1 |

---

## 7. Специальные случаи маппинга

### 7.1 Разделение имени

```
Ecwid: "John Smith Jr."
↓
WooCommerce: 
  first_name: "John"
  last_name: "Smith Jr."
```

**Алгоритм**: Первое слово → first_name, остальное → last_name

### 7.2 Разделение адреса

```
Ecwid street: "123 Main St\nApt 4B"
↓
WooCommerce:
  address_1: "123 Main St"
  address_2: "Apt 4B"
```

**Алгоритм**: Разделить по `\n`, первая строка → address_1, остальное → address_2

### 7.3 Конвертация дат

```
Ecwid: "2024-03-15 14:30:00 +0000"
↓
WooCommerce: "2024-03-15T14:30:00"
```

**Формат**: ISO 8601 без timezone (WC использует site timezone)

### 7.4 Обработка изображений

1. Скачать изображение с Ecwid CDN
2. Загрузить в WordPress Media Library
3. Получить attachment ID
4. Привязать к продукту

### 7.5 Сохранение связей ID

```php
// Таблица маппинга
[
    'entity_type' => 'product',
    'ecwid_id' => '123456789',
    'wc_id' => 42,
    'sync_status' => 'synced'
]
```

---

## 8. Примеры преобразования

### 8.1 Продукт Ecwid → WooCommerce

**Ecwid Product:**
```json
{
  "id": 123456789,
  "sku": "TSHIRT-001",
  "name": "Classic T-Shirt",
  "description": "<p>Comfortable cotton t-shirt</p>",
  "price": 29.99,
  "compareToPrice": 39.99,
  "enabled": true,
  "quantity": 100,
  "unlimited": false,
  "inStock": true,
  "weight": 0.3,
  "categoryIds": [1001, 1002],
  "imageUrl": "https://ecwid.com/images/product.jpg",
  "options": [
    {
      "name": "Size",
      "type": "SIZE",
      "choices": [
        {"text": "S", "priceModifier": 0},
        {"text": "M", "priceModifier": 0},
        {"text": "L", "priceModifier": 2}
      ]
    }
  ]
}
```

**WooCommerce Product:**
```json
{
  "sku": "TSHIRT-001",
  "name": "Classic T-Shirt",
  "description": "<p>Comfortable cotton t-shirt</p>",
  "type": "variable",
  "status": "publish",
  "regular_price": "39.99",
  "sale_price": "29.99",
  "manage_stock": true,
  "stock_quantity": 100,
  "stock_status": "instock",
  "weight": "0.3",
  "categories": [
    {"id": 10},
    {"id": 11}
  ],
  "images": [
    {"src": "https://ecwid.com/images/product.jpg"}
  ],
  "attributes": [
    {
      "name": "Size",
      "visible": true,
      "variation": true,
      "options": ["S", "M", "L"]
    }
  ]
}
```

### 8.2 Заказ Ecwid → WooCommerce

**Ecwid Order:**
```json
{
  "id": 987654321,
  "orderNumber": "00042",
  "email": "customer@example.com",
  "subtotal": 59.98,
  "total": 69.98,
  "tax": 5.00,
  "paymentStatus": "PAID",
  "fulfillmentStatus": "PROCESSING",
  "items": [
    {
      "productId": 123456789,
      "name": "Classic T-Shirt",
      "quantity": 2,
      "price": 29.99
    }
  ],
  "billingPerson": {
    "name": "John Smith",
    "street": "123 Main St",
    "city": "New York",
    "countryCode": "US",
    "stateOrProvinceCode": "NY",
    "postalCode": "10001"
  },
  "shippingOption": {
    "shippingMethodName": "Standard Shipping",
    "shippingRate": 5.00
  }
}
```

**WooCommerce Order:**
```json
{
  "status": "processing",
  "billing": {
    "first_name": "John",
    "last_name": "Smith",
    "email": "customer@example.com",
    "address_1": "123 Main St",
    "city": "New York",
    "country": "US",
    "state": "NY",
    "postcode": "10001"
  },
  "shipping": {
    "first_name": "John",
    "last_name": "Smith",
    "address_1": "123 Main St",
    "city": "New York",
    "country": "US",
    "state": "NY",
    "postcode": "10001"
  },
  "line_items": [
    {
      "product_id": 42,
      "name": "Classic T-Shirt",
      "quantity": 2,
      "price": 29.99
    }
  ],
  "shipping_lines": [
    {
      "method_title": "Standard Shipping",
      "total": "5.00"
    }
  ],
  "meta_data": [
    {
      "key": "_ecwid_order_id",
      "value": "987654321"
    }
  ]
}
```

---

## 9. Обработка ошибок маппинга

### 9.1 Обязательные поля

| Entity | Required Fields |
|--------|-----------------|
| Product | `sku` or `name` |
| Order | `email`, at least one `line_item` |
| Customer | `email` |

### 9.2 Стратегии при отсутствии данных

| Ситуация | Стратегия |
|----------|-----------|
| Отсутствует SKU | Генерировать из `ecwid_id` |
| Отсутствует категория | Использовать "Uncategorized" |
| Невалидный email | Пропустить запись, логировать |
| Отсутствует цена | Установить 0, пометить для review |

### 9.3 Логирование проблем маппинга

```php
[
    'entity_type' => 'product',
    'entity_id' => '123456789',
    'field' => 'categoryIds',
    'issue' => 'Category 1003 not found in mapping',
    'resolution' => 'Assigned to Uncategorized'
]
```
