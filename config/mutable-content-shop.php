<?php

return [
    'user_model' => env('MUTABLE_CONTENT_SHOP_USER_MODEL'),

    'models' => [
        'order' => Amarenkov\MutableContentShop\Models\Order::class,
        'order_item' => Amarenkov\MutableContentShop\Models\OrderItem::class,
        'cart_item' => Amarenkov\MutableContentShop\Models\CartItem::class,
    ],

    'order_number_prefix' => env('MUTABLE_CONTENT_SHOP_ORDER_NUMBER_PREFIX', ''),
];
