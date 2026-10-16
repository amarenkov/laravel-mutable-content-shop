<?php

namespace Amarenkov\MutableContentShop\Tests\Fixtures\Lovs;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

use Amarenkov\MutableContentShop\Lovs\OrderState;

#[ClassCode(OrderState::CLASS_CODE)]
#[ClassLabel('Order state')]
#[LovItem(OrderState::NEW, 'New')]
#[LovItem(ConfirmedOrderState::CONFIRMED, 'Confirmed')]
#[LovItem(OrderState::PAID, 'Paid')]
#[LovItem(OrderState::DONE, 'Done')]
#[LovItem(OrderState::CANCELLED, 'Cancelled')]
class ConfirmedOrderState extends OrderState
{
    // const
    public const CONFIRMED = 'confirmed';
}
