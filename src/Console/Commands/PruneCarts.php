<?php

namespace Amarenkov\MutableContentShop\Console\Commands;

use Illuminate\Console\Command;

use Amarenkov\MutableContentShop\Models\CartItem;
use Amarenkov\MutableContentShop\Support\Models;

class PruneCarts extends Command
{
    // protected
    protected $signature = 'shop:prune-carts {--days= : Days to keep abandoned guest carts, the guest_cart_days config value by default}';

    protected $description = 'Delete abandoned guest carts';

    // public
    public function handle(): int
    {
        $days = (int)($this->option('days') ?? config('mutable-content-shop.guest_cart_days'));

        $count = Models::cartItem()::withTrashed()
            ->whereNull(CartItem::FIELD_USER_ID)
            ->where('updated_at', '<', now()->subDays($days))
            ->forceDelete();

        $this->info('Deleted cart lines: '.$count);

        return self::SUCCESS;
    }
}
