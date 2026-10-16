<?php

return [
    'user_model' => env('MUTABLE_CONTENT_SHOP_USER_MODEL'),

    'models' => [
        'order' => Amarenkov\MutableContentShop\Models\Order::class,
        'order_item' => Amarenkov\MutableContentShop\Models\OrderItem::class,
        'cart_item' => Amarenkov\MutableContentShop\Models\CartItem::class,
        'invoice' => Amarenkov\MutableContentShop\Models\Invoice::class,
    ],

    'lovs' => [
        'order_state' => Amarenkov\MutableContentShop\Lovs\OrderState::class,
        'order_item_state' => Amarenkov\MutableContentShop\Lovs\OrderItemState::class,
    ],

    'invoices_disk' => env('MUTABLE_CONTENT_SHOP_INVOICES_DISK', 'local'),

    'order_number_prefix' => env('MUTABLE_CONTENT_SHOP_ORDER_NUMBER_PREFIX', ''),
];
