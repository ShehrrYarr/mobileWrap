<?php

use App\Models\PurchaseOrder;
use App\Models\VendorTransaction;
use App\Services\FinanceService;
use App\Services\PurchaseService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] class extends Component
{
    public PurchaseOrder $purchase;

    public ?string $modal = null; // receive | pay | return

    public array $qty = [];

    public array $pay = [];

    public string $date = '';

    public string $note = '';

    public function mount(PurchaseOrder $purchase): void
    {
        $this->purchase = $purchase;
    }

    public function rendering($view): void
    {
        $view->title($this->purchase->number);
    }

    public function open(string $modal): void
    {
        $this->resetErrorBag();
        $this->purchase->load('items');
        $this->date = now()->toDateString();
        $this->note = '';
        $this->qty = [];

        if ($modal === 'receive') {
            foreach ($this->purchase->items as $i) {
                $this->qty[$i->id] = max(0, (float) $i->quantity - (float) $i->received_quantity);
            }
        } elseif ($modal === 'return') {
            foreach ($this->purchase->items as $i) {
                $this->qty[$i->id] = 0;
            }
        } elseif ($modal === 'pay') {
            $amount = $this->purchase->suggestedPayment();
            $this->pay = ['account' => FinanceService::defaultAccountKey($amount), 'amount' => $amount];
        }
        $this->modal = $modal;
    }

    public function markOrdered(): void
    {
        abort_unless($this->purchase->status === 'draft', 422);
        $this->purchase->update(['status' => 'ordered']);
        $this->dispatch('toast', type: 'success', message: 'Marked as ordered.');
    }

    public function receive(PurchaseService $service): void
    {
        $this->validate(['date' => 'required|date', 'qty.*' => 'nullable|numeric|min:0']);
        try {
            $service->receive($this->purchase, $this->qty, $this->date);
        } catch (ValidationException $e) {
            $this->addError('qty', collect($e->errors())->flatten()->first());

            return;
        }
        $this->finish('Stock received and added to inventory.');
    }

    public function savePayment(PurchaseService $service): void
    {
        $this->validate(['pay.account' => 'required', 'pay.amount' => 'required|numeric|min:1', 'date' => 'required|date'], [], ['pay.amount' => 'amount']);
        try {
            $service->pay($this->purchase->vendor, FinanceService::accountFromKey($this->pay['account']), (float) $this->pay['amount'], $this->purchase, $this->note ?: null, $this->date);
        } catch (ValidationException $e) {
            $this->addError('pay.amount', collect($e->errors())->flatten()->first());

            return;
        }
        $this->finish(money($this->pay['amount']).' paid to '.$this->purchase->vendor->name.'.');
    }

    public function returnGoods(PurchaseService $service): void
    {
        $this->validate(['qty.*' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:255']);
        try {
            $credit = $service->returnToVendor($this->purchase, $this->qty, $this->note ?: null);
        } catch (ValidationException $e) {
            $this->addError('qty', collect($e->errors())->flatten()->first());

            return;
        }
        $this->finish('Returned to vendor — payable reduced by '.money($credit).'.');
    }

    public function cancel(PurchaseService $service): void
    {
        $service->cancel($this->purchase);
        $this->finish('Purchase cancelled.');
    }

    protected function finish(string $message): void
    {
        $this->modal = null;
        $this->purchase->refresh();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function with(): array
    {
        $this->purchase->load('items.variant.product', 'vendor', 'user');

        return [
            'po' => $this->purchase,
            'history' => VendorTransaction::with('user:id,name')->where('reference_type', $this->purchase->getMorphClass())->where('reference_id', $this->purchase->id)->orderBy('id')->get(),
            'accounts' => $this->modal === 'pay' ? FinanceService::accountOptions() : [],
            'canReceive' => in_array($this->purchase->status, ['draft', 'ordered', 'partial'], true),
            'canReturn' => $this->purchase->items->contains(fn ($i) => (float) $i->received_quantity - (float) $i->returned_quantity > 0),
        ];
    }
}; ?>

<div>
    <x-admin.page-header :title="$po->number" :crumbs="['Purchases' => route('admin.purchases.index')]" :subtitle="'From '.$po->vendor->name.($po->bill_number ? ' · bill '.$po->bill_number : '')">
        <x-slot:actions>
            <a href="{{ route('admin.purchases.print', $po) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-printer-line me-1"></i>Print</a>
            @if (in_array($po->status, ['draft', 'ordered']))
                <a href="{{ route('admin.purchases.edit', $po) }}" wire:navigate class="btn btn-sm btn-light"><i class="ri-pencil-line me-1"></i>Edit</a>
                <button class="btn btn-sm btn-light-danger" wire:click="cancel" wire:confirm="Cancel {{ $po->number }}? Nothing has been received, so stock and payables are unaffected.">Cancel PO</button>
            @endif
            @if ($po->status === 'draft')<button class="btn btn-sm btn-light-primary" wire:click="markOrdered">Mark as ordered</button>@endif
            @if ($canReturn)<button class="btn btn-sm btn-light-warning" wire:click="open('return')"><i class="ri-arrow-go-back-line me-1"></i>Return to vendor</button>@endif
            @if ($po->canPay())<button class="btn btn-sm btn-light-success" wire:click="open('pay')"><i class="ri-hand-coin-line me-1"></i>{{ $po->status === 'ordered' ? 'Pay advance' : 'Pay' }}</button>@endif
            @if ($canReceive)<button class="btn btn-sm btn-primary" wire:click="open('receive')"><i class="ri-inbox-archive-line me-1"></i>Receive stock</button>@endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <h6 class="card-title mb-0 flex-grow-1">Items</h6>
                    <span class="badge bg-{{ $po->statusColor() }}-subtle text-{{ $po->statusColor() }} fs-12">{{ $po->statusLabel() }}</span>
                    @if ($po->isOverdue())<span class="badge bg-danger fs-12">Payment overdue</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Returned</th><th class="text-end">Unit cost</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($po->items as $item)
                                @php($area = $item->variant?->product?->unit === 'cm2')
                                <tr>
                                    <td>
                                        @if ($item->variant)<a href="{{ route('admin.inventory.ledger', $item->variant) }}" wire:navigate class="fw-semibold text-body">{{ $item->variant->displayName() }}</a>@else<span class="text-muted">Deleted item</span>@endif
                                        <div class="fs-12 text-muted">{{ $item->variant?->sku }}</div>
                                    </td>
                                    <td class="text-end">{{ qty($item->quantity) }}{{ $area ? ' cm²' : '' }}</td>
                                    <td class="text-end {{ (float) $item->received_quantity >= (float) $item->quantity ? 'text-success' : ((float) $item->received_quantity > 0 ? 'text-warning' : 'text-muted') }}">{{ qty($item->received_quantity) }}</td>
                                    <td class="text-end {{ (float) $item->returned_quantity > 0 ? 'text-danger' : 'text-muted' }}">{{ qty($item->returned_quantity) }}</td>
                                    <td class="text-end">{{ $area ? 'Rs. '.number_format($item->unit_cost, 4) : money($item->unit_cost) }}</td>
                                    <td class="text-end fw-semibold">{{ money($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Vendor ledger for this purchase</h6></div>
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Entry</th><th class="text-end">Paid / returned</th><th class="text-end">Payable</th></tr></thead>
                        <tbody>
                            @forelse ($history as $h)
                                <tr>
                                    <td class="fs-13 text-nowrap">{{ $h->date->format('d M Y') }}</td>
                                    <td class="fs-13"><span class="fw-semibold text-capitalize">{{ $h->type }}</span> · {{ $h->note }} <span class="text-muted fs-12">{{ $h->user?->name }}</span></td>
                                    <td class="text-end text-success">{{ $h->debit > 0 ? money($h->debit) : '' }}</td>
                                    <td class="text-end text-danger">{{ $h->credit > 0 ? money($h->credit) : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted fs-13 py-4">Nothing booked yet — the payable is added when stock is received.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Vendor</span><a href="{{ route('admin.vendors.show', $po->vendor) }}" wire:navigate class="fw-semibold">{{ $po->vendor->name }}</a></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Bill date</span><span>{{ $po->order_date->format('d M Y') }}</span></div>
                    @if ($po->received_date)<div class="d-flex justify-content-between mb-2"><span class="text-muted">Last received</span><span>{{ $po->received_date->format('d M Y') }}</span></div>@endif
                    @if ($po->due_date)<div class="d-flex justify-content-between mb-2"><span class="text-muted">Payment due</span><span class="{{ $po->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $po->due_date->format('d M Y') }}</span></div>@endif
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Entered by</span><span>{{ $po->user?->name ?? '—' }}</span></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Items</span><span>{{ money($po->subtotal) }}</span></div>
                    @if ((float) $po->discount > 0)<div class="d-flex justify-content-between mb-1"><span class="text-muted">Discount</span><span>−{{ money($po->discount) }}</span></div>@endif
                    @if ((float) $po->shipping > 0)<div class="d-flex justify-content-between mb-1"><span class="text-muted">Freight / other</span><span>{{ money($po->shipping) }}</span></div>@endif
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-2"><span>Bill total</span><span>{{ money($po->total) }}</span></div>
                    @if (! in_array($po->status, ['draft', 'cancelled']))
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Goods received (payable)</span><span>{{ money($po->received_total) }}</span></div>
                    @endif
                    @if ((float) $po->returned_total > 0)<div class="d-flex justify-content-between mb-1"><span class="text-muted">Returned</span><span>−{{ money($po->returned_total) }}</span></div>@endif
                    <div class="d-flex justify-content-between mb-1"><span class="text-muted">Paid</span><span>−{{ money($po->paid_amount) }}</span></div>
                    @if ($po->balanceDue() < 0)
                        <div class="d-flex justify-content-between fw-bold text-success"><span>Advance paid</span><span>{{ money(abs($po->balanceDue())) }}</span></div>
                    @else
                        <div class="d-flex justify-content-between fw-bold {{ $po->balanceDue() > 0 ? 'text-danger' : 'text-success' }}"><span>Balance due</span><span>{{ money($po->balanceDue()) }}</span></div>
                    @endif
                    @if ($po->status === 'partial')<div class="fs-12 text-muted mt-1">The rest becomes payable as it arrives.</div>@endif
                    @if ($po->notes)<hr><div class="fs-13 text-muted">{{ $po->notes }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- Receive --}}
    <x-admin.modal :show="$modal === 'receive'" close="$set('modal', null)" title="Receive stock" subtitle="Enter what actually arrived. Partial deliveries are fine — receive the rest later." size="lg">
        <form wire:submit="receive" id="rcv-form">
            @error('qty')<div class="alert alert-danger py-2 fs-13">{{ $message }}</div>@enderror
            <table class="table table-sm align-middle">
                <thead><tr><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Already in</th><th style="width: 150px">Receiving now</th></tr></thead>
                <tbody>
                    @foreach ($po->items as $item)
                        @php($open = (float) $item->quantity - (float) $item->received_quantity)
                        <tr wire:key="rq-{{ $item->id }}">
                            <td class="fs-13">{{ $item->variant?->displayName() }}</td>
                            <td class="text-end">{{ qty($item->quantity) }}</td>
                            <td class="text-end text-muted">{{ qty($item->received_quantity) }}</td>
                            <td>@if ($open > 0)<input type="number" min="0" max="{{ $open }}" step="any" class="form-control form-control-sm" wire:model="qty.{{ $item->id }}">@else<span class="text-success fs-12"><i class="ri-check-line"></i> complete</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="row"><div class="col-md-5"><label class="form-label">Received on</label><input type="date" class="form-control" wire:model="date"></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Close</button>
            <button type="submit" form="rcv-form" class="btn btn-primary" wire:loading.attr="disabled">Receive into stock</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Return --}}
    <x-admin.modal :show="$modal === 'return'" close="$set('modal', null)" title="Return to vendor" subtitle="Takes the items out of stock and reduces what you owe this vendor." size="lg">
        <form wire:submit="returnGoods" id="ret-form">
            @error('qty')<div class="alert alert-danger py-2 fs-13">{{ $message }}</div>@enderror
            <table class="table table-sm align-middle">
                <thead><tr><th>Item</th><th class="text-end">Received</th><th class="text-end">Returned</th><th style="width: 150px">Return now</th></tr></thead>
                <tbody>
                    @foreach ($po->items as $item)
                        @php($can = (float) $item->received_quantity - (float) $item->returned_quantity)
                        <tr wire:key="rt-{{ $item->id }}">
                            <td class="fs-13">{{ $item->variant?->displayName() }}</td>
                            <td class="text-end">{{ qty($item->received_quantity) }}</td>
                            <td class="text-end text-muted">{{ qty($item->returned_quantity) }}</td>
                            <td>@if ($can > 0)<input type="number" min="0" max="{{ $can }}" step="any" class="form-control form-control-sm" wire:model="qty.{{ $item->id }}">@else<span class="text-muted fs-12">—</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <label class="form-label">Reason</label><input class="form-control" wire:model="note" placeholder="e.g. damaged in transit">
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Close</button>
            <button type="submit" form="ret-form" class="btn btn-warning" wire:loading.attr="disabled">Return items</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Pay --}}
    <x-admin.modal :show="$modal === 'pay'" close="$set('modal', null)" :title="'Pay '.$po->vendor->name" :subtitle="$po->status === 'ordered' ? 'Advance against '.$po->number.' (goods not received yet)' : 'Against '.$po->number.' — due '.money($po->balanceDue())">
        <form wire:submit="savePayment" id="pp-form" class="row g-3">
            <div class="col-12"><label class="form-label">Pay from</label><select class="form-select" wire:model="pay.account">@foreach ($accounts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" step="0.01" min="0" class="form-control @error('pay.amount') is-invalid @enderror" wire:model="pay.amount">@error('pay.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" wire:model="date"></div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="note" placeholder="e.g. cheque #1234"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Close</button>
            <button type="submit" form="pp-form" class="btn btn-primary" wire:loading.attr="disabled">Record payment</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
