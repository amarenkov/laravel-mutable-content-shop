# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `CartItem` (a line of the cart of a user or of a guest by a session token), `Order` (number, state of the `order_state` LOV, customer, contacts, comment, `money` total, `ownedBy()`, `makeNumber()`, `itemsTotal()`), `OrderItem` (a copy of the cart line with a state of the `order_item_state` LOV); states `new`, `paid`, `done`, `cancelled`.
- `Concerns\HasPriceBreaks` (price breaks, minimum order quantity, order multiple, `setPrice()`, `setPrices()`, `isQuantityAllowed()`, `getPriceForN()`, `getTotalForN()` rounded to the currency) and `Concerns\PricedLine` (what is sold, name, quantity, unit price, total, `setQuantity()`, `lineFields()`, `fillLine()`).
- `Contracts\Sellable` for anything that goes to a cart.
- Service `Cart` (`of()`, `guest()`, `current()`, `add()`, `update()`, `remove()`, `clear()`, `count()`, `quantity()`, `total()`, `moveTo()`, `checkout()`) and the `OrderPlaced` event dispatched after commit.
- Model classes from the config (`models.order`, `models.order_item`, `models.cart_item`), so an application adds its own fields in subclasses; fields both line classes have are copied from a cart line to an order item.
- Migration in the `shop` schema (publish tag `mutable-content-shop-migrations`), LOVs and classes registered in the core registries, Russian labels.

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content-shop/commits/main
