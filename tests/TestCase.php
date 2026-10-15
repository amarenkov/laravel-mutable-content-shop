<?php

namespace Amarenkov\MutableContentShop\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Orchestra\Testbench\TestCase as BaseTestCase;

use Amarenkov\MutableContent\Database\Seeders\FieldsSeeder;
use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;
use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\MutableContentServiceProvider;

use Amarenkov\MutableContentShop\MutableContentShopServiceProvider;

use Amarenkov\MutableContentShop\Tests\Fixtures\Models\User;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected static bool $databaseReady = false;

    protected User $user;

    protected function getPackageProviders($app): array
    {
        return [
            MutableContentServiceProvider::class,
            MutableContentShopServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('database.default', env('DB_CONNECTION', 'pgsql'));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function setUpTraits()
    {
        if (!static::$databaseReady) {
            $this->prepareDatabase();

            static::$databaseReady = true;
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        ModelWithFields::flushFieldDefinitions();

        $this->user = User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => 'secret']);

        $this->actingAs($this->user);
    }

    protected function prepareDatabase(): void
    {
        if (DatabaseHelper::isMariaDb()) {
            foreach (DB::connection()->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
        } else {
            foreach (['public', 'logs', 'shop', 'fixtures'] as $schema) {
                DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
            }

            DB::statement('CREATE SCHEMA public');
        }

        Artisan::call('migrate', [
            '--path' => [
                realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel/migrations'),
                realpath(__DIR__.'/../vendor/amarenkov/laravel-mutable-content/database/migrations'),
                realpath(__DIR__.'/../database/migrations'),
                realpath(__DIR__.'/Fixtures/Migrations'),
            ],
            '--realpath' => true,
        ]);

        $this->seed(LovsSeeder::class);
        $this->seed(FieldsSeeder::class);

        ModelWithFields::flushFieldDefinitions();
    }
}
