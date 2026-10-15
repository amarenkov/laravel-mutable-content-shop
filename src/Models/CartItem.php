<?php

namespace Amarenkov\MutableContentShop\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;

use Amarenkov\MutableContentShop\Concerns\PricedLine;
use Amarenkov\MutableContentShop\Support\Models;

/**
 * A line of the cart of a user or of a guest, the guest known by a token kept in the session.
 */
#[Table('shop.cart_items')]
#[ClassLabel('Cart item')]
class CartItem extends ModelWithFields
{
    use PricedLine;

    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Customer')]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Guest token')]
    public const FIELD_TOKEN = 'token';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function user(): BelongsTo
    {
        return $this->belongsTo(Models::user(), self::FIELD_USER_ID);
    }
}
