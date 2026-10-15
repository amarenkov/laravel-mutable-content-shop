<?php

namespace Amarenkov\MutableContentShop\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;

use Amarenkov\MutableContentShop\Concerns\PricedLine;
use Amarenkov\MutableContentShop\Lovs\OrderItemState;
use Amarenkov\MutableContentShop\Support\Models;

/**
 * A line of an order: a copy of the cart line it was made of, with its own state.
 */
#[Table('shop.order_items')]
#[ClassLabel('Order item')]
class OrderItem extends ModelWithFields
{
    use PricedLine;

    // const
    #[CFAType(Type::TYPE_OBJECT), CFALabel('Order'), CFAObjectClass(Order::class), CFAIsRequired, CFAIsImmutable]
    public const FIELD_ORDER_ID = 'order_id';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('State'), CFAIsRequired, CFALovCode(OrderItemState::CLASS_CODE)]
    public const FIELD_STATE = 'state';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Models::order(), self::FIELD_ORDER_ID);
    }
}
