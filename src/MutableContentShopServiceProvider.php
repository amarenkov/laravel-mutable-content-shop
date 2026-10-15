<?php

namespace Amarenkov\MutableContentShop;

use Illuminate\Support\ServiceProvider;

use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContentShop\Lovs\OrderItemState;
use Amarenkov\MutableContentShop\Lovs\OrderState;
use Amarenkov\MutableContentShop\Support\Models;

class MutableContentShopServiceProvider extends ServiceProvider
{
    // public
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mutable-content-shop.php', 'mutable-content-shop');

        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->callAfterResolving(MutableClassRegistry::class, function (MutableClassRegistry $registry) {
            $registry
                ->add(Models::order())
                ->add(Models::orderItem())
                ->add(Models::cartItem());
        });

        $this->callAfterResolving(LovRegistry::class, function (LovRegistry $registry) {
            $registry
                ->addClass(OrderState::class)
                ->addClass(OrderItemState::class);
        });
    }

    public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'mutable-content-shop-migrations');

        $this->publishes([
            __DIR__.'/../config/mutable-content-shop.php' => config_path('mutable-content-shop.php'),
        ], 'mutable-content-shop-config');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mutable-content-shop');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/mutable-content-shop'),
        ], 'mutable-content-shop-lang');
    }
}
