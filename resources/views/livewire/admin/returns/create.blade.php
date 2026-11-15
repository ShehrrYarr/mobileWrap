<?php

use App\Models\BankAccount;
use App\Models\Order;
use App\Services\ReturnService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Return / exchange')] class extends Component
{
    #[Url(as: 'invoice', except: '')]
    public string $invoice = '';

    public ?int $orderId = null;

    /** order_item_id => ['qty' => , 'condition' => ] */
    public array $lines = [];

    public string $refundMethod = 'cash';

    public ?int $bankAccountId = null;

    public string $refundOverride = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->bankAccountId = BankAccount::active()->value('id');
        if ($this->invoice) {
            $this->find();
        }
    }

    public function find(): void
    {
        $this->resetErrorBag();
        $this->orderId = null;
        $this->lines = [];
        $number = strtoupper(trim($this->invoice));
        if ($number === '') {
            return;
        }

        $order = Order::with('items')->where('number', $number)->first();
        if (! $order || $order->status === 'held') {
            $this->addError('invoice', 'No invoice found with that number.');

            return;
        }
        if (in_array($order->status, ['cancelled', 'returned'], true)) {
            $this->addError('invoice', 'This invoice is already '.strtolower($order->statusLabel()).'.');

            return;
        }
        if ($order->channel === 'online' && ! in_array($order->status, ['delivered', 'completed', 'partially_returned'], true)) {
            $this->addError('invoice', 'Online orders can be returned once delivered. Cancel it from Online Orders instead.');

            return;
        }

        $this->orderId = $order->id;
        foreach ($order->items as $item) {
            $this->lines[$item->id] = ['qty' => 0, 'condition' => $item->item_type === 'laser_service' ? 'damaged' : 'restock'];
        }
        $this->refundMethod = 'cash';
    }

    public function pickInvoice(string $number): void
    {
        $this->invoice = $number;
        $this->find();
    }

    public function setQty(int $itemId, int $qty): void
    {
        $item = Order::find($this->orderId)?->items()->find($itemId);
        if ($item) {
            $max = (int) floor((float) $item->quantity - (float) $item->returned_quantity);
            $this->lines[$itemId]['qty'] = max(0, min($max, $qty));
        }
    }

    public function process(ReturnService $returns)
    {
        $order = Order::with('items', 'customer')->findOrFail($this->orderId);
        $this->validate([
            'refundMethod' => 'required|in:cash,bank_transfer,customer_credit,exchange,none',
            'bankAccountId' => $this->refundMethod === 'bank_transfer' ? 'required|exists:bank_accounts,id' : 'nullable',
            'refundOverride' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $return = $returns->process(
                $order,
                $this->lines,
                $this->refundMethod,
                $this->refundOverride !== '' ? (float) $this->refundOverride : null,
                $this->bankAccountId,
                $this->reason ?: null,
            );
        } catch (ValidationException $e) {
            $this->addError('lines', collect($e->errors())->flatten()->first());

            return;
        }

        if ($return->type === 'exchange') {
            session()->flash('toast', ['type' => 'success', 'message' => $return->number.' created — '.money($return->refund_amount).' credit is applied to the new sale.']);

            return $this->redirectRoute('admin.pos', ['exchange' => $return->id]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Return '.$return->number.' processed · '.money($return->refund_amount).' refunded.']);

        return $this->redirectRoute('admin.returns.index', navigate: true);
    }

    public function with(): array
    {
        $order = $this->orderId ? Order::with('items.variant', 'customer', 'returns')->find($this->orderId) : null;
        $computed = 0.0;
        if ($order) {
            foreach ($order->items as $item) {
                $computed += ReturnService::netUnitPrice($order, $item) * (float) ($this->lines[$item->id]['qty'] ?? 0);
            }
        }

        return [
            'order' => $order,
            'computed' => round($computed, 2),
            'selectedCount' => collect($this->lines)->sum('qty'),
            'banks' => BankAccount::active()->get(),
            'recent' => Order::whereIn('status', ['completed', 'delivered', 'partially_returned'])->latest('placed_at')->limit(6)->get(['id', 'number', 'customer_name', 'grand_total', 'placed_at']),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Return / exchange" :crumbs="['Returns' => route('admin.returns.index')]" subtitle="Find the invoice, choose what’s coming back, then refund or exchange." />

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <form wire:submit="find" class="d-flex gap-2">
                        <div class="form-icon flex-grow-1">
                            <input type="search" class="form-control form-control-icon form-control-lg @error('invoice') is-invalid @enderror" placeholder="Scan the receipt barcode or type the invoice number (POS-1001 / MW-1001)" wire:model="invoice" autofocus>
                            <i class="ri-barcode-line text-muted"></i>
                        </div>
                        <button type="submit" class="btn btn-primary px-4">Find</button>
                    </form>
                    @error('invoice')<div class="text-danger fs-13 mt-2">{{ $message }}</div>@enderror
                    @unless ($order)
                        <div class="mt-3 fs-13 text-muted">Recent invoices:
                            @foreach ($recent as $r)
                                <button type="button" class="btn btn-sm btn-light ms-1 mb-1" wire:click="pickInvoice('{{ $r->number }}')">{{ $r->number }}</button>
                            @endforeach
                        </div>
                    @endunless
                </div>
            </div>

            @if ($order)
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-0">{{ $order->number }} · {{ $order->customer_name }}</h5>
                            <span class="fs-12 text-muted">{{ $order->placed_at?->format('d M Y, h:i a') }} · {{ money($order->grand_total) }} · {{ $order->paymentMethodLabel() }}</span>
                        </div>
                        <span class="badge bg-{{ $order->statusColor() }}-subtle text-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-mw mb-0">
                            <thead class="table-light"><tr><th>Item</th><th class="text-center">Sold</th><th class="text-center">Returning</th><th>Condition</th><th class="text-end">Refund each</th></tr></thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    @php($left = (float) $item->quantity - (float) $item->returned_quantity)
                                    <tr wire:key="ri-{{ $item->id }}" class="{{ $left <= 0 ? 'opacity-50' : '' }}">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                @if ($item->item_type === 'laser_service')<span class="mw-thumb d-inline-flex align-items-center justify-content-center bg-dark text-white"><i class="ri-scissors-cut-line"></i></span>@else<img src="{{ $item->imageUrl() }}" class="mw-thumb" alt="">@endif
                                                <div><div class="fw-medium">{{ $item->name }}</div><div class="fs-12 text-muted">{{ $item->variant_label }}</div></div>
                                            </div>
                                        </td>
                                        <td class="text-center">{{ $item->qty() }}@if ((float) $item->returned_quantity > 0)<div class="fs-11 text-muted">{{ qty($item->returned_quantity) }} returned</div>@endif</td>
                                        <td class="text-center">
                                            @if ($left > 0)
                                                <div class="d-inline-flex align-items-center border rounded-3">
                                                    <button type="button" class="btn btn-sm" wire:click="setQty({{ $item->id }}, {{ (int) ($lines[$item->id]['qty'] ?? 0) - 1 }})"><i class="ri-subtract-line"></i></button>
                                                    <span class="px-2 fw-bold">{{ (int) ($lines[$item->id]['qty'] ?? 0) }}</span>
                                                    <button type="button" class="btn btn-sm" wire:click="setQty({{ $item->id }}, {{ (int) ($lines[$item->id]['qty'] ?? 0) + 1 }})"><i class="ri-add-line"></i></button>
                                                </div>
                                            @else
                                                <span class="text-muted fs-12">Fully returned</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->item_type === 'laser_service')
                                                <span class="fs-12 text-muted">Service — no stock</span>
                                            @elseif ($left > 0)
                                                <select class="form-select form-select-sm" wire:model.live="lines.{{ $item->id }}.condition">
                                                    <option value="restock">Back to stock</option>
                                                    <option value="damaged">Damaged (don’t restock)</option>
                                                </select>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ money(\App\Services\ReturnService::netUnitPrice($order, $item)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ((float) $order->discount_total + (float) $order->coupon_discount > 0)
                        <div class="card-footer fs-12 text-muted">Refund amounts include this invoice’s discount ({{ money((float) $order->discount_total + (float) $order->coupon_discount) }}) spread across its items.</div>
                    @endif
                </div>
            @endif
        </div>

        @if ($order)
            <div class="col-xl-4">
                <div class="card" style="position: sticky; top: 90px">
                    <div class="card-header"><h5 class="card-title mb-0">Refund</h5></div>
                    <div class="card-body">
                        <div class="d-grid gap-2 mb-3">
                            @foreach ([
                                'cash' => ['Cash refund', 'ri-money-dollar-circle-line', 'Paid out of the cash drawer'],
                                'exchange' => ['Exchange', 'ri-exchange-line', 'Credit goes to a new POS sale'],
                                'bank_transfer' => ['Bank transfer', 'ri-bank-line', 'Refund to the customer’s account'],
                                'customer_credit' => ['Khata credit', 'ri-book-2-line', $order->customer_id ? 'Reduces '.\Illuminate\Support\Str::before($order->customer_name, ' ').'’s balance' : 'Needs a saved customer'],
                                'none' => ['No refund', 'ri-forbid-line', 'Items back in stock only'],
                            ] as $value => [$label, $icon, $hint])
                                <label class="d-flex align-items-center gap-3 border rounded-3 p-2 px-3 {{ $refundMethod === $value ? 'border-primary bg-primary-subtle' : '' }} {{ $value === 'customer_credit' && ! $order->customer_id ? 'opacity-50' : '' }}" style="cursor: pointer">
                                    <input type="radio" class="form-check-input mt-0" value="{{ $value }}" wire:model.live="refundMethod" @disabled($value === 'customer_credit' && ! $order->customer_id)>
                                    <i class="{{ $icon }} fs-4"></i>
                                    <span><span class="fw-semibold d-block">{{ $label }}</span><span class="fs-12 text-muted">{{ $hint }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        @if ($refundMethod === 'bank_transfer')
                            <select class="form-select mb-3" wire:model="bankAccountId">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->displayName() }}</option>@endforeach</select>
                        @endif
                        @if ($refundMethod !== 'none')
                            <label class="form-label fs-13">Refund amount</label>
                            <div class="input-group mb-1"><span class="input-group-text">Rs</span><input type="number" min="0" class="form-control" wire:model.live.debounce.400ms="refundOverride" placeholder="{{ number_format($computed, 2) }}"></div>
                            <div class="fs-12 text-muted mb-3">Calculated: {{ money($computed) }} — leave blank to use it.</div>
                        @endif
                        <input type="text" class="form-control mb-3" wire:model="reason" placeholder="Reason (e.g. wrong model, defective)">
                        @error('lines')<div class="alert alert-danger py-2 fs-13">{{ $message }}</div>@enderror
                        <button type="button" class="btn btn-primary w-100 btn-lg" wire:click="process" wire:loading.attr="disabled" @disabled($selectedCount <= 0)>
                            @if ($refundMethod === 'exchange')
                                <i class="ri-exchange-line me-1"></i>Continue to exchange
                            @else
                                <i class="ri-arrow-go-back-line me-1"></i>Process return · {{ money($refundOverride !== '' ? (float) $refundOverride : ($refundMethod === 'none' ? 0 : $computed)) }}
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
