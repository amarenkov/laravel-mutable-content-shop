<?php

namespace Amarenkov\MutableContentShop\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContentShop\Lovs\OrderState;
use Amarenkov\MutableContentShop\Support\Models;

/**
 * An order of a user or a guest: contacts, state, number and the total of its items.
 */
#[Table('shop.orders')]
#[ClassLabel('Order')]
class Order extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('Number')]
    public const FIELD_NUMBER = 'number';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('State'), CFAIsRequired, CFALovCode(OrderState::CLASS_CODE)]
    public const FIELD_STATE = 'state';

    #[CFAType(Type::TYPE_INT), CFALabel('Customer')]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('First name')]
    public const FIELD_FIRST_NAME = 'first_name';

    #[CFAType(Type::TYPE_STRING), CFALabel('Last name')]
    public const FIELD_LAST_NAME = 'last_name';

    #[CFAType(Type::TYPE_STRING), CFALabel('Company')]
    public const FIELD_COMPANY = 'company';

    #[CFAType(Type::TYPE_STRING), CFALabel('Phone')]
    public const FIELD_PHONE = 'phone';

    #[CFAType(Type::TYPE_STRING), CFALabel('Email')]
    public const FIELD_EMAIL = 'email';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Comment')]
    public const FIELD_COMMENT = 'comment';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Total'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_TOTAL = 'total';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Models::user(), self::FIELD_USER_ID);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Models::orderItem(), OrderItem::FIELD_ORDER_ID);
    }

    /**
     * Prefix from the config, the date of the order and its id padded to four digits.
     */
    public function makeNumber(): string
    {
        return config('mutable-content-shop.order_number_prefix').($this->created_at ?? now())->format('Ymd').str_pad((string)$this->getKey(), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Sum of the item totals; items without a total are not counted.
     */
    public function itemsTotal(): ?Money
    {
        $result = null;

        foreach ($this->items()->get() as $item) {
            $total = $item->getMoney(OrderItem::FIELD_TOTAL);

            if ($total) {
                $result = $result ? $result->add($total) : $total;
            }
        }

        return $result;
    }
}
