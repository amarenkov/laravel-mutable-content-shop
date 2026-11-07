<?php

namespace Amarenkov\MutableContentShop\Concerns;

/**
 * Type and id of a Sellable model; lineFields() is left to the model.
 */
trait IsSellable
{
    // public
    public function sellableType(): string
    {
        return $this->getMorphClass();
    }

    public function sellableId(): int
    {
        return (int)$this->getKey();
    }
}
