<?php

namespace Amarenkov\MutableContentShop\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order was placed from a cart; the user is null for a guest.
 */
final readonly class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    // public
    public function __construct(
        public int $orderId,
        public ?int $userId,
    )
    {
    }
}
