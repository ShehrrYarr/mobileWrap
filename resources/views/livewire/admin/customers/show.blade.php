<?php

use App\Models\Customer;
use App\Services\CustomerLedgerService;
use App\Services\FinanceService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] class extends Component
{
    use WithPagination;

    public Customer $customer;

    #[Url(except: 'ledger')]
    public string $tab = 'ledger';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public ?string $modal = null; // pay | adjust

    public array $pay = [];

    public array $adjust = [];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function rendering($view): void
    {
        $view->title($this->customer->name);
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['from', 'to'])) {
            $this->resetPage('lp');
        }
    }

    public function openPay(): void
    {
        $this->resetErrorBag();
        $this->pay = [
            'account' => array_key_first(FinanceService::accountOptions()),
            'amount' => max(0, (float) $this->customer->current_balance),
            'note' => '',
            'print' => true,
        ];
        $this->modal = 'pay';
    }

    public function openAdjust(): void
    {
        $this->resetErrorBag();
        $this->adjust = ['direction' => 'debit', 'amount' => '', 'note' => ''];
        $this->modal = 'adjust';
    }

    public function savePayment(CustomerLedgerService $ledger): void
    {
        $this->validate(['pay.account' => 'required', 'pay.amount' => 'required|numeric|min:1', 'pay.note' => 'nullable|string|max:255'], [], ['pay.amount' => 'amount']);

        try {
            $entry = $ledger->receivePayment($this->customer, FinanceService::accountFromKey($this->pay['account']), (float) $this->pay['amount'], $this->pay['note'] ?: null);
        } catch (ValidationException $e) {
            $this->addError('pay.amount', collect($e->errors())->flatten()->first());

            return;
        }

        $this->modal = null;
        $this->customer->refresh();
        $this->dispatch('toast', type: 'success', message: money($entry->credit).' received from '.$this->customer->name.'.');
        if ($this->pay['print']) {
            $this->dispatch('open-print', url: route('admin.payment-receipt', $entry));
        }
    }

    public function saveAdjustment(CustomerLedgerService $ledger): void
    {
        $this->validate(['adjust.amount' => 'required|numeric|min:1', 'adjust.note' => 'required|string|max:255', 'adjust.direction' => 'in:debit,credit'], [], ['adjust.amount' => 'amount', 'adjust.note' => 'reason']);
        $amount = (float) $this->adjust['amount'];
        $ledger->record($this->customer, 'adjustment', $this->adjust['direction'] === 'debit' ? $amount : 0, $this->adjust['direction'] === 'credit' ? $amount : 0, null, $this->adjust['note']);
        $this->modal = null;
        $this->customer->refresh();
        $this->dispatch('toast', type: 'success', message: 'Khata adjusted.');
    }

    public function with(): array
    {
        $c = $this->customer;
        $balance = (float) $c->current_balance;
        $phone = preg_replace('/\D/', '', (string) $c->phone);

        return [
            'entries' => \App\Support\Ledger::running($c->transactions(), 'debit - credit', $balance)
                ->with('reference', 'user:id,name')
                ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
                ->orderByDesc('date')->orderByDesc('id')->paginate(25, pageName: 'lp'),
            'orders' => $this->tab === 'orders' ? $c->orders()->where('status', '!=', 'held')->paginate(15, pageName: 'op') : null,
            'stats' => [
                'orders' => $c->orders()->whereNotIn('status', ['cancelled', 'held'])->count(),
                'spent' => (float) $c->orders()->whereNotIn('status', ['cancelled', 'held'])->sum('grand_total'),
                'lastPayment' => $c->transactions()->where('type', 'payment')->first(),
            ],
            'accounts' => $this->modal === 'pay' ? FinanceService::accountOptions() : [],
            'whatsapp' => $phone && $balance > 0
                ? 'https://wa.me/'.preg_replace('/^0/', '92', $phone).'?text='.rawurlencode("Assalam o Alaikum {$c->name}, this is a friendly reminder from ".store_name().' that your khata balance is '.money($balance).'. Please clear it at your convenience. Thank you!')
                : null,
        ];
    }
}; ?>

<div x-on:open-print.window="window.open($event.detail.url ?? $event.detail[0]?.url, 'mw-print', 'width=420,height=720')">
    <x-admin.page-header :title="$customer->name" :crumbs="['Customers' => route('admin.customers.index')]" :subtitle="trim(ucfirst($customer->type).' customer · '.$customer->phone.' · '.$customer->city, ' ·')">
        <x-slot:actions>
            <a href="{{ route('admin.statements', ['type' => 'customer', 'id' => $customer->id, 'from' => $from ?: null, 'to' => $to ?: null]) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-printer-line me-1"></i>Statement</a>
            @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-sm btn-light-success"><i class="ri-whatsapp-line me-1"></i>Remind</a>@endif
            <button class="btn btn-sm btn-light" wire:click="openAdjust"><i class="ri-equalizer-line me-1"></i>Adjust</button>
            <button class="btn btn-sm btn-primary" wire:click="openPay"><i class="ri-hand-coin-line me-1"></i>Receive payment</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="fs-12 text-muted">{{ $customer->current_balance < 0 ? 'Advance / store credit' : 'Khata balance (owes you)' }}</div>
            <h3 class="mb-0 {{ $customer->current_balance > 0 ? 'text-danger' : 'text-success' }}">{{ money(abs((float) $customer->current_balance)) }}</h3>
            @if ((float) $customer->credit_limit > 0)<div class="fs-12 text-muted">Limit {{ money($customer->credit_limit) }}</div>@endif
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Orders</div><h4 class="mb-0">{{ $stats['orders'] }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Total spent</div><h4 class="mb-0">{{ money($stats['spent']) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Last payment</div>
            @if ($stats['lastPayment'])<h4 class="mb-0">{{ money($stats['lastPayment']->credit) }}</h4><div class="fs-12 text-muted">{{ $stats['lastPayment']->date->format('d M Y') }}</div>@else<h4 class="mb-0 text-muted">—</h4>@endif
        </div></div></div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link {{ $tab === 'ledger' ? 'active' : '' }}" wire:click="$set('tab', 'ledger')"><i class="ri-book-open-line me-1"></i>Khata ledger</button></li>
        <li class="nav-item"><button class="nav-link {{ $tab === 'orders' ? 'active' : '' }}" wire:click="$set('tab', 'orders')"><i class="ri-file-list-3-line me-1"></i>Orders</button></li>
    </ul>

    @if ($tab === 'ledger')
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <input type="date" class="form-control form-control-sm" style="width: 160px" wire:model.live="from" title="From">
                <span class="text-muted">to</span>
                <input type="date" class="form-control form-control-sm" style="width: 160px" wire:model.live="to" title="To">
            </div>
            <div class="table-responsive">
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-end">Sale / charge</th><th class="text-end">Paid / returned</th><th class="text-end">Balance</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($entries as $e)
                            @php($color = ['sale' => 'warning', 'payment' => 'success', 'return' => 'info', 'refund' => 'info', 'opening' => 'secondary', 'adjustment' => 'dark'][$e->type] ?? 'secondary')
                            <tr wire:key="ce-{{ $e->id }}">
                                <td class="fs-13 text-nowrap">{{ $e->date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ $color }}-subtle text-{{ $color }}">{{ ucfirst($e->type) }}</span></td>
                                <td class="fs-13">
                                    @if ($e->reference instanceof \App\Models\Order)<a href="{{ route('admin.orders.show', $e->reference) }}" wire:navigate>{{ $e->reference->number }}</a> · @endif
                                    @if ($e->reference instanceof \App\Models\OrderReturn){{ $e->reference->number }} · @endif
                                    {{ $e->note }} <span class="text-muted fs-12">{{ $e->user?->name }}</span>
                                </td>
                                <td class="text-end text-danger text-nowrap">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td class="text-end text-success text-nowrap">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                                <td class="text-end fw-semibold text-nowrap">{{ money($e->running_balance) }}</td>
                                <td class="text-end">@if ($e->type === 'payment')<a href="{{ route('admin.payment-receipt', [$e, 'autoprint' => 0]) }}" target="_blank" class="btn btn-sm btn-light icon-btn-sm" title="Payment receipt"><i class="ri-receipt-line"></i></a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-admin.empty icon="ri-book-open-line" title="No khata entries" text="Credit sales from the POS and payments you receive will show here." /></td></tr>
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
                    <thead class="table-light"><tr><th>Order</th><th>Date</th><th>Channel</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $o)
                            <tr wire:key="co-{{ $o->id }}">
                                <td><a href="{{ route('admin.orders.show', $o) }}" wire:navigate class="fw-semibold">{{ $o->number }}</a></td>
                                <td class="fs-13">{{ $o->placed_at?->format('d M Y h:i a') }}</td>
                                <td><span class="badge bg-light text-muted">{{ $o->channel === 'pos' ? 'POS' : 'Online' }}</span></td>
                                <td><span class="badge bg-{{ $o->statusColor() }}-subtle text-{{ $o->statusColor() }}">{{ $o->statusLabel() }}</span></td>
                                <td class="text-end">{{ money($o->grand_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty icon="ri-file-list-3-line" title="No orders yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
        </div>
    @endif

    <x-admin.modal :show="$modal === 'pay'" close="$set('modal', null)" :title="'Receive payment — '.$customer->name" :subtitle="'Current khata balance: '.money($customer->current_balance)">
        <form wire:submit="savePayment" id="cpay-form" class="row g-3">
            <div class="col-12"><label class="form-label">Received into</label><select class="form-select" wire:model="pay.account">@foreach ($accounts as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Amount (Rs.)</label><input type="number" step="0.01" min="0" class="form-control form-control-lg @error('pay.amount') is-invalid @enderror" wire:model="pay.amount">@error('pay.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="pay.note" placeholder="Optional"></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="cp-print" wire:model="pay.print"><label class="form-check-label" for="cp-print">Print payment receipt</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="cpay-form" class="btn btn-primary" wire:loading.attr="disabled">Receive payment</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal :show="$modal === 'adjust'" close="$set('modal', null)" title="Khata adjustment" subtitle="Correct the balance without moving money (e.g. an old balance from your paper khata, or a write-off).">
        <form wire:submit="saveAdjustment" id="cadj-form" class="row g-3">
            <div class="col-12">
                <div class="btn-group w-100">
                    <input type="radio" class="btn-check" id="ca-d" value="debit" wire:model="adjust.direction"><label class="btn btn-outline-danger" for="ca-d">Owes more</label>
                    <input type="radio" class="btn-check" id="ca-c" value="credit" wire:model="adjust.direction"><label class="btn btn-outline-success" for="ca-c">Owes less</label>
                </div>
            </div>
            <div class="col-md-5"><label class="form-label">Amount</label><input type="number" min="0" step="0.01" class="form-control @error('adjust.amount') is-invalid @enderror" wire:model="adjust.amount"></div>
            <div class="col-md-7"><label class="form-label">Reason *</label><input class="form-control @error('adjust.note') is-invalid @enderror" wire:model="adjust.note"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="cadj-form" class="btn btn-primary">Save adjustment</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
