<?php

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\FinanceService;
use App\Services\PurchaseService;
use App\Services\VendorLedgerService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Vendor ledger')] class extends Component
{
    use WithPagination;

    public Vendor $vendor;

    #[Url(except: 'ledger')]
    public string $tab = 'ledger';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public bool $showPay = false;

    public array $pay = [];

    public bool $showAdjust = false;

    public array $adjust = ['direction' => 'credit', 'amount' => '', 'note' => ''];

    public function mount(Vendor $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function openPay(?int $purchaseId = null): void
    {
        $po = $purchaseId ? PurchaseOrder::find($purchaseId) : null;
        $amount = $po ? $po->suggestedPayment() : max(0, (float) $this->vendor->current_balance);
        $this->pay = [
            'account' => FinanceService::defaultAccountKey($amount),
            'amount' => $amount,
            'purchase_id' => $po?->id ?? '',
            'date' => now()->toDateString(),
            'note' => '',
        ];
        $this->resetErrorBag();
        $this->showPay = true;
    }

    public function savePayment(PurchaseService $purchases): void
    {
        $this->validate([
            'pay.account' => 'required|string',
            'pay.amount' => 'required|numeric|min:1',
            'pay.date' => 'required|date',
            'pay.note' => 'nullable|string|max:255',
        ], [], ['pay.amount' => 'amount', 'pay.account' => 'account']);

        $account = FinanceService::accountFromKey($this->pay['account']);
        $po = $this->pay['purchase_id'] ? PurchaseOrder::where('vendor_id', $this->vendor->id)->find($this->pay['purchase_id']) : null;

        try {
            $purchases->pay($this->vendor, $account, (float) $this->pay['amount'], $po, $this->pay['note'] ?: null, $this->pay['date']);
        } catch (ValidationException $e) {
            $this->addError('pay.amount', collect($e->errors())->flatten()->first());

            return;
        }

        $this->vendor->refresh();
        $this->showPay = false;
        $this->dispatch('toast', type: 'success', message: money($this->pay['amount']).' paid to '.$this->vendor->name.'.');
    }

    public function saveAdjustment(VendorLedgerService $ledger): void
    {
        $this->validate(['adjust.amount' => 'required|numeric|min:1', 'adjust.note' => 'required|string|max:255', 'adjust.direction' => 'in:credit,debit']);
        $amount = (float) $this->adjust['amount'];
        $ledger->record($this->vendor, 'adjustment', $this->adjust['direction'] === 'debit' ? $amount : 0, $this->adjust['direction'] === 'credit' ? $amount : 0, null, $this->adjust['note']);
        $this->vendor->refresh();
        $this->showAdjust = false;
        $this->adjust = ['direction' => 'credit', 'amount' => '', 'note' => ''];
        $this->dispatch('toast', type: 'success', message: 'Ledger adjusted.');
    }

    public function with(): array
    {
        $entries = \App\Support\Ledger::running($this->vendor->transactions(), 'credit - debit', (float) $this->vendor->current_balance)
            ->with('user:id,name', 'reference')
            ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
            ->orderByDesc('date')->orderByDesc('id')->paginate(25, pageName: 'lp');

        $purchases = $this->vendor->purchaseOrders()->withCount('items')->paginate(15, pageName: 'pp');

        return [
            'entries' => $entries,
            'purchases' => $purchases,
            'stats' => [
                'purchased' => (float) $this->vendor->transactions()->where('type', 'purchase')->sum('credit'),
                'paid' => (float) $this->vendor->transactions()->where('type', 'payment')->sum('debit'),
                'returned' => (float) $this->vendor->transactions()->where('type', 'return')->sum('debit'),
            ],
            'accounts' => FinanceService::accountOptions(),
            'openPurchases' => $this->vendor->purchaseOrders()->whereIn('status', ['ordered', 'partial', 'received'])->get()->filter(fn ($p) => $p->canPay()),
        ];
    }
}; ?>

<div>
    <x-admin.page-header :title="$vendor->name" :crumbs="['Vendors' => route('admin.vendors.index')]" :subtitle="trim($vendor->company.' · '.$vendor->phone.' · '.$vendor->city, ' ·')">
        <x-slot:actions>
            <a href="{{ route('admin.statements', ['type' => 'vendor', 'id' => $vendor->id, 'from' => $from, 'to' => $to]) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-printer-line me-1"></i>Statement</a>
            <a href="{{ route('admin.purchases.create', ['vendor' => $vendor->id]) }}" wire:navigate class="btn btn-sm btn-light-primary"><i class="ri-shopping-cart-2-line me-1"></i>New purchase</a>
            <button class="btn btn-sm btn-primary" wire:click="openPay"><i class="ri-hand-coin-line me-1"></i>Pay vendor</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="fs-12 text-muted">{{ $vendor->current_balance >= 0 ? 'We owe' : 'Advance paid' }}</div>
            <h3 class="mb-0 {{ $vendor->current_balance > 0 ? 'text-danger' : 'text-success' }}">{{ money(abs((float) $vendor->current_balance)) }}</h3>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Goods received</div><h4 class="mb-0">{{ money($stats['purchased']) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Paid</div><h4 class="mb-0">{{ money($stats['paid']) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Returned to vendor</div><h4 class="mb-0">{{ money($stats['returned']) }}</h4></div></div></div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link {{ $tab === 'ledger' ? 'active' : '' }}" wire:click="$set('tab', 'ledger')"><i class="ri-book-open-line me-1"></i>Ledger</button></li>
        <li class="nav-item"><button class="nav-link {{ $tab === 'purchases' ? 'active' : '' }}" wire:click="$set('tab', 'purchases')"><i class="ri-shopping-cart-2-line me-1"></i>Purchases</button></li>
    </ul>

    @if ($tab === 'ledger')
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <input type="date" class="form-control form-control-sm" style="width: 160px" wire:model.live="from" title="From">
                <span class="text-muted">to</span>
                <input type="date" class="form-control form-control-sm" style="width: 160px" wire:model.live="to" title="To">
                <button class="btn btn-sm btn-light ms-auto" wire:click="$set('showAdjust', true)"><i class="ri-equalizer-line me-1"></i>Adjustment</button>
            </div>
            <div class="table-responsive">
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-end">Paid / returned</th><th class="text-end">Purchased</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                        @forelse ($entries as $e)
                            <tr>
                                <td class="fs-13 text-nowrap">{{ $e->date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ ['purchase' => 'warning', 'payment' => 'success', 'return' => 'info', 'opening' => 'secondary', 'adjustment' => 'dark'][$e->type] ?? 'secondary' }}-subtle text-{{ ['purchase' => 'warning', 'payment' => 'success', 'return' => 'info', 'opening' => 'secondary', 'adjustment' => 'dark'][$e->type] ?? 'secondary' }}">{{ ucfirst($e->type) }}</span></td>
                                <td class="fs-13">
                                    @if ($e->reference instanceof \App\Models\PurchaseOrder)<a href="{{ route('admin.purchases.show', $e->reference) }}" wire:navigate>{{ $e->reference->number }}</a> · @endif
                                    {{ $e->note }} <span class="text-muted fs-12">{{ $e->user?->name }}</span>
                                </td>
                                <td class="text-end text-success text-nowrap">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td class="text-end text-danger text-nowrap">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                                <td class="text-end fw-semibold text-nowrap">{{ money($e->running_balance) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="ri-book-open-line" title="No ledger entries" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entries->hasPages())<div class="card-footer">{{ $entries->links() }}</div>@endif
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-mw mb-0">
                    <thead class="table-light"><tr><th>Purchase</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($purchases as $po)
                            <tr wire:key="vp-{{ $po->id }}">
                                <td><a href="{{ route('admin.purchases.show', $po) }}" wire:navigate class="fw-semibold">{{ $po->number }}</a><div class="fs-12 text-muted">{{ $po->bill_number ? 'Bill '.$po->bill_number : '' }}</div></td>
                                <td class="fs-13">{{ $po->order_date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ $po->statusColor() }}-subtle text-{{ $po->statusColor() }}">{{ $po->statusLabel() }}</span> @if ($po->isOverdue())<span class="badge bg-danger">Overdue</span>@endif</td>
                                <td class="text-end">{{ money($po->total) }}</td>
                                <td class="text-end">{{ money($po->paid_amount) }}</td>
                                <td class="text-end fw-semibold text-nowrap {{ $po->balanceDue() > 0 ? 'text-danger' : 'text-muted' }}">{{ $po->balanceDue() < 0 ? 'advance '.money(abs($po->balanceDue())) : money($po->balanceDue()) }}</td>
                                <td class="text-end">@if ($po->canPay())<button class="btn btn-sm btn-light-success" wire:click="openPay({{ $po->id }})">Pay</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-admin.empty icon="ri-shopping-cart-2-line" title="No purchases yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($purchases->hasPages())<div class="card-footer">{{ $purchases->links() }}</div>@endif
        </div>
    @endif

    <x-admin.modal :show="$showPay" close="$set('showPay', false)" :title="'Pay '.$vendor->name">
        <form wire:submit="savePayment" id="vpay-form" class="row g-3">
            <div class="col-12"><label class="form-label">Pay from</label><select class="form-select" wire:model="pay.account">@foreach ($accounts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" step="0.01" min="0" class="form-control @error('pay.amount') is-invalid @enderror" wire:model="pay.amount">@error('pay.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" wire:model="pay.date"></div>
            <div class="col-12">
                <label class="form-label">Against purchase <span class="text-muted fs-12">(optional)</span></label>
                <select class="form-select" wire:model="pay.purchase_id"><option value="">General payment on account</option>@foreach ($openPurchases as $po)<option value="{{ $po->id }}">{{ $po->number }} — {{ $po->status === 'ordered' ? 'advance (not received yet)' : 'due '.money($po->balanceDue()) }}</option>@endforeach</select>
            </div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="pay.note" placeholder="e.g. cheque #1234"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showPay', false)">Cancel</button>
            <button type="submit" form="vpay-form" class="btn btn-primary" wire:loading.attr="disabled">Record payment</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal :show="$showAdjust" close="$set('showAdjust', false)" title="Ledger adjustment" subtitle="Correct the balance without moving money (e.g. a missed bill or a discount the vendor gave).">
        <form wire:submit="saveAdjustment" id="vadj-form" class="row g-3">
            <div class="col-12">
                <div class="btn-group w-100">
                    <input type="radio" class="btn-check" id="va-c" value="credit" wire:model="adjust.direction"><label class="btn btn-outline-danger" for="va-c">We owe more</label>
                    <input type="radio" class="btn-check" id="va-d" value="debit" wire:model="adjust.direction"><label class="btn btn-outline-success" for="va-d">We owe less</label>
                </div>
            </div>
            <div class="col-md-5"><label class="form-label">Amount</label><input type="number" min="0" step="0.01" class="form-control @error('adjust.amount') is-invalid @enderror" wire:model="adjust.amount"></div>
            <div class="col-md-7"><label class="form-label">Reason *</label><input class="form-control @error('adjust.note') is-invalid @enderror" wire:model="adjust.note"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showAdjust', false)">Cancel</button>
            <button type="submit" form="vadj-form" class="btn btn-primary">Save adjustment</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
