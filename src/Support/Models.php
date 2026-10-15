<?php

namespace Amarenkov\MutableContentShop\Support;

use Amarenkov\MutableContentShop\Models\CartItem;
use Amarenkov\MutableContentShop\Models\Order;
use Amarenkov\MutableContentShop\Models\OrderItem;

/**
 * Model classes the package works with: its own or the application subclasses from the config.
 */
class Models
{
    // static
    /**
     * @return class-string<Order>
     */
    public static function order(): string
    {
        return config('mutable-content-shop.models.order') ?? Order::class;
    }

    /**
     * @return class-string<OrderItem>
     */
    public static function orderItem(): string
    {
        return config('mutable-content-shop.models.order_item') ?? OrderItem::class;
    }

    /**
     * @return class-string<CartItem>
     */
    public static function cartItem(): string
    {
        return config('mutable-content-shop.models.cart_item') ?? CartItem::class;
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function user(): string
    {
        return config('mutable-content-shop.user_model') ?? config('auth.providers.users.model');
    }
}
