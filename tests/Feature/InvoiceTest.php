<?php

namespace Amarenkov\MutableContentShop\Tests\Feature;

use DomainException;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentShop\Lovs\InvoicePaymentState;
use Amarenkov\MutableContentShop\Lovs\OrderState;
use Amarenkov\MutableContentShop\Models\Invoice;
use Amarenkov\MutableContentShop\Models\Order;
use Amarenkov\MutableContentShop\Support\Lovs;

use Amarenkov\MutableContentShop\Tests\Fixtures\Lovs\ConfirmedOrderState;
use Amarenkov\MutableContentShop\Tests\TestCase;

class InvoiceTest extends TestCase
{
    protected function makeOrder(): Order
    {
        $order = new Order();
        $order->fill([Order::FIELD_STATE => OrderState::NEW, Order::FIELD_USER_ID => $this->user->getKey()]);
        $order->save();

        return $order;
    }

    protected function makeInvoice(Order $order, string $amount, ?string $paid = null): Invoice
    {
        $invoice = new Invoice();
        $invoice->fill([
            Invoice::FIELD_ORDER_ID => $order->id,
            Invoice::FIELD_NUMBER => 'INV-'.$amount,
            Invoice::FIELD_DATE => '2026-10-16',
            Invoice::FIELD_AMOUNT => Money::of($amount, Currency::USD),
            Invoice::FIELD_PAID => $paid === null ? null : Money::of($paid, Currency::USD),
        ]);
        $invoice->save();

        return $invoice;
    }

    public function test_payment_state_follows_the_amounts(): void
    {
        $invoice = $this->makeInvoice($this->makeOrder(), '100');

        $this->assertSame(InvoicePaymentState::UNPAID, $invoice->getField(Invoice::FIELD_PAYMENT_STATE));
        $this->assertSame('100', $invoice->due()->amount);

        $invoice->{Invoice::FIELD_PAID} = Money::of('40', Currency::USD);
        $invoice->save();

        $this->assertSame(InvoicePaymentState::PARTIALLY_PAID, $invoice->fresh()->getField(Invoice::FIELD_PAYMENT_STATE));
        $this->assertSame('60', $invoice->due()->amount);

        $invoice->{Invoice::FIELD_PAID} = Money::of('100', Currency::USD);
        $invoice->save();

        $this->assertSame(InvoicePaymentState::PAID, $invoice->fresh()->getField(Invoice::FIELD_PAYMENT_STATE));
        $this->assertNull($invoice->due());
        $this->assertSame('2026-10-16', $invoice->getDate(Invoice::FIELD_DATE)->format('Y-m-d'));
    }

    public function test_paid_amount_in_another_currency_is_rejected(): void
    {
        $invoice = $this->makeInvoice($this->makeOrder(), '100');
        $invoice->{Invoice::FIELD_PAID} = new Money('10', 'EUR');

        $this->expectException(DomainException::class);

        $invoice->save();
    }

    public function test_order_sums_its_invoices(): void
    {
        $order = $this->makeOrder();

        $this->assertNull($order->invoicedTotal());
        $this->assertNull($order->paidTotal());

        $this->makeInvoice($order, '100', '100');
        $this->makeInvoice($order, '50.5', '20');
        $this->makeInvoice($order, '10');

        $this->assertSame(3, $order->invoices()->count());
        $this->assertSame('160.5', $order->invoicedTotal()->amount);
        $this->assertSame('120', $order->paidTotal()->amount);
        $this->assertTrue($order->invoices()->first()->order->is($order));
    }

    public function test_file_on_the_invoices_disk(): void
    {
        Storage::fake('local');

        $invoice = $this->makeInvoice($this->makeOrder(), '10');
        $this->assertFalse($invoice->hasFile());

        $path = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')->store('invoices', 'local');
        $invoice->fill([Invoice::FIELD_FILE => $path, Invoice::FIELD_FILE_NAME => 'invoice.pdf']);
        $invoice->save();

        $this->assertTrue($invoice->hasFile());
        $this->assertTrue(Invoice::disk()->exists($invoice->getField(Invoice::FIELD_FILE)));
    }

    public function test_state_lov_class_from_the_config(): void
    {
        $this->assertSame(OrderState::class, Lovs::orderState());

        config(['mutable-content-shop.lovs.order_state' => ConfirmedOrderState::class]);

        $this->assertSame(ConfirmedOrderState::class, Lovs::orderState());

        $lov = (new LovRegistry())->addClass(Lovs::orderState())->getSystemLovs()[OrderState::CLASS_CODE];

        $this->assertContains(ConfirmedOrderState::CONFIRMED, array_map(fn ($item) => $item->code, $lov->items));
        $this->assertCount(5, $lov->items);
    }
}
