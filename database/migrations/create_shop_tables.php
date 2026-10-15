<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

use Amarenkov\MutableContentShop\Models\CartItem;
use Amarenkov\MutableContentShop\Models\Order;
use Amarenkov\MutableContentShop\Models\OrderItem;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createSchema('shop');

        Schema::createWithLog('shop.orders', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Order::FIELD_NUMBER)->type('varchar(50)');
            $table->fieldExtract(Order::FIELD_STATE)->type('varchar(50)');
            $table->fieldExtract(Order::FIELD_USER_ID)->type('bigint');

            $table->index(Order::FIELD_NUMBER);
            $table->index(Order::FIELD_STATE);
            $table->index(Order::FIELD_USER_ID);
        });

        Schema::createWithLog('shop.order_items', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(OrderItem::FIELD_ORDER_ID)->type('bigint');
            $table->fieldExtract(OrderItem::FIELD_SELLABLE_TYPE)->type('varchar(255)');
            $table->fieldExtract(OrderItem::FIELD_SELLABLE_ID)->type('bigint');

            $table->index(OrderItem::FIELD_ORDER_ID);
            $table->index([OrderItem::FIELD_SELLABLE_TYPE, OrderItem::FIELD_SELLABLE_ID]);
        });

        Schema::create(DatabaseHelper::tableName('shop.cart_items'), function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(CartItem::FIELD_USER_ID)->type('bigint');
            $table->fieldExtract(CartItem::FIELD_TOKEN)->type('varchar(64)');
            $table->fieldExtract(CartItem::FIELD_SELLABLE_TYPE)->type('varchar(255)');
            $table->fieldExtract(CartItem::FIELD_SELLABLE_ID)->type('bigint');

            $table->index(CartItem::FIELD_USER_ID);
            $table->index(CartItem::FIELD_TOKEN);
            $table->index([CartItem::FIELD_SELLABLE_TYPE, CartItem::FIELD_SELLABLE_ID]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DatabaseHelper::tableName('shop.cart_items'));
        Schema::dropIfExistsWithLog('shop.order_items');
        Schema::dropIfExistsWithLog('shop.orders');
    }
};
