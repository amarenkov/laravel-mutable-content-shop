<?php

namespace Amarenkov\MutableContentShop\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(InvoicePaymentState::CLASS_CODE)]
#[ClassLabel('Invoice payment state')]
#[LovItem(InvoicePaymentState::UNPAID, 'Unpaid')]
#[LovItem(InvoicePaymentState::PARTIALLY_PAID, 'Partially paid')]
#[LovItem(InvoicePaymentState::PAID, 'Paid')]
class InvoicePaymentState extends Lov
{
    // const
    public const CLASS_CODE = 'invoice_payment_state';

    public const UNPAID = 'unpaid';
    public const PARTIALLY_PAID = 'partially_paid';
    public const PAID = 'paid';
}
