<?php

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Customers')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function updated($prop): void
    {
        if (in_array($prop, ['search', 'type'])) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['type' => 'retail', 'name' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'credit_limit' => 0, 'notes' => '', 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $c = Customer::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $c->{$k} ?? '';
        }
        $this->form['credit_limit'] = (float) $c->credit_limit;
    }

    public function save(): void
    {
        $this->form['phone'] = Customer::normalizePhone($this->form['phone']) ?? '';
        $this->validate([
            'form.type' => 'required|in:retail,wholesale',
            'form.name' => 'required|string|max:120',
            'form.phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($this->editingId)],
            'form.email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($this->editingId)],
            'form.credit_limit' => 'numeric|min:0',
        ], [], ['form.name' => 'name', 'form.phone' => 'phone', 'form.email' => 'email']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        Customer::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Customer saved.');
    }

    public function with(): array
    {
        return [
            'customers' => Customer::query()
                ->withCount('orders')
                ->withSum(['orders as spent' => fn ($q) => $q->whereNotIn('status', ['cancelled', 'held'])], 'grand_total')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
                ->when($this->type, fn ($q) => $q->where('type', $this->type))
                ->latest('id')->paginate(20),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Customers" subtitle="Storefront accounts, guests from online orders, and POS / wholesale (khata) customers.">
        <x-slot:actions>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-user-add-line me-1"></i>New customer</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-5"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Name, phone or email" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="type"><option value="">All types</option><option value="retail">Retail</option><option value="wholesale">Wholesale</option></select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Customer</th><th>Contact</th><th>Type</th><th class="text-center">Orders</th><th class="text-end">Spent</th><th class="text-end">Khata balance</th><th>Last order</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customers as $c)
                        <tr wire:key="cu-{{ $c->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm avatar-item bg-primary-subtle text-primary fw-semibold">{{ Str::upper(Str::substr($c->name, 0, 1)) }}</span>
                                    <div><div class="fw-medium">{{ $c->name }}</div>
                                        @if ($c->hasAccount())<span class="badge bg-primary-subtle text-primary fs-10">Online account</span>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="fs-13">{{ $c->phone }}<div class="text-muted">{{ $c->email }}</div></td>
                            <td><span class="badge {{ $c->type === 'wholesale' ? 'bg-warning-subtle text-warning' : 'bg-light text-muted' }}">{{ ucfirst($c->type) }}</span></td>
                            <td class="text-center">{{ $c->orders_count }}</td>
                            <td class="text-end">{{ money($c->spent ?? 0) }}</td>
                            <td class="text-end {{ $c->current_balance > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ money($c->current_balance) }}</td>
                            <td class="fs-13 text-muted">{{ $c->last_order_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-end"><button class="btn btn-sm btn-light-primary icon-btn-sm" wire:click="edit({{ $c->id }})"><i class="ri-pencil-line"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty icon="ri-group-line" title="No customers found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())<div class="card-footer">{{ $customers->links() }}</div>@endif
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit customer' : 'New customer'" size="lg">
        <form wire:submit="save" id="cu-form" class="row g-3">
            <div class="col-md-8"><label class="form-label">Name *</label><input class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" wire:model="form.type"><option value="retail">Retail</option><option value="wholesale">Wholesale (wholesale prices)</option></select></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control @error('form.phone') is-invalid @enderror" wire:model="form.phone" placeholder="03XX-XXXXXXX">@error('form.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control @error('form.email') is-invalid @enderror" wire:model="form.email">@error('form.email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" wire:model="form.address"></div>
            <div class="col-md-4"><label class="form-label">City</label><input class="form-control" wire:model="form.city"></div>
            <div class="col-md-4"><label class="form-label">Credit (khata) limit</label><input type="number" min="0" class="form-control" wire:model="form.credit_limit"></div>
            <div class="col-md-8"><label class="form-label">Notes</label><input class="form-control" wire:model="form.notes"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cu-act" wire:model="form.is_active"><label class="form-check-label" for="cu-act">Active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="cu-form" class="btn btn-primary">Save customer</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
