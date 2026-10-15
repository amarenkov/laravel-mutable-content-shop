<?php

namespace Amarenkov\MutableContentShop\Contracts;

/**
 * Something a cart line can be made of: a product, an offer, a search result pointing at an offer.
 */
interface Sellable
{
    /**
     * Morph type of the model a line refers to; lines of the same type and id are one line.
     */
    public function sellableType(): string;

    public function sellableId(): int;

    /**
     * Field values a new line starts with: the name, price breaks and any field the line class has.
     *
     * @return array<string, mixed>
     */
    public function lineFields(): array;
}
