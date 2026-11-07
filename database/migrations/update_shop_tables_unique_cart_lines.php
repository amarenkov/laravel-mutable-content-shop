<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

use Amarenkov\MutableContentShop\Models\CartItem;

return new class extends Migration
{
    // const
    protected const KEY = [CartItem::FIELD_USER_ID, CartItem::FIELD_TOKEN, CartItem::FIELD_SELLABLE_TYPE, CartItem::FIELD_SELLABLE_ID];

    // public
    public function up(): void
    {
        $table = DatabaseHelper::tableName('shop.cart_items');

        $duplicates = DB::table($table)
            ->select(self::KEY)
            ->selectRaw('max(id) as newest_id')
            ->groupBy(self::KEY)
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::table($table)->where('id', '<', $duplicate->newest_id);

            foreach (self::KEY as $column) {
                $query->where($column, $duplicate->$column);
            }

            $query->delete();
        }

        Schema::table($table, function (Blueprint $table) {
            $table->unique([CartItem::FIELD_USER_ID, CartItem::FIELD_SELLABLE_TYPE, CartItem::FIELD_SELLABLE_ID], 'shop_cart_items_user_line_unique');
            $table->unique([CartItem::FIELD_TOKEN, CartItem::FIELD_SELLABLE_TYPE, CartItem::FIELD_SELLABLE_ID], 'shop_cart_items_token_line_unique');
        });
    }

    public function down(): void
    {
        Schema::table(DatabaseHelper::tableName('shop.cart_items'), function (Blueprint $table) {
            $table->dropUnique('shop_cart_items_user_line_unique');
            $table->dropUnique('shop_cart_items_token_line_unique');
        });
    }
};
