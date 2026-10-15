<?php

namespace Amarenkov\MutableContentShop\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(OrderState::CLASS_CODE)]
#[ClassLabel('Order state')]
#[LovItem(OrderState::NEW, 'New')]
#[LovItem(OrderState::PAID, 'Paid')]
#[LovItem(OrderState::DONE, 'Done')]
#[LovItem(OrderState::CANCELLED, 'Cancelled')]
class OrderState extends Lov
{
    // const
    public const CLASS_CODE = 'order_state';

    public const NEW = 'new';
    public const PAID = 'paid';
    public const DONE = 'done';
    public const CANCELLED = 'cancelled';
}
