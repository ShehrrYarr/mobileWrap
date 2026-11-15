<?php

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\FinancialTransaction;
use App\Services\FinanceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Bank & cash')] class extends Component
{
    public ?string $modal = null; // transfer | move

    public array $form = [];

    public function openTransfer(?string $from = null): void
    {
        $keys = array_keys(FinanceService::accountOptions());
        $from ??= $keys[0] ?? null;
        $this->form = ['from' => $from, 'to' => collect($keys)->first(fn ($k) => $k !== $from), 'amount' => '', 'date' => now()->toDateString(), 'note' => ''];
        $this->resetErrorBag();
        $this->modal = 'transfer';
    }

    /** Owner puts money in (capital) or takes money out (drawings) — or any other correction. */
    public function openMove(string $account, string $direction): void
    {
        $this->form = ['account' => $account, 'direction' => $direction, 'amount' => '', 'date' => now()->toDateString(), 'note' => ''];
        $this->resetErrorBag();
        $this->modal = 'move';
    }

    public function saveTransfer(FinanceService $finance): void
    {
        $this->validate([
            'form.from' => 'required',
            'form.to' => 'required|different:form.from',
            'form.amount' => 'required|numeric|min:1',
            'form.date' => 'required|date',
        ], ['form.to.different' => 'Choose two different accounts.'], ['form.amount' => 'amount']);

        $from = FinanceService::accountFromKey($this->form['from']);
        $to = FinanceService::accountFromKey($this->form['to']);
        try {
            $finance->transfer($from, $to, (float) $this->form['amount'], $this->form['note'] ?: null, $this->form['date']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('form.amount', collect($e->errors())->flatten()->first());

            return;
        }
        $this->modal = null;
        $this->dispatch('toast', type: 'success', message: money($this->form['amount']).' moved.');
    }

    public function saveMove(FinanceService $finance): void
    {
        $this->validate([
            'form.amount' => 'required|numeric|min:1',
            'form.date' => 'required|date',
            'form.note' => 'required|string|max:255',
            'form.direction' => 'in:deposit,withdrawal',
        ], [], ['form.amount' => 'amount', 'form.note' => 'reason']);

        $account = FinanceService::accountFromKey($this->form['account']);
        $amount = (float) $this->form['amount'];
        $in = $this->form['direction'] === 'deposit';
        if (! $in) {
            try {
                FinanceService::ensureFunds($account, $amount);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->addError('form.amount', collect($e->errors())->flatten()->first());

                return;
            }
        }
        $finance->record($account, $this->form['direction'], $in ? $amount : 0, $in ? 0 : $amount, null, null, $this->form['note'], $this->form['date']);
        $this->modal = null;
        $this->dispatch('toast', type: 'success', message: ($in ? 'Money added: ' : 'Money withdrawn: ').money($amount).'.');
    }

    public function with(): array
    {
        $accounts = collect()
            ->concat(CashDrawer::where('is_active', true)->get()->map(fn ($a) => ['key' => 'cash-'.$a->id, 'pick' => 'cash:'.$a->id, 'model' => $a, 'name' => 'Cash in hand', 'sub' => $a->name, 'icon' => 'ri-money-dollar-circle-line', 'tone' => 'success']))
            ->concat(BankAccount::orderByDesc('is_active')->orderBy('bank_name')->get()->map(fn ($a) => ['key' => 'bank-'.$a->id, 'pick' => 'bank:'.$a->id, 'model' => $a, 'name' => $a->bank_name, 'sub' => $a->account_title.' · …'.substr($a->account_number, -4), 'icon' => 'ri-bank-line', 'tone' => 'primary']));

        $today = FinancialTransaction::whereDate('date', today())->where('type', '!=', 'opening')
            ->selectRaw('account_type, account_id, SUM(debit) d, SUM(credit) c')->groupBy('account_type', 'account_id')->get()
            ->keyBy(fn ($r) => $r->account_type.'#'.$r->account_id);

        return [
            'accounts' => $accounts->map(function ($a) use ($today) {
                $t = $today[$a['model']->getMorphClass().'#'.$a['model']->id] ?? null;

                return $a + ['in' => (float) ($t->d ?? 0), 'out' => (float) ($t->c ?? 0)];
            }),
            'total' => (float) $accounts->filter(fn ($a) => $a['model']->is_active)->sum(fn ($a) => (float) $a['model']->current_balance),
            'recent' => FinancialTransaction::with('account', 'user:id,name')->latest('id')->limit(12)->get(),
            'options' => $this->modal ? FinanceService::accountOptions() : [],
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Bank & cash" subtitle="Where your money is right now. Every sale, payment and expense posts here automatically.">
        <x-slot:actions>
            <span class="badge bg-success-subtle text-success fs-13 py-2 px-3">Total: {{ money($total) }}</span>
            <a href="{{ route('admin.finance.expenses') }}" wire:navigate class="btn btn-sm btn-light"><i class="ri-wallet-3-line me-1"></i>Expenses</a>
            <button class="btn btn-sm btn-primary" wire:click="openTransfer"><i class="ri-arrow-left-right-line me-1"></i>Transfer</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        @foreach ($accounts as $a)
            <div class="col-md-6 col-xl-3" wire:key="acc-{{ $a['key'] }}">
                <div class="card mb-0 h-100 {{ $a['model']->is_active ? '' : 'opacity-50' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="avatar avatar-sm avatar-item bg-{{ $a['tone'] }}-subtle text-{{ $a['tone'] }} rounded-circle"><i class="{{ $a['icon'] }}"></i></span>
                            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">{{ $a['name'] }}</div><div class="fs-12 text-muted text-truncate">{{ $a['sub'] }}</div></div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light icon-btn-sm" data-bs-toggle="dropdown" aria-label="Account actions"><i class="ri-more-2-fill"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item" wire:click="openTransfer('{{ $a['pick'] }}')"><i class="ri-arrow-left-right-line me-2"></i>Transfer out</button></li>
                                    <li><button class="dropdown-item" wire:click="openMove('{{ $a['pick'] }}', 'deposit')"><i class="ri-add-circle-line me-2"></i>Add money</button></li>
                                    <li><button class="dropdown-item" wire:click="openMove('{{ $a['pick'] }}', 'withdrawal')"><i class="ri-indeterminate-circle-line me-2"></i>Withdraw money</button></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.statements', ['type' => 'account', 'id' => $a['key'], 'from' => now()->startOfMonth()->toDateString()]) }}" target="_blank"><i class="ri-printer-line me-2"></i>Print this month</a></li>
                                </ul>
                            </div>
                        </div>
                        <h3 class="mb-1 {{ (float) $a['model']->current_balance < 0 ? 'text-danger' : '' }}">{{ money($a['model']->current_balance) }}</h3>
                        <div class="fs-12 text-muted mb-2">Today: <span class="text-success">+{{ money($a['in']) }}</span> · <span class="text-danger">−{{ money($a['out']) }}</span></div>
                        <a href="{{ route('admin.finance.ledger', ['account' => $a['key']]) }}" wire:navigate class="btn btn-sm btn-light-primary w-100"><i class="ri-book-open-line me-1"></i>Ledger</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center"><h6 class="card-title mb-0 flex-grow-1">Latest movements</h6><a href="{{ route('admin.finance.ledger') }}" wire:navigate class="fs-13">All transactions <i class="ri-arrow-right-line"></i></a></div>
        <div class="table-responsive">
            <table class="table table-mw mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Account</th><th>Details</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse ($recent as $t)
                        <tr>
                            <td class="fs-13 text-nowrap">{{ $t->date->format('d M') }}@if ($t->date->isSameDay($t->created_at))<div class="fs-11 text-muted">{{ $t->created_at->format('h:i a') }}</div>@endif</td>
                            <td class="fs-13">{{ $t->account instanceof \App\Models\CashDrawer ? 'Cash' : $t->account?->bank_name }}</td>
                            <td class="fs-13"><span class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $t->type) }}</span> · {{ $t->note }} <span class="text-muted fs-12">{{ $t->user?->name }}</span></td>
                            <td class="text-end text-success">{{ $t->debit > 0 ? money($t->debit) : '' }}</td>
                            <td class="text-end text-danger">{{ $t->credit > 0 ? money($t->credit) : '' }}</td>
                            <td class="text-end">{{ money($t->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="ri-exchange-funds-line" title="No transactions yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.modal :show="$modal === 'transfer'" close="$set('modal', null)" title="Transfer between accounts" subtitle="e.g. deposit the day’s cash into the bank, or withdraw cash for the counter.">
        <form wire:submit="saveTransfer" id="tr-form" class="row g-3">
            <div class="col-12"><label class="form-label">From</label><select class="form-select" wire:model="form.from">@foreach ($options as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">To</label><select class="form-select @error('form.to') is-invalid @enderror" wire:model="form.to">@foreach ($options as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>@error('form.to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" min="0" step="0.01" class="form-control @error('form.amount') is-invalid @enderror" wire:model="form.amount">@error('form.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" wire:model="form.date"></div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="form.note" placeholder="Optional"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="tr-form" class="btn btn-primary" wire:loading.attr="disabled">Transfer</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal :show="$modal === 'move'" close="$set('modal', null)" :title="($form['direction'] ?? '') === 'deposit' ? 'Add money' : 'Withdraw money'" :subtitle="($form['direction'] ?? '') === 'deposit' ? 'Money put into the business (owner capital, loan received…). Not a sale.' : 'Money taken out that isn’t an expense or vendor payment (owner drawings, loan repaid…).'">
        <form wire:submit="saveMove" id="mv-form" class="row g-3">
            <div class="col-12"><label class="form-label">Account</label><select class="form-select" wire:model="form.account">@foreach ($options as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" min="0" step="0.01" class="form-control @error('form.amount') is-invalid @enderror" wire:model="form.amount">@error('form.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" wire:model="form.date"></div>
            <div class="col-12"><label class="form-label">Reason *</label><input class="form-control @error('form.note') is-invalid @enderror" wire:model="form.note" placeholder="e.g. Owner capital">@error('form.note')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="mv-form" class="btn btn-primary" wire:loading.attr="disabled">Save</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
