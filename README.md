# amarenkov/laravel-mutable-content-shop

[![tests](https://github.com/amarenkov/laravel-mutable-content-shop/actions/workflows/tests.yml/badge.svg)](https://github.com/amarenkov/laravel-mutable-content-shop/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/amarenkov/laravel-mutable-content-shop)](https://packagist.org/packages/amarenkov/laravel-mutable-content-shop)
![coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)

Shop domain for [`amarenkov/laravel-mutable-content`](https://github.com/amarenkov/laravel-mutable-content):
carts of signed in users and guests, orders with snapshots of what was bought, price breaks and
exact money totals, with a service and an event to build any storefront on.

## At a glance

```php
use Amarenkov\MutableContentShop\Services\Cart;

$cart = Cart::current();                 // the signed in user's cart or the guest's one
$cart->add($product, 3);                 // the same product again adds up
$cart->total();                          // Money, rounded to the currency per line

$order = $cart->checkout([
    'phone' => '+1 555 0100',
    'comment' => 'Call before delivery',
]);
$order->number;                          // 202610150042
```

## Scope

- Models with mutable fields: an application adds its own fields from code or from the admin
  without migrations, and its own model subclasses with more fields.
- Prices and totals are `money` fields of the core: exact decimal arithmetic, a line total is
  the unit price times the quantity rounded to the currency decimals, an order total is the sum
  of the rounded lines.
- Order and order item changes land in the core change log with their author.
- **No user interface.** Catalogs, storefront pages, admin screens and notifications belong to
  the application.

## Model

| Model | What it is |
|---|---|
| `CartItem` | A line of the cart of a user (`user_id`) or of a guest (`token` kept in the session) |
| `Order` | Number, state, customer, contacts (first and last name, company, phone, email), comment, total |
| `OrderItem` | A copy of the cart line it was made of, with its own state |

Cart and order lines share the `PricedLine` trait: what is sold (`sellable_type`, `sellable_id`),
name, price breaks, minimum order quantity, order multiple, quantity, unit price and total.
`HasPriceBreaks` alone suits a catalog model: one price is a break from the quantity of 1.

States are LOVs: `order_state` and `order_item_state` with `new`, `paid`, `done` and `cancelled`.

## Requirements

- PHP 8.4
- Laravel 13
- PostgreSQL or MariaDB 10.7+, as required by `amarenkov/laravel-mutable-content`

## Installation

```bash
composer require amarenkov/laravel-mutable-content-shop
php artisan vendor:publish --tag=mutable-content-shop-migrations
php artisan migrate
php artisan db:seed --class="Amarenkov\MutableContent\Database\Seeders\LovsSeeder"
php artisan db:seed --class="Amarenkov\MutableContent\Database\Seeders\FieldsSeeder"
```

Add the currencies you sell in as items of the `currency` LOV of the core: it has only USD.

## Usage

### What can be sold

Anything implementing `Sellable` goes to a cart: a product, an offer of a seller, a search result
pointing at an offer. `lineFields()` gives the values a new line starts with; the line keeps the
fields its class has and skips the rest.

```php
use Amarenkov\MutableContent\ValueObjects\Money;
use Amarenkov\MutableContentShop\Concerns\HasPriceBreaks;
use Amarenkov\MutableContentShop\Contracts\Sellable;

class Product extends ModelWithFields implements Sellable
{
    use HasPriceBreaks;

    public function sellableType(): string { return $this->getMorphClass(); }

    public function sellableId(): int { return $this->id; }

    public function lineFields(): array
    {
        return [
            'name' => $this->label,
            self::FIELD_PRICES => $this->getField(self::FIELD_PRICES),
            self::FIELD_MOQ => $this->getField(self::FIELD_MOQ),
            self::FIELD_ORDER_MULTIPLE => $this->getField(self::FIELD_ORDER_MULTIPLE),
        ];
    }
}

$product->setPrice(Money::of('19.90', 'USD'));
$product->setPrices([
    ['quantity' => 1, 'price' => Money::of('0.02', 'USD')],
    ['quantity' => 1000, 'price' => Money::of('0.0123', 'USD')],
]);
$product->fill(['moq' => 10, 'order_multiple' => 10]);
$product->getTotalForN(2000);   // Money 24.60
```

### Cart

```php
$cart = Cart::of($user);            // or Cart::guest($token), or Cart::current()
$line = $cart->add($product, 10);
$cart->update($line, 20);           // the unit price and total follow the breaks
$cart->remove($line);
$cart->items();                     // lines of the configured class
$cart->count();                     // lines
$cart->quantity();                  // pieces
$cart->total();                     // ?Money

Cart::guest($token)->moveTo(Cart::of($user));   // after sign in, quantities add up
```

A guest cart is known by a token in the session (`Cart::SESSION_KEY`). Session data survives the
regeneration on sign in, so a `Login` listener can move it:
`Cart::guest(session(Cart::SESSION_KEY))->moveTo(Cart::of($event->user))`.

### Checkout

`checkout($fields)` places an order of all lines in a transaction: the order gets state `new`,
the user, the fields given, a number and the total; every line is copied into an order item with
all fields both classes have; the cart is emptied. `OrderPlaced` (order id, user id) is
dispatched after the commit, a good place for notifications. An empty cart throws
`DomainException`.

The number is the `order_number_prefix` config value, the date and the order id padded to four
digits. Override `Order::makeNumber()` in a subclass for another format.

### Own fields and subclasses

Fields added from the admin of the core are copied from cart lines to order items when both
classes have them. Fields from code go to subclasses set in the config:

```php
// config/mutable-content-shop.php
'models' => [
    'order' => App\Models\Order::class,
    'order_item' => App\Models\OrderItem::class,
    'cart_item' => App\Models\CartItem::class,
],
```

```php
class CartItem extends \Amarenkov\MutableContentShop\Models\CartItem
{
    #[CFAType(Type::TYPE_STRING), CFALabel('SKU')]
    public const FIELD_SKU = 'sku';

    // unit prices with more digits than the currency has
    #[CFAType(Type::TYPE_MONEY), CFALabel('Unit price'), CFATypeSettings([TypeSettings::DECIMALS => 6])]
    public const FIELD_UNIT_PRICE = 'unit_price';

    protected static array|bool|null $fieldDefinitions = null;
}
```

The package registers the configured classes and its LOVs in the core registries. Columns for
sorting and search of own fields go to a migration of the application:
`Schema::table('shop.cart_items', fn ($table) => $table->fieldExtract('sku')->type('varchar(64)'))`.

Access checks (who sees which order) belong to the application; `Order::ownedBy($user)` and
`Cart::of($user)->query()` are the scopes to build them on.

## Translations

Strings are in `lang/{en,ru}/shop.php` under the `mutable-content-shop` namespace, Russian field
and LOV labels in `lang/ru.json`. Publish them to override:

```bash
php artisan vendor:publish --tag=mutable-content-shop-lang
```

## License

MIT. See [LICENSE](LICENSE).
