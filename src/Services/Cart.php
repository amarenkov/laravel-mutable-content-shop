<?php

namespace Amarenkov\MutableContentShop\Services;

use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentShop\Contracts\Sellable;
use Amarenkov\MutableContentShop\Events\OrderPlaced;
use Amarenkov\MutableContentShop\Lovs\OrderItemState;
use Amarenkov\MutableContentShop\Lovs\OrderState;
use Amarenkov\MutableContentShop\Models\CartItem;
use Amarenkov\MutableContentShop\Models\Order;
use Amarenkov\MutableContentShop\Models\OrderItem;
use Amarenkov\MutableContentShop\Support\Models;

/**
 * The cart of a user or of a guest: lines, totals and checkout into an order.
 */
class Cart
{
    // const
    public const SESSION_KEY = 'mutable-content-shop.cart_token';

    // static
    public static function of(Authenticatable|int $user): static
    {
        return new static($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user, null);
    }

    public static function guest(string $token): static
    {
        return new static(null, $token);
    }

    /**
     * The cart of the signed in user, otherwise of the guest by the token in the session, created on the first call.
     */
    public static function current(): static
    {
        if (Auth::check()) {
            return static::of(Auth::user());
        }

        $token = Session::get(self::SESSION_KEY);

        if (!$token) {
            $token = Str::random(40);

            Session::put(self::SESSION_KEY, $token);
        }

        return static::guest($token);
    }

    // protected
    final protected function __construct(
        protected ?int $userId,
        protected ?string $token,
    )
    {
    }

    protected function prepareLog(ModelWithFields $model): void
    {
        if ($this->userId !== null) {
            $model->withLogContext()->user($this->userId);
        }
    }

    protected function ensureOwn(CartItem $line): void
    {
        $own = $this->userId !== null
            ? (string)$line->getField(CartItem::FIELD_USER_ID) === (string)$this->userId
            : $line->getField(CartItem::FIELD_TOKEN) === $this->token;

        if (!$own) {
            throw new DomainException(__('mutable-content-shop::shop.not_in_cart'));
        }
    }

    // public
    public function userId(): ?int
    {
        return $this->userId;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    /**
     * @return Builder<CartItem>
     */
    public function query(): Builder
    {
        $class = Models::cartItem();

        return $this->userId !== null
            ? $class::query()->whereField(CartItem::FIELD_USER_ID, $this->userId)
            : $class::query()->whereField(CartItem::FIELD_TOKEN, $this->token);
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function items(): Collection
    {
        return $this->query()->orderBy('id')->get();
    }

    public function find(Sellable $item): ?CartItem
    {
        return $this->query()
            ->whereField(CartItem::FIELD_SELLABLE_TYPE, $item->sellableType())
            ->whereField(CartItem::FIELD_SELLABLE_ID, $item->sellableId())
            ->first();
    }

    /**
     * Adds the quantity to the line of the item, a new line takes its fields from the item.
     */
    public function add(Sellable $item, int $quantity): CartItem
    {
        $line = $this->find($item);

        if ($line) {
            $line->setQuantity((int)$line->getQuantity() + $quantity);
        } else {
            $class = Models::cartItem();

            $line = new $class();
            $line->fillLine($item->lineFields());
            $line->fill([
                CartItem::FIELD_USER_ID => $this->userId,
                CartItem::FIELD_TOKEN => $this->userId === null ? $this->token : null,
                CartItem::FIELD_SELLABLE_TYPE => $item->sellableType(),
                CartItem::FIELD_SELLABLE_ID => $item->sellableId(),
            ]);
            $line->setQuantity($quantity);
        }

        $line->save();

        return $line;
    }

    public function update(CartItem $line, int|string|null $quantity): CartItem
    {
        $this->ensureOwn($line);

        $line->setQuantity($quantity);
        $line->save();

        return $line;
    }

    public function remove(CartItem $line): void
    {
        $this->ensureOwn($line);

        $line->forceDelete();
    }

    public function clear(): void
    {
        $this->query()->forceDelete();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function quantity(): int
    {
        return (int)$this->items()->sum(fn (CartItem $line) => (int)$line->getQuantity());
    }

    /**
     * Sum of the line totals; lines without a total (no price for the quantity) are not counted.
     */
    public function total(): ?Money
    {
        $result = null;

        foreach ($this->items() as $line) {
            $total = $line->getMoney(CartItem::FIELD_TOTAL);

            if ($total) {
                $result = $result ? $result->add($total) : $total;
            }
        }

        return $result;
    }

    /**
     * Moves the lines to another cart, a guest cart to the user's after sign in; quantities of the same item add up.
     */
    public function moveTo(Cart $target): void
    {
        DB::transaction(function () use ($target) {
            foreach ($this->items() as $line) {
                $existing = $target->query()
                    ->whereField(CartItem::FIELD_SELLABLE_TYPE, $line->getField(CartItem::FIELD_SELLABLE_TYPE))
                    ->whereField(CartItem::FIELD_SELLABLE_ID, $line->getField(CartItem::FIELD_SELLABLE_ID))
                    ->first();

                if ($existing) {
                    $existing->setQuantity((int)$existing->getQuantity() + (int)$line->getQuantity());
                    $existing->save();

                    $line->forceDelete();
                } else {
                    $line->fill([
                        CartItem::FIELD_USER_ID => $target->userId(),
                        CartItem::FIELD_TOKEN => $target->userId() === null ? $target->token() : null,
                    ]);
                    $line->save();
                }
            }
        });
    }

    /**
     * Places an order of all lines and empties the cart. The fields are the order ones: contacts, comment and
     * the ones added by the application.
     *
     * @param array<string, mixed> $fields
     */
    public function checkout(array $fields = []): Order
    {
        return DB::transaction(function () use ($fields) {
            $lines = $this->items();

            if ($lines->isEmpty()) {
                throw new DomainException(__('mutable-content-shop::shop.cart_empty'));
            }

            $orderClass = Models::order();
            $itemClass = Models::orderItem();

            $order = new $orderClass();
            $order->fill([
                Order::FIELD_STATE => OrderState::NEW,
                Order::FIELD_USER_ID => $this->userId,
            ] + $fields);
            $this->prepareLog($order);
            $order->save();

            foreach ($lines as $line) {
                $item = new $itemClass();
                $item->fillLine($line->lineFields());
                $item->fill([
                    OrderItem::FIELD_ORDER_ID => $order->id,
                    OrderItem::FIELD_STATE => OrderItemState::NEW,
                ]);
                $this->prepareLog($item);
                $item->save();
            }

            $order->fill([
                Order::FIELD_NUMBER => $order->getField(Order::FIELD_NUMBER) ?? $order->makeNumber(),
                Order::FIELD_TOTAL => $order->itemsTotal(),
            ]);
            $this->prepareLog($order);
            $order->save();

            $this->clear();

            event(new OrderPlaced($order->id, $this->userId));

            return $order;
        });
    }
}
