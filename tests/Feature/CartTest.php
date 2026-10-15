<?php

namespace Amarenkov\MutableContentShop\Tests\Feature;

use DomainException;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentShop\Events\OrderPlaced;
use Amarenkov\MutableContentShop\Lovs\OrderItemState;
use Amarenkov\MutableContentShop\Lovs\OrderState;
use Amarenkov\MutableContentShop\Models\CartItem;
use Amarenkov\MutableContentShop\Models\Order;
use Amarenkov\MutableContentShop\Models\OrderItem;
use Amarenkov\MutableContentShop\Services\Cart;

use Amarenkov\MutableContentShop\Tests\Fixtures\Models\Product;
use Amarenkov\MutableContentShop\Tests\Fixtures\Models\SkuCartItem;
use Amarenkov\MutableContentShop\Tests\Fixtures\Models\SkuOrderItem;
use Amarenkov\MutableContentShop\Tests\TestCase;

class CartTest extends TestCase
{
    protected function makeProduct(string $label, string $price, ?string $sku = null): Product
    {
        $product = new Product();
        $product->fill(['label' => $label, Product::FIELD_SKU => $sku]);
        $product->setPrice(Money::of($price, Currency::USD));
        $product->save();

        return $product;
    }

    public function test_lines_of_the_same_item_add_up(): void
    {
        $cart = Cart::of($this->user);
        $pen = $this->makeProduct('Pen', '1.25');
        $pad = $this->makeProduct('Pad', '3');

        $cart->add($pen, 2);
        $line = $cart->add($pen, 3);
        $cart->add($pad, 1);

        $this->assertSame(2, $cart->count());
        $this->assertSame(6, $cart->quantity());
        $this->assertSame('Pen', $line->getField(CartItem::FIELD_NAME));
        $this->assertSame(5, $line->getQuantity());
        $this->assertSame('1.25', $line->getMoney(CartItem::FIELD_UNIT_PRICE)->amount);
        $this->assertSame('6.25', $line->getMoney(CartItem::FIELD_TOTAL)->amount);
        $this->assertSame('9.25', $cart->total()->amount);
        $this->assertTrue($line->sellable->is($pen));
    }

    public function test_update_and_remove(): void
    {
        $cart = Cart::of($this->user);
        $line = $cart->add($this->makeProduct('Pen', '1.25'), 1);

        $cart->update($line, 4);
        $this->assertSame('5', $cart->total()->amount);

        $cart->remove($line);
        $this->assertSame(0, $cart->count());
        $this->assertNull($cart->total());
    }

    public function test_lines_of_another_cart_are_refused(): void
    {
        $line = Cart::guest('another')->add($this->makeProduct('Pen', '1'), 1);

        $this->expectException(DomainException::class);

        Cart::of($this->user)->update($line, 2);
    }

    public function test_guest_cart_by_session_token_moves_to_the_user(): void
    {
        Auth::logout();

        $pen = $this->makeProduct('Pen', '1');
        $guest = Cart::current();
        $guest->add($pen, 2);
        $guest->add($this->makeProduct('Pad', '2'), 1);

        $this->assertSame($guest->token(), Session::get(Cart::SESSION_KEY));
        $this->assertSame(2, Cart::current()->count());

        $userCart = Cart::of($this->user);
        $userCart->add($pen, 1);

        $guest->moveTo($userCart);

        $this->assertSame(0, $guest->count());
        $this->assertSame(2, $userCart->count());
        $this->assertSame(4, $userCart->quantity());
    }

    public function test_checkout_places_an_order_and_empties_the_cart(): void
    {
        Event::fake([OrderPlaced::class]);
        config(['mutable-content-shop.order_number_prefix' => 'S']);

        $cart = Cart::of($this->user);
        $cart->add($this->makeProduct('Pen', '1.25'), 2);
        $cart->add($this->makeProduct('Pad', '3'), 1);

        $order = $cart->checkout([Order::FIELD_PHONE => '+100', Order::FIELD_COMMENT => 'Call first']);

        $this->assertSame(OrderState::NEW, $order->getField(Order::FIELD_STATE));
        $this->assertSame($this->user->id, (int)$order->getField(Order::FIELD_USER_ID));
        $this->assertSame('+100', $order->getField(Order::FIELD_PHONE));
        $this->assertSame('5.5', $order->getMoney(Order::FIELD_TOTAL)->amount);
        $this->assertSame('S'.now()->format('Ymd').str_pad((string)$order->id, 4, '0', STR_PAD_LEFT), $order->getField(Order::FIELD_NUMBER));
        $this->assertSame(1, Order::query()->ownedBy($this->user)->count());

        $items = $order->items()->orderBy('id')->get();
        $this->assertSame(['Pen', 'Pad'], $items->map(fn (OrderItem $item) => $item->getField(OrderItem::FIELD_NAME))->all());
        $this->assertSame(OrderItemState::NEW, $items[0]->getField(OrderItem::FIELD_STATE));
        $this->assertSame('2.5', $items[0]->getMoney(OrderItem::FIELD_TOTAL)->amount);
        $this->assertSame(1, $items[0]->getPrices()[0]['quantity']);

        $this->assertSame(0, $cart->count());
        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->orderId === $order->id && $event->userId === $this->user->id);
    }

    public function test_guest_checkout(): void
    {
        $cart = Cart::guest('guest');
        $cart->add($this->makeProduct('Pen', '1'), 1);

        $order = $cart->checkout([Order::FIELD_EMAIL => 'guest@example.com']);

        $this->assertNull($order->getField(Order::FIELD_USER_ID));
        $this->assertSame('guest@example.com', $order->getField(Order::FIELD_EMAIL));
    }

    public function test_empty_cart_cannot_be_checked_out(): void
    {
        $this->expectException(DomainException::class);

        Cart::of($this->user)->checkout();
    }

    public function test_application_line_classes_with_own_fields(): void
    {
        config([
            'mutable-content-shop.models.cart_item' => SkuCartItem::class,
            'mutable-content-shop.models.order_item' => SkuOrderItem::class,
        ]);

        $product = new Product();
        $product->fill(['label' => 'Resistor', Product::FIELD_SKU => 'R-10K']);
        $product->setPrice(Money::of('0.012345', Currency::USD));
        $product->save();

        $cart = Cart::of($this->user);
        $line = $cart->add($product, 1000);

        $this->assertInstanceOf(SkuCartItem::class, $line);
        $this->assertSame('R-10K', $line->getField(SkuCartItem::FIELD_SKU));
        $this->assertSame('0.012345', $line->fresh()->getMoney(SkuCartItem::FIELD_UNIT_PRICE)->amount);

        $order = $cart->checkout();
        $item = $order->items()->first();

        $this->assertInstanceOf(SkuOrderItem::class, $item);
        $this->assertSame('R-10K', $item->getField(SkuOrderItem::FIELD_SKU));
        $this->assertSame('0.012345', $item->getMoney(SkuOrderItem::FIELD_UNIT_PRICE)->amount);
        $this->assertSame('12.35', $order->getMoney(Order::FIELD_TOTAL)->amount);
    }
}
