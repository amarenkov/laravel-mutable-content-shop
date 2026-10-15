<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createSchema('fixtures');

        Schema::create(DatabaseHelper::tableName('fixtures.products'), function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
            $table->softDeletes();
        });
    }
};
