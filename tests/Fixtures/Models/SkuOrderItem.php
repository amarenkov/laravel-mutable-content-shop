<?php

namespace Amarenkov\MutableContentShop\Tests\Fixtures\Models;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContentShop\Models\OrderItem;

#[ClassLabel('SKU order item')]
class SkuOrderItem extends OrderItem
{
    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('SKU')]
    public const FIELD_SKU = 'sku';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Unit price'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false, TypeSettings::DECIMALS => 6])]
    public const FIELD_UNIT_PRICE = 'unit_price';

    // static
    protected static array|bool|null $fieldDefinitions = null;
}
