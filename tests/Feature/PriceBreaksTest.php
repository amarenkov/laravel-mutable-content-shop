<?php

namespace Amarenkov\MutableContentShop\Tests\Feature;

use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentShop\Tests\Fixtures\Models\Product;
use Amarenkov\MutableContentShop\Tests\TestCase;

class PriceBreaksTest extends TestCase
{
    public function test_price_of_the_last_break_not_above_the_quantity(): void
    {
        $product = new Product();
        $product->setPrices([
            ['quantity' => 100, 'price' => Money::of('0.012345', Currency::USD)],
            ['quantity' => 1, 'price' => Money::of('0.02', Currency::USD)],
            ['quantity' => 10, 'price' => Money::of('0.015', Currency::USD)],
        ]);

        $this->assertSame([1, 10, 100], array_column($product->getPrices(), 'quantity'));
        $this->assertNull($product->getPriceForN(0));
        $this->assertSame('0.02', $product->getPriceForN(9)->amount);
        $this->assertSame('0.015', $product->getPriceForN(10)->amount);
        $this->assertSame('0.012345', $product->getPriceForN(1000)->amount);
        $this->assertSame('12.35', $product->getTotalForN(1000)->amount);
    }

    public function test_minimum_order_quantity_and_multiple(): void
    {
        $product = new Product();
        $product->setPrice(Money::of('1.50', Currency::USD));
        $product->fill([Product::FIELD_MOQ => 10, Product::FIELD_ORDER_MULTIPLE => 5]);

        $this->assertFalse($product->isQuantityAllowed(5));
        $this->assertFalse($product->isQuantityAllowed(12));
        $this->assertTrue($product->isQuantityAllowed(15));
        $this->assertNull($product->getPriceForN(12));
        $this->assertSame('22.5', $product->getTotalForN(15)->amount);
    }

    public function test_no_price(): void
    {
        $product = new Product();
        $product->setPrice(null);

        $this->assertFalse($product->hasPrices());
        $this->assertNull($product->getTotalForN(1));
    }
}
