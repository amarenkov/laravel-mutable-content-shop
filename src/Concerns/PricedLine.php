<?php

namespace Amarenkov\MutableContentShop\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContentShop\Contracts\Sellable;

/**
 * A line of a cart or an order: what is sold, its name, price breaks, quantity, unit price and total.
 * A class redeclares FIELD_UNIT_PRICE with the decimals setting when unit prices need more digits than the currency.
 */
trait PricedLine
{
    use HasPriceBreaks;

    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('Item type')]
    public const FIELD_SELLABLE_TYPE = 'sellable_type';

    #[CFAType(Type::TYPE_INT), CFALabel('Item')]
    public const FIELD_SELLABLE_ID = 'sellable_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Name')]
    public const FIELD_NAME = 'name';

    #[CFAType(Type::TYPE_INT), CFALabel('Quantity'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_QUANTITY = 'quantity';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Unit price'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_UNIT_PRICE = 'unit_price';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Total'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_TOTAL = 'total';

    // public
    public function sellable(): MorphTo
    {
        return $this->morphTo('sellable', self::FIELD_SELLABLE_TYPE, self::FIELD_SELLABLE_ID);
    }

    public function isOf(Sellable $item): bool
    {
        return $this->getField(self::FIELD_SELLABLE_TYPE) === $item->sellableType()
            && (string)$this->getField(self::FIELD_SELLABLE_ID) === (string)$item->sellableId();
    }

    public function getQuantity(): ?int
    {
        $quantity = $this->getField(self::FIELD_QUANTITY);

        return $quantity === null ? null : (int)$quantity;
    }

    /**
     * The quantity with its unit price and total; a quantity that is not allowed or below the first break has neither.
     */
    public function setQuantity(int|string|null $quantity): static
    {
        $quantity = $quantity === null || $quantity === '' ? null : (int)$quantity;

        $this->{self::FIELD_QUANTITY} = $quantity;
        $this->{self::FIELD_UNIT_PRICE} = $this->getPriceForN($quantity);
        $this->{self::FIELD_TOTAL} = $this->getTotalForN($quantity);

        return $this;
    }

    /**
     * Values of all fields of the line, the ones added by the application among them.
     *
     * @return array<string, mixed>
     */
    public function lineFields(): array
    {
        $result = [];

        foreach (array_keys($this->fieldDefinitions()) as $code) {
            $value = $this->getField($code);

            if ($value !== null) {
                $result[$code] = $value;
            }
        }

        return $result;
    }

    /**
     * Fills the fields this line has from the values, others are skipped.
     *
     * @param array<string, mixed> $values
     */
    public function fillLine(array $values): static
    {
        return $this->fill(array_intersect_key($values, $this->fieldDefinitions()));
    }
}
