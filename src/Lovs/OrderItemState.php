<?php

namespace Amarenkov\MutableContentShop\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(OrderItemState::CLASS_CODE)]
#[ClassLabel('Order item state')]
#[LovItem(OrderItemState::NEW, 'New')]
#[LovItem(OrderItemState::PAID, 'Paid')]
#[LovItem(OrderItemState::DONE, 'Done')]
#[LovItem(OrderItemState::CANCELLED, 'Cancelled')]
class OrderItemState extends Lov
{
    // const
    public const CLASS_CODE = 'order_item_state';

    public const NEW = 'new';
    public const PAID = 'paid';
    public const DONE = 'done';
    public const CANCELLED = 'cancelled';
}
