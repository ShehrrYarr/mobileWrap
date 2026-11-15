<?php

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Services\FinanceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Bank accounts')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public ?string $ledgerFor = null; // "bank:1" | "cash:1"

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['bank_name' => '', 'account_title' => store_name(), 'account_number' => '', 'iban' => '', 'branch' => '', 'opening_balance' => 0, 'show_on_checkout' => true, 'is_active' => true, 'notes' => ''];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $a = BankAccount::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $a->{$k} ?? '';
        }
        $this->form['opening_balance'] = (float) $a->opening_balance;
    }

    public function save(FinanceService $finance): void
    {
        $this->validate([
            'form.bank_name' => 'required|string|max:80',
            'form.account_title' => 'required|string|max:120',
            'form.account_number' => 'required|string|max:60',
            'form.iban' => 'nullable|string|max:34',
            'form.opening_balance' => 'numeric',
        ], [], ['form.bank_name' => 'bank name', 'form.account_title' => 'account title', 'form.account_number' => 'account number']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();

        if ($this->editingId) {
            unset($data['opening_balance']); // opening balance is fixed once the ledger exists
            BankAccount::findOrFail($this->editingId)->update($data);
        } else {
            $account = BankAccount::create($data + ['current_balance' => 0]);
            $finance->opening($account, (float) $this->form['opening_balance']);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Bank account saved.');
    }

    public function toggle(int $id, string $field): void
    {
        abort_unless(in_array($field, ['is_active', 'show_on_checkout']), 403);
        $a = BankAccount::findOrFail($id);
        $a->update([$field => ! $a->{$field}]);
    }

    public function with(): array
    {
        $ledger = null;
        if ($this->ledgerFor) {
            [$kind, $id] = explode(':', $this->ledgerFor);
            $account = $kind === 'cash' ? CashDrawer::find($id) : BankAccount::find($id);
            $ledger = ['account' => $account, 'rows' => $account?->transactions()->with('reference', 'user:id,name')->limit(50)->get() ?? collect()];
        }

        return [
            'accounts' => BankAccount::orderByDesc('is_active')->orderBy('bank_name')->get(),
            'drawers' => CashDrawer::all(),
            'ledger' => $ledger,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Bank accounts & cash" subtitle="Unlimited bank accounts plus the Cash In Hand drawer. Verified online payments post here automatically.">
        <x-slot:actions><button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>Add bank account</button></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        @foreach ($drawers as $d)
            <div class="col-md-6 col-xl-4">
                <div class="card mb-0 h-100 border-success border-opacity-25">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="mw-stat-icon bg-success-subtle text-success"><i class="ri-wallet-3-line"></i></span>
                            <div><h6 class="mb-0">Cash In Hand</h6><span class="fs-12 text-muted">{{ $d->name }}</span></div>
                        </div>
                        <h3 class="mb-0">{{ money($d->current_balance) }}</h3>
                    </div>
                    <div class="card-footer"><button class="btn btn-sm btn-light-primary" wire:click="$set('ledgerFor', 'cash:{{ $d->id }}')"><i class="ri-book-open-line me-1"></i>Ledger</button></div>
                </div>
            </div>
        @endforeach
        @foreach ($accounts as $a)
            <div class="col-md-6 col-xl-4" wire:key="ba-{{ $a->id }}">
                <div class="card mb-0 h-100 {{ $a->is_active ? '' : 'opacity-50' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="mw-stat-icon bg-primary-subtle text-primary"><i class="ri-bank-line"></i></span>
                            <div class="min-w-0"><h6 class="mb-0 text-truncate">{{ $a->bank_name }}</h6><span class="fs-12 text-muted">{{ $a->account_title }} · {{ $a->branch }}</span></div>
                        </div>
                        <div class="font-monospace fs-13 mb-1">{{ $a->account_number }}</div>
                        @if ($a->iban)<div class="font-monospace fs-12 text-muted mb-2">{{ $a->iban }}</div>@endif
                        <h3 class="mb-2">{{ money($a->current_balance) }}</h3>
                        <div class="d-flex gap-3 fs-13">
                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="sc-{{ $a->id }}" @checked($a->show_on_checkout) wire:click="toggle({{ $a->id }}, 'show_on_checkout')"><label class="form-check-label" for="sc-{{ $a->id }}">Show on checkout</label></div>
                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="ia-{{ $a->id }}" @checked($a->is_active) wire:click="toggle({{ $a->id }}, 'is_active')"><label class="form-check-label" for="ia-{{ $a->id }}">Active</label></div>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button class="btn btn-sm btn-light-primary" wire:click="$set('ledgerFor', 'bank:{{ $a->id }}')"><i class="ri-book-open-line me-1"></i>Ledger</button>
                        <button class="btn btn-sm btn-light" wire:click="edit({{ $a->id }})"><i class="ri-pencil-line me-1"></i>Edit</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit bank account' : 'Add bank account'" size="lg">
        <form wire:submit="save" id="ba-form" class="row g-3">
            <div class="col-md-6"><label class="form-label">Bank name *</label><input class="form-control @error('form.bank_name') is-invalid @enderror" wire:model="form.bank_name" placeholder="e.g. Meezan Bank">@error('form.bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Account title *</label><input class="form-control @error('form.account_title') is-invalid @enderror" wire:model="form.account_title"></div>
            <div class="col-md-6"><label class="form-label">Account number *</label><input class="form-control font-monospace @error('form.account_number') is-invalid @enderror" wire:model="form.account_number"></div>
            <div class="col-md-6"><label class="form-label">IBAN</label><input class="form-control font-monospace" wire:model="form.iban"></div>
            <div class="col-md-6"><label class="form-label">Branch</label><input class="form-control" wire:model="form.branch"></div>
            <div class="col-md-6"><label class="form-label">Opening balance (Rs.)</label><input type="number" class="form-control" wire:model="form.opening_balance" @disabled($editingId)>@if ($editingId)<div class="form-text fs-12">Fixed after creation — post adjustments through the ledger.</div>@endif</div>
            <div class="col-12"><label class="form-label">Notes</label><input class="form-control" wire:model="form.notes"></div>
            <div class="col-12 d-flex gap-4">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-sc" wire:model="form.show_on_checkout"><label class="form-check-label" for="f-sc">Show to customers on checkout</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-ia" wire:model="form.is_active"><label class="form-check-label" for="f-ia">Active</label></div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="ba-form" class="btn btn-primary">Save account</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal :show="(bool) $ledgerFor" close="$set('ledgerFor', null)" :title="'Ledger — '.($ledger ? ($ledger['account'] instanceof \App\Models\CashDrawer ? 'Cash In Hand' : $ledger['account']?->bank_name) : '')" size="xl">
        @if ($ledger)
            <div class="table-responsive">
                <table class="table table-sm table-mw mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Details</th><th>By</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                        @forelse ($ledger['rows'] as $t)
                            <tr>
                                <td class="fs-13 text-nowrap">{{ $t->date->format('d M Y') }}</td>
                                <td><span class="badge bg-light text-body">{{ ucfirst(str_replace('_', ' ', $t->type)) }}</span></td>
                                <td class="fs-13">{{ $t->note }}</td>
                                <td class="fs-13">{{ $t->user?->name ?? 'System' }}</td>
                                <td class="text-end text-success">{{ $t->debit > 0 ? money($t->debit) : '' }}</td>
                                <td class="text-end text-danger">{{ $t->credit > 0 ? money($t->credit) : '' }}</td>
                                <td class="text-end fw-semibold">{{ money($t->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No transactions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.modal>
</div>
