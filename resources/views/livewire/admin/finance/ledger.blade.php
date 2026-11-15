<?php

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\FinancialTransaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Account ledger')] class extends Component
{
    use WithPagination;

    /** '' (all accounts) | cash-1 | bank-2 */
    #[Url(except: '')]
    public string $account = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        if (! $this->from && ! $this->to) {
            $this->from = now()->startOfMonth()->toDateString();
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    protected function selected(): BankAccount|CashDrawer|null
    {
        if (! $this->account) {
            return null;
        }
        [$kind, $id] = array_pad(explode('-', $this->account, 2), 2, 0);

        return $kind === 'cash' ? CashDrawer::find($id) : BankAccount::find($id);
    }

    public function with(): array
    {
        $selected = $this->selected();
        $base = $selected
            ? \App\Support\Ledger::running(FinancialTransaction::where('account_type', $selected->getMorphClass())->where('account_id', $selected->id), 'debit - credit', (float) $selected->current_balance)
            : \App\Support\Ledger::running(FinancialTransaction::query(), 'debit - credit', null, 'account_type, account_id');
        $query = $base
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
            ->when($this->search, fn ($q) => $q->where('note', 'like', "%{$this->search}%"));

        $sums = $query->clone()->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();

        $opening = null;
        $closing = null;
        if ($selected && ! $this->type && ! $this->search) {
            $s = \App\Support\Statement::forAccount($selected, $this->from ?: null, $this->to ?: null);
            $opening = $s->opening;
            $closing = $s->closing;
        }

        return [
            'rows' => $query->with('account', 'user:id,name', 'reference', 'party')->orderByDesc('date')->orderByDesc('id')->paginate(30),
            'cash' => CashDrawer::orderBy('id')->get(),
            'banks' => BankAccount::orderBy('bank_name')->get(),
            'selected' => $selected,
            'totalIn' => (float) $sums->d,
            'totalOut' => (float) $sums->c,
            'opening' => $opening,
            'closing' => $closing,
            'types' => FinancialTransaction::query()->distinct()->orderBy('type')->pluck('type'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Account ledger" :crumbs="['Bank & cash' => route('admin.finance.accounts')]" subtitle="Every rupee in and out of your bank accounts and cash drawer.">
        <x-slot:actions>
            @if ($selected)
                <a href="{{ route('admin.statements', ['type' => 'account', 'id' => $account, 'from' => $from ?: null, 'to' => $to ?: null]) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-printer-line me-1"></i>Print statement</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="account">
                        <option value="">All accounts</option>
                        @foreach ($cash as $c)<option value="cash-{{ $c->id }}">Cash — {{ $c->name }}</option>@endforeach
                        @foreach ($banks as $b)<option value="bank-{{ $b->id }}">{{ $b->bank_name }} …{{ substr($b->account_number, -4) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model.live="type"><option value="">All types</option>@foreach ($types as $t)<option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>@endforeach</select>
                </div>
                <div class="col-6 col-md-2"><input type="date" class="form-control" wire:model.live="from" title="From"></div>
                <div class="col-6 col-md-2"><input type="date" class="form-control" wire:model.live="to" title="To"></div>
                <div class="col-md-3"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Search notes" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
            </div>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="d-flex flex-wrap gap-4">
                @if ($opening !== null)<div><div class="fs-12 text-muted">Opening balance</div><div class="fw-bold fs-5">{{ money($opening) }}</div></div>@endif
                <div><div class="fs-12 text-muted">Money in</div><div class="fw-bold fs-5 text-success">{{ money($totalIn) }}</div></div>
                <div><div class="fs-12 text-muted">Money out</div><div class="fw-bold fs-5 text-danger">{{ money($totalOut) }}</div></div>
                <div><div class="fs-12 text-muted">Net</div><div class="fw-bold fs-5">{{ money($totalIn - $totalOut) }}</div></div>
                @if ($closing !== null)<div><div class="fs-12 text-muted">Closing balance</div><div class="fw-bold fs-5">{{ money($closing) }}</div></div>@endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-mw mb-0">
                <thead class="table-light"><tr><th>Date</th>@unless ($selected)<th>Account</th>@endunless<th>Type</th><th>Details</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse ($rows as $t)
                        <tr wire:key="ft-{{ $t->id }}">
                            <td class="fs-13 text-nowrap">{{ $t->date->format('d M Y') }}</td>
                            @unless ($selected)<td class="fs-13">{{ $t->account instanceof \App\Models\CashDrawer ? 'Cash' : $t->account?->bank_name }}</td>@endunless
                            <td><span class="badge bg-{{ $t->debit > 0 ? 'success' : 'danger' }}-subtle text-{{ $t->debit > 0 ? 'success' : 'danger' }} text-capitalize">{{ str_replace('_', ' ', $t->type) }}</span></td>
                            <td class="fs-13">
                                @if ($t->reference instanceof \App\Models\Order)<a href="{{ route('admin.orders.show', $t->reference) }}" wire:navigate>{{ $t->reference->number }}</a> ·
                                @elseif ($t->reference instanceof \App\Models\PurchaseOrder)<a href="{{ route('admin.purchases.show', $t->reference) }}" wire:navigate>{{ $t->reference->number }}</a> ·
                                @endif
                                @if ($t->party instanceof \App\Models\Customer)<a href="{{ route('admin.customers.show', $t->party) }}" wire:navigate class="text-body fw-medium">{{ $t->party->name }}</a> ·
                                @elseif ($t->party instanceof \App\Models\Vendor)<a href="{{ route('admin.vendors.show', $t->party) }}" wire:navigate class="text-body fw-medium">{{ $t->party->name }}</a> ·
                                @endif
                                {{ $t->note }} <span class="text-muted fs-12">{{ $t->user?->name }}</span>
                            </td>
                            <td class="text-end text-success">{{ $t->debit > 0 ? money($t->debit) : '' }}</td>
                            <td class="text-end text-danger">{{ $t->credit > 0 ? money($t->credit) : '' }}</td>
                            <td class="text-end">{{ money($t->running_balance) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty icon="ri-exchange-funds-line" title="No transactions in this period" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    </div>
</div>
