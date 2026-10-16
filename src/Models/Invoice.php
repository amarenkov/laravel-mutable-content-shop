<?php

namespace Amarenkov\MutableContentShop\Models;

use DomainException;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContentShop\Lovs\InvoicePaymentState;
use Amarenkov\MutableContentShop\Support\Models;

/**
 * An invoice issued for an order: amount, how much of it is paid and the invoice document on the invoices disk.
 * The payment state follows the amounts on save.
 */
#[Table('shop.invoices')]
#[ClassLabel('Invoice')]
class Invoice extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_OBJECT), CFALabel('Order'), CFAObjectClass(Order::class), CFAIsRequired, CFAIsImmutable]
    public const FIELD_ORDER_ID = 'order_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Number'), CFAIsRequired]
    public const FIELD_NUMBER = 'number';

    #[CFAType(Type::TYPE_DATE), CFALabel('Date')]
    public const FIELD_DATE = 'date';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Amount'), CFAIsRequired, CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_AMOUNT = 'amount';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Paid amount'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false])]
    public const FIELD_PAID = 'paid';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Payment state'), CFAIsRequired, CFALovCode(InvoicePaymentState::CLASS_CODE)]
    public const FIELD_PAYMENT_STATE = 'payment_state';

    #[CFAType(Type::TYPE_STRING), CFALabel('File')]
    public const FIELD_FILE = 'file';

    #[CFAType(Type::TYPE_STRING), CFALabel('File name')]
    public const FIELD_FILE_NAME = 'file_name';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Comment')]
    public const FIELD_COMMENT = 'comment';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice) {
            $invoice->{self::FIELD_PAYMENT_STATE} = $invoice->paymentState();
        });
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('mutable-content-shop.invoices_disk'));
    }

    // public
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Models::order(), self::FIELD_ORDER_ID);
    }

    /**
     * Unpaid without a payment, paid when the paid amount covers the invoice, partially paid between.
     */
    public function paymentState(): string
    {
        $amount = $this->getMoney(self::FIELD_AMOUNT);
        $paid = $this->getMoney(self::FIELD_PAID);

        if (!$paid || $paid->isZero()) {
            return InvoicePaymentState::UNPAID;
        }

        if ($amount && $paid->currency !== $amount->currency) {
            throw new DomainException(__('mutable-content-shop::shop.invoice_currency_mismatch'));
        }

        return $amount && $paid->compareTo($amount) < 0 ? InvoicePaymentState::PARTIALLY_PAID : InvoicePaymentState::PAID;
    }

    /**
     * What is left to pay; null when nothing is.
     */
    public function due(): ?Money
    {
        $amount = $this->getMoney(self::FIELD_AMOUNT);
        $paid = $this->getMoney(self::FIELD_PAID);

        if (!$amount) {
            return null;
        }

        $result = $paid ? $amount->subtract($paid) : $amount;

        return $result->isPositive() ? $result : null;
    }

    public function hasFile(): bool
    {
        return (string)$this->getField(self::FIELD_FILE) !== '' && static::disk()->exists($this->getField(self::FIELD_FILE));
    }
}
