<?php

namespace Amarenkov\MutableContentShop\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;

use Amarenkov\MutableContentShop\Concerns\HasPriceBreaks;
use Amarenkov\MutableContentShop\Contracts\Sellable;

#[Table('fixtures.products')]
#[ClassLabel('Product')]
#[CommonFieldLabel]
class Product extends ModelWithFields implements Sellable
{
    use HasPriceBreaks;

    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('SKU')]
    public const FIELD_SKU = 'sku';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function sellableType(): string
    {
        return $this->getMorphClass();
    }

    public function sellableId(): int
    {
        return (int)$this->getKey();
    }

    public function lineFields(): array
    {
        return [
            'name' => $this->getField('label'),
            self::FIELD_SKU => $this->getField(self::FIELD_SKU),
            self::FIELD_PRICES => $this->getField(self::FIELD_PRICES),
            self::FIELD_MOQ => $this->getField(self::FIELD_MOQ),
            self::FIELD_ORDER_MULTIPLE => $this->getField(self::FIELD_ORDER_MULTIPLE),
        ];
    }
}
