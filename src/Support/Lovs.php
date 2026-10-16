<?php

namespace Amarenkov\MutableContentShop\Support;

use Amarenkov\MutableContentShop\Lovs\OrderItemState;
use Amarenkov\MutableContentShop\Lovs\OrderState;

/**
 * State LOV classes: the package ones or the application subclasses from the config with more items.
 */
class Lovs
{
    // static
    /**
     * @return class-string<OrderState>
     */
    public static function orderState(): string
    {
        return config('mutable-content-shop.lovs.order_state') ?? OrderState::class;
    }

    /**
     * @return class-string<OrderItemState>
     */
    public static function orderItemState(): string
    {
        return config('mutable-content-shop.lovs.order_item_state') ?? OrderItemState::class;
    }
}
