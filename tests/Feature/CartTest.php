<?php

namespace Amarenkov\MutableContentShop\Tests\Feature;

use DomainException;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentShop\Contracts\Sellable;
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
    public function test_current_or_null_gives_no_token_to_a_guest_without_a_cart(): void
    {
        $this->assertSame($this->user->id, Cart::currentOrNull()->userId());

        Auth::logout();

        $this->assertNull(Cart::currentOrNull());
        $this->assertFalse(Session::has(Cart::SESSION_KEY));

        $token = Cart::current()->token();

        $this->assertSame($token, Cart::currentOrNull()->token());
    }

    public function test_guest_cart_moves_to_the_user_on_login(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        Cart::of($this->user)->add($product, 1);

        Auth::logout();

        Cart::current()->add($product, 2);

        Auth::login($this->user);

        $this->assertFalse(Session::has(Cart::SESSION_KEY));
        $this->assertSame(1, CartItem::count());
        $this->assertSame(3, Cart::of($this->user)->quantity());
    }

    public function test_moving_on_login_can_be_turned_off(): void
    {
        config(['mutable-content-shop.move_guest_cart_on_login' => false]);

        Auth::logout();

        $cart = Cart::current();
        $cart->add($this->makeProduct('Bolt', '2'), 2);

        Auth::login($this->user);

        $this->assertSame(0, Cart::of($this->user)->quantity());
        $this->assertSame(2, $cart->quantity());
    }

    public function test_login_without_guest_cart_changes_nothing(): void
    {
        Auth::logout();
        Auth::login($this->user);

        $this->assertSame(0, CartItem::count());
    }

    public function test_sync_makes_the_cart_hold_exactly_the_given_items(): void
    {
        $kept = $this->makeProduct('Kept', '2');
        $dropped = $this->makeProduct('Dropped', '3');
        $zero = $this->makeProduct('Zero', '4');
        $added = $this->makeProduct('Added', '5');

        $cart = Cart::of($this->user);
        $cart->add($kept, 1);
        $cart->add($dropped, 1);
        $cart->add($zero, 1);

        $cart->sync([
            ['item' => $kept, 'quantity' => 4],
            ['item' => $zero, 'quantity' => 0],
            ['item' => $added, 'quantity' => 2],
        ]);

        $lines = $cart->items();

        $this->assertCount(2, $lines);
        $this->assertTrue($lines[0]->isOf($kept));
        $this->assertSame(4, (int)$lines[0]->getQuantity());
        $this->assertTrue($lines[1]->isOf($added));
        $this->assertSame(2, (int)$lines[1]->getQuantity());
    }

    public function test_checkout_with_places_the_order_of_the_given_items(): void
    {
        $kept = $this->makeProduct('Kept', '2');
        $dropped = $this->makeProduct('Dropped', '3');

        $cart = Cart::of($this->user);
        $cart->add($kept, 1);
        $cart->add($dropped, 1);

        $order = $cart->checkoutWith([['item' => $kept, 'quantity' => 3]], ['phone' => '1']);

        $this->assertCount(1, $order->items);
        $this->assertTrue($order->getMoney(Order::FIELD_TOTAL)->equals(Money::of('6', Currency::USD)));
        $this->assertSame(0, $cart->count());
    }

    public function test_refused_checkout_with_leaves_the_cart_as_it_was(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        $cart = Cart::of($this->user);
        $cart->add($product, 2);

        try {
            $cart->checkoutWith([]);

            $this->fail('An empty order is refused');
        } catch (DomainException) {
        }

        $this->assertSame(2, $cart->quantity());
    }

    public function test_prune_deletes_only_old_guest_lines(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        Cart::of($this->user)->add($product, 1);
        Cart::guest('fresh')->add($product, 1);
        Cart::guest('old')->add($product, 1);
        Cart::guest('older')->add($product, 1);

        $table = DatabaseHelper::tableName('shop.cart_items');

        DB::table($table)->update(['updated_at' => now()->subDays(40)]);
        DB::table($table)->where('token', 'fresh')->update(['updated_at' => now()]);
        DB::table($table)->where('token', 'older')->update(['updated_at' => now()->subDays(100), 'deleted_at' => now()]);

        $this->artisan('shop:prune-carts')->assertSuccessful();

        $this->assertSame(2, DB::table($table)->count());
        $this->assertSame(1, Cart::of($this->user)->count());
        $this->assertSame(1, Cart::guest('fresh')->count());

        Cart::guest('recent')->add($product, 1);
        DB::table($table)->where('token', 'recent')->update(['updated_at' => now()->subDays(5)]);

        $this->artisan('shop:prune-carts', ['--days' => 1])->assertSuccessful();

        $this->assertSame(0, Cart::guest('recent')->count());
        $this->assertSame(1, Cart::guest('fresh')->count());
    }

    public function test_access_key_of_an_order(): void
    {
        $cart = Cart::of($this->user);

        $cart->add($this->makeProduct('Bolt', '2'), 1);
        $first = $cart->checkout();

        $cart->add($this->makeProduct('Nut', '2'), 1);
        $second = $cart->checkout();

        $this->assertTrue($first->isAccessKey($first->accessKey()));
        $this->assertFalse($first->isAccessKey($second->accessKey()));
        $this->assertFalse($first->isAccessKey(null));
        $this->assertFalse($first->isAccessKey(['x']));

        $key = $first->accessKey();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->app->forgetInstance('encrypter');

        $this->assertNotSame($key, $first->accessKey());
    }

    public function test_concurrent_add_ends_up_in_one_line(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        $cart = RacingCart::of(1);
        $cart->add($product, 2);

        $cart->missNextLine = true;
        $line = $cart->add($product, 3);

        $this->assertSame(1, $cart->count());
        $this->assertSame(5, (int)$line->getQuantity());
        $this->assertSame(5, $cart->quantity());
    }

    public function test_guest_and_user_lines_of_one_item_do_not_collide(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        Cart::of(1)->add($product, 1);
        Cart::of(2)->add($product, 1);
        Cart::guest('first')->add($product, 1);
        Cart::guest('second')->add($product, 1);

        $this->assertSame(4, CartItem::count());
    }

    public function test_unique_lines_migration_removes_duplicates(): void
    {
        $product = $this->makeProduct('Bolt', '2');

        $migration = require __DIR__.'/../../database/migrations/update_shop_tables_unique_cart_lines.php';
        $migration->down();

        Cart::of(1)->add($product, 1);

        $table = DatabaseHelper::tableName('shop.cart_items');
        $newest = DB::table($table)->insertGetId(['fields' => DB::table($table)->value('fields')]);

        Cart::guest('other')->add($product, 7);

        $migration->up();

        $this->assertSame([$newest], Cart::of(1)->items()->pluck('id')->all());
        $this->assertSame(7, Cart::guest('other')->quantity());
    }

    public function test_checkout_locks_the_lines(): void
    {
        $cart = Cart::of(1);
        $cart->add($this->makeProduct('Bolt', '2'), 1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $cart->checkout();

        $locks = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'for update'));

        DB::disableQueryLog();

        $this->assertNotEmpty($locks);
    }

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

class RacingCart extends Cart
{
    public bool $missNextLine = false;

    protected function findForUpdate(Sellable $item): ?CartItem
    {
        if ($this->missNextLine) {
            $this->missNextLine = false;

            return null;
        }

        return parent::findForUpdate($item);
    }
}
