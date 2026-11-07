<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(DatabaseHelper::tableName('shop.cart_items'), function (Blueprint $table) {
            $table->index('updated_at', 'shop_cart_items_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table(DatabaseHelper::tableName('shop.cart_items'), function (Blueprint $table) {
            $table->dropIndex('shop_cart_items_updated_at_index');
        });
    }
};
