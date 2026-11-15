<?php

use App\Models\Vendor;
use App\Services\VendorLedgerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Vendors')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function updated($prop): void
    {
        if (in_array($prop, ['search', 'filter'])) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'company' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'opening_balance' => 0, 'notes' => '', 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $v = Vendor::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $v->{$k} ?? '';
        }
        $this->form['opening_balance'] = (float) $v->opening_balance;
    }

    public function save(VendorLedgerService $ledger): void
    {
        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.email' => 'nullable|email',
            'form.phone' => 'nullable|string|max:30',
            'form.opening_balance' => 'numeric',
        ], [], ['form.name' => 'name']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();

        if ($this->editingId) {
            unset($data['opening_balance']); // fixed once the ledger exists
            Vendor::findOrFail($this->editingId)->update($data);
        } else {
            $vendor = Vendor::create($data + ['current_balance' => 0]);
            $opening = (float) $this->form['opening_balance'];
            if ($opening != 0.0) {
                $ledger->record($vendor, 'opening', max(-$opening, 0), max($opening, 0), null, 'Opening balance');
            }
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Vendor saved.');
    }

    public function with(): array
    {
        return [
            'vendors' => Vendor::query()
                ->withCount('purchaseOrders')
                ->withSum(['purchaseOrders as purchased' => fn ($q) => $q->whereIn('status', ['partial', 'received'])], 'total')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('company', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%")))
                ->when($this->filter === 'payable', fn ($q) => $q->where('current_balance', '>', 0))
                ->orderByDesc('current_balance')->orderBy('name')->paginate(20),
            'totalPayable' => (float) Vendor::where('current_balance', '>', 0)->sum('current_balance'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Vendors" subtitle="Suppliers, what you owe them and their purchase history.">
        <x-slot:actions>
            <span class="badge bg-danger-subtle text-danger fs-13 py-2 px-3">Payable: {{ money($totalPayable) }}</span>
            <a href="{{ route('admin.purchases.create') }}" wire:navigate class="btn btn-sm btn-light-primary"><i class="ri-shopping-cart-2-line me-1"></i>New purchase</a>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>New vendor</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-5"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Name, company or phone" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
                <div class="col-md-3"><select class="form-select" wire:model.live="filter"><option value="">All vendors</option><option value="payable">We owe money</option></select></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Vendor</th><th>Contact</th><th class="text-center">Purchases</th><th class="text-end">Purchased</th><th class="text-end">Balance</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vendors as $v)
                        <tr wire:key="v-{{ $v->id }}">
                            <td>
                                <a href="{{ route('admin.vendors.show', $v) }}" wire:navigate class="fw-semibold">{{ $v->name }}</a>
                                <div class="fs-12 text-muted">{{ $v->company }} @unless ($v->is_active)<span class="badge bg-light text-muted">inactive</span>@endunless</div>
                            </td>
                            <td class="fs-13">{{ $v->phone }}<div class="text-muted">{{ $v->city }}</div></td>
                            <td class="text-center">{{ $v->purchase_orders_count }}</td>
                            <td class="text-end">{{ money($v->purchased ?? 0) }}</td>
                            <td class="text-end fw-semibold {{ $v->current_balance > 0 ? 'text-danger' : ($v->current_balance < 0 ? 'text-success' : 'text-muted') }}">
                                {{ money(abs((float) $v->current_balance)) }}
                                <div class="fs-11 fw-normal text-muted">{{ $v->current_balance > 0 ? 'we owe' : ($v->current_balance < 0 ? 'advance paid' : 'settled') }}</div>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.vendors.show', $v) }}" wire:navigate class="btn btn-sm btn-light-primary icon-btn-sm" title="Ledger"><i class="ri-book-open-line"></i></a>
                                <button class="btn btn-sm btn-light icon-btn-sm" wire:click="edit({{ $v->id }})" title="Edit"><i class="ri-pencil-line"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="ri-truck-line" title="No vendors yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($vendors->hasPages())<div class="card-footer">{{ $vendors->links() }}</div>@endif
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit vendor' : 'New vendor'" size="lg">
        <form wire:submit="save" id="vendor-form" class="row g-3">
            <div class="col-md-6"><label class="form-label">Name *</label><input class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" wire:model="form.company"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" wire:model="form.phone"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email"></div>
            <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" wire:model="form.address"></div>
            <div class="col-md-4"><label class="form-label">City</label><input class="form-control" wire:model="form.city"></div>
            <div class="col-md-6">
                <label class="form-label">Opening balance (Rs.)</label>
                <input type="number" step="0.01" class="form-control" wire:model="form.opening_balance" @disabled($editingId)>
                <div class="form-text fs-12">{{ $editingId ? 'Fixed after creation — use payments or the ledger.' : 'Positive = you already owe this vendor; negative = you paid in advance.' }}</div>
            </div>
            <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" wire:model="form.notes"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="vf-act" wire:model="form.is_active"><label class="form-check-label" for="vf-act">Active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="vendor-form" class="btn btn-primary">Save vendor</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
