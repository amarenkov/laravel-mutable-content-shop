<?php

namespace Amarenkov\MutableContentShop\Concerns;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContent\ValueObjects\Money;

/**
 * Unit prices by quantity: [['quantity' => int, 'price' => money field value], ...] ascending by quantity,
 * a minimum order quantity and an order multiple. A single price is one break from the quantity of 1.
 */
trait HasPriceBreaks
{
    // const
    #[CFAType(Type::TYPE_SYSTEM), CFALabel('Price breaks')]
    public const FIELD_PRICES = 'prices';

    #[CFAType(Type::TYPE_INT), CFALabel('Minimum order quantity'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_MOQ = 'moq';

    #[CFAType(Type::TYPE_INT), CFALabel('Order multiple'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_ORDER_MULTIPLE = 'order_multiple';

    // public
    /**
     * @return array<array{quantity: int, price: Money}>
     */
    public function getPrices(): array
    {
        $result = [];

        foreach ($this->getField(self::FIELD_PRICES) ?? [] as $break) {
            $price = Money::fromFieldValue($break['price'] ?? null);

            if ($price && isset($break['quantity'])) {
                $result[] = ['quantity' => (int)$break['quantity'], 'price' => $price];
            }
        }

        return $result;
    }

    /**
     * @param array<array{quantity?: int|string|null, price?: Money|array|null}> $breaks
     */
    public function setPrices(array $breaks): static
    {
        $result = [];

        foreach ($breaks as $break) {
            $price = ($break['price'] ?? null) instanceof Money ? $break['price'] : Money::fromFieldValue($break['price'] ?? null);

            if ($price && isset($break['quantity'])) {
                $result[] = ['quantity' => (int)$break['quantity'], 'price' => $price->toFieldValue()];
            }
        }

        usort($result, fn ($a, $b) => $a['quantity'] <=> $b['quantity']);

        $this->{self::FIELD_PRICES} = $result ?: null;

        return $this;
    }

    public function setPrice(?Money $price): static
    {
        return $this->setPrices($price ? [['quantity' => 1, 'price' => $price]] : []);
    }

    public function hasPrices(): bool
    {
        return (bool)$this->getPrices();
    }

    /**
     * Positive, not below the minimum order quantity and a multiple of the order multiple.
     */
    public function isQuantityAllowed(?int $quantity): bool
    {
        if (!$quantity || $quantity < 0) {
            return false;
        }

        if ($this->getField(self::FIELD_MOQ) && $quantity < $this->getField(self::FIELD_MOQ)) {
            return false;
        }

        if ($this->getField(self::FIELD_ORDER_MULTIPLE) && $quantity % $this->getField(self::FIELD_ORDER_MULTIPLE) != 0) {
            return false;
        }

        return true;
    }

    /**
     * Unit price of the last break not above the quantity; none for a quantity that is not allowed.
     */
    public function getPriceForN(?int $quantity): ?Money
    {
        if (!$this->isQuantityAllowed($quantity)) {
            return null;
        }

        $result = null;

        foreach ($this->getPrices() as $break) {
            if ($break['quantity'] <= $quantity) {
                $result = $break['price'];
            }
        }

        return $result;
    }

    /**
     * Unit price times the quantity, rounded to the currency decimals.
     */
    public function getTotalForN(?int $quantity): ?Money
    {
        return $this->getPriceForN($quantity)?->multiply($quantity)->round();
    }
}
