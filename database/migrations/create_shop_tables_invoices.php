<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContentShop\Models\Invoice;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createWithLog('shop.invoices', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();

            $table->fieldExtract(Invoice::FIELD_ORDER_ID)->type('bigint');
            $table->fieldExtract(Invoice::FIELD_NUMBER)->type('varchar(50)');
            $table->fieldExtract(Invoice::FIELD_PAYMENT_STATE)->type('varchar(50)');

            $table->index(Invoice::FIELD_ORDER_ID);
            $table->index(Invoice::FIELD_NUMBER);
            $table->index(Invoice::FIELD_PAYMENT_STATE);
        });
    }

    public function down(): void
    {
        Schema::dropIfExistsWithLog('shop.invoices');
    }
};
