<?php

use App\Models\Coupon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Coupons')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => strtoupper(\Illuminate\Support\Str::random(8)), 'description' => '', 'type' => 'percent', 'value' => 10,
            'min_order_amount' => 0, 'max_discount_amount' => '', 'usage_limit' => '', 'usage_limit_per_customer' => '',
            'applies_to' => 'all', 'starts_at' => '', 'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'), 'is_active' => true,
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $c = Coupon::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $c->{$k} ?? '';
        }
        $this->form['starts_at'] = $c->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['expires_at'] = $c->expires_at?->format('Y-m-d\TH:i') ?? '';
        foreach (['value', 'min_order_amount', 'max_discount_amount'] as $k) {
            $this->form[$k] = $c->{$k} !== null ? (float) $c->{$k} : '';
        }
    }

    public function save(): void
    {
        $this->form['code'] = strtoupper(trim($this->form['code']));
        $this->validate([
            'form.code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons', 'code')->ignore($this->editingId)],
            'form.type' => 'required|in:percent,fixed',
            'form.value' => ['required', 'numeric', 'min:1', $this->form['type'] === 'percent' ? 'max:100' : 'max:1000000'],
            'form.min_order_amount' => 'nullable|numeric|min:0',
            'form.max_discount_amount' => 'nullable|numeric|min:0',
            'form.usage_limit' => 'nullable|integer|min:1',
            'form.usage_limit_per_customer' => 'nullable|integer|min:1',
            'form.applies_to' => 'required|in:all,online,pos',
            'form.starts_at' => 'nullable|date',
            'form.expires_at' => 'nullable|date|after_or_equal:form.starts_at',
        ], [], ['form.code' => 'code', 'form.value' => 'value', 'form.expires_at' => 'expiry']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['min_order_amount'] ??= 0;
        Coupon::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Coupon '.$data['code'].' saved.');
    }

    public function toggle(int $id): void
    {
        $c = Coupon::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    public function delete(int $id): void
    {
        Coupon::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Coupon deleted.');
    }

    public function with(): array
    {
        return ['coupons' => Coupon::latest('id')->get()];
    }
}; ?>

<div>
    <x-admin.page-header title="Coupons" subtitle="Percentage or fixed-amount promo codes with limits and expiry dates.">
        <x-slot:actions><button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-coupon-3-line me-1"></i>New coupon</button></x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Code</th><th>Discount</th><th>Conditions</th><th>Where</th><th>Usage</th><th>Valid</th><th class="text-center">Active</th><th></th></tr></thead>
                <tbody>
                    @forelse ($coupons as $c)
                        @php($expired = $c->expires_at && $c->expires_at->isPast())
                        <tr wire:key="cp-{{ $c->id }}">
                            <td><span class="badge bg-dark fs-13 font-monospace">{{ $c->code }}</span><div class="fs-12 text-muted mt-1">{{ $c->description }}</div></td>
                            <td class="fw-semibold">{{ $c->label() }}</td>
                            <td class="fs-12 text-muted">
                                @if ($c->min_order_amount > 0) Min {{ money($c->min_order_amount) }}<br>@endif
                                @if ($c->max_discount_amount) Max {{ money($c->max_discount_amount) }} off @endif
                            </td>
                            <td><span class="badge bg-light text-muted">{{ ['all' => 'Online + POS', 'online' => 'Online', 'pos' => 'POS'][$c->applies_to] }}</span></td>
                            <td class="fs-13">{{ $c->used_count }}{{ $c->usage_limit ? ' / '.$c->usage_limit : '' }}</td>
                            <td class="fs-12">
                                @if ($expired)<span class="badge bg-danger-subtle text-danger">Expired</span>@endif
                                {{ $c->expires_at ? 'until '.$c->expires_at->format('d M Y') : 'No expiry' }}
                            </td>
                            <td class="text-center"><div class="form-check form-switch d-inline-block mb-0"><input type="checkbox" class="form-check-input" @checked($c->is_active) wire:click="toggle({{ $c->id }})"></div></td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-light-primary icon-btn-sm" wire:click="edit({{ $c->id }})"><i class="ri-pencil-line"></i></button>
                                <button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete coupon {{ $c->code }}?', () => $wire.delete({{ $c->id }}))"><i class="ri-delete-bin-line"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty icon="ri-coupon-3-line" title="No coupons yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit coupon' : 'New coupon'" size="lg">
        <form wire:submit="save" id="cp-form" class="row g-3">
            <div class="col-md-5"><label class="form-label">Code *</label><input class="form-control text-uppercase font-monospace @error('form.code') is-invalid @enderror" wire:model="form.code">@error('form.code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-7"><label class="form-label">Description</label><input class="form-control" wire:model="form.description" placeholder="Shown to staff and on popups"></div>
            <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" wire:model.live="form.type"><option value="percent">Percentage</option><option value="fixed">Fixed amount (Rs.)</option></select></div>
            <div class="col-md-4"><label class="form-label">Value *</label><div class="input-group"><input type="number" step="0.01" class="form-control @error('form.value') is-invalid @enderror" wire:model="form.value"><span class="input-group-text">{{ ($form['type'] ?? '') === 'percent' ? '%' : 'Rs.' }}</span></div>@error('form.value')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Applies to</label><select class="form-select" wire:model="form.applies_to"><option value="all">Online + POS</option><option value="online">Online only</option><option value="pos">POS only</option></select></div>
            <div class="col-md-6"><label class="form-label">Minimum order (Rs.)</label><input type="number" class="form-control" wire:model="form.min_order_amount"></div>
            <div class="col-md-6"><label class="form-label">Maximum discount (Rs.)</label><input type="number" class="form-control" wire:model="form.max_discount_amount" placeholder="No cap"></div>
            <div class="col-md-6"><label class="form-label">Total usage limit</label><input type="number" class="form-control" wire:model="form.usage_limit" placeholder="Unlimited"></div>
            <div class="col-md-6"><label class="form-label">Per customer limit</label><input type="number" class="form-control" wire:model="form.usage_limit_per_customer" placeholder="Unlimited"></div>
            <div class="col-md-6"><label class="form-label">Starts</label><input type="datetime-local" class="form-control" wire:model="form.starts_at"></div>
            <div class="col-md-6"><label class="form-label">Expires</label><input type="datetime-local" class="form-control @error('form.expires_at') is-invalid @enderror" wire:model="form.expires_at">@error('form.expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cp-act" wire:model="form.is_active"><label class="form-check-label" for="cp-act">Active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="cp-form" class="btn btn-primary">Save coupon</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
