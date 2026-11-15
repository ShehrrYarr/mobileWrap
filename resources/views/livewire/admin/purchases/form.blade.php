<?php

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\PurchaseService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] class extends Component
{
    public ?PurchaseOrder $purchase = null;

    public array $form = [];

    public array $lines = [];

    public string $search = '';

    public bool $showVendor = false;

    public array $newVendor = ['name' => '', 'company' => '', 'phone' => ''];

    public function mount(?PurchaseOrder $purchase = null): void
    {
        $this->purchase = $purchase?->exists ? $purchase : null;

        if ($this->purchase && ! in_array($this->purchase->status, ['draft', 'ordered'], true)) {
            session()->flash('toast', ['type' => 'warning', 'message' => 'Received purchases can’t be edited.']);
            $this->redirectRoute('admin.purchases.show', $this->purchase, navigate: true);

            return;
        }

        $po = $this->purchase;
        $this->form = [
            'vendor_id' => $po?->vendor_id ?? (request()->integer('vendor') ?: ''),
            'bill_number' => $po?->bill_number ?? '',
            'order_date' => $po?->order_date?->toDateString() ?? now()->toDateString(),
            'due_date' => $po?->due_date?->toDateString() ?? '',
            'discount' => (float) ($po?->discount ?? 0),
            'shipping' => (float) ($po?->shipping ?? 0),
            'notes' => $po?->notes ?? '',
        ];

        if ($po) {
            $po->load('items.variant.product', 'items.variant.stock');
            foreach ($po->items as $item) {
                if ($item->variant) {
                    $this->lines[] = $this->lineFor($item->variant, (float) $item->quantity, (float) $item->unit_cost);
                }
            }
        }
    }

    public function rendering($view): void
    {
        $view->title($this->purchase ? 'Edit '.$this->purchase->number : 'New purchase');
    }

    protected function lineFor(ProductVariant $v, float $qty = 1, ?float $cost = null): array
    {
        $isArea = $v->product?->unit === 'cm2';

        return [
            'variant_id' => $v->id,
            'name' => $v->displayName(),
            'sku' => $v->sku,
            'area' => $isArea,
            'stock' => (float) ($v->stock?->quantity ?? 0),
            'avg' => (float) ($v->stock?->average_cost ?: $v->cost_price),
            'quantity' => $qty,
            'unit_cost' => $cost ?? (float) $v->cost_price,
        ];
    }

    public function add(int $variantId): void
    {
        foreach ($this->lines as $i => $line) {
            if ($line['variant_id'] === $variantId) {
                if (! $line['area']) {
                    $this->lines[$i]['quantity'] = (float) $line['quantity'] + 1;
                }
                $this->search = '';

                return;
            }
        }

        $variant = ProductVariant::with('product', 'stock')->findOrFail($variantId);
        $this->lines[] = $this->lineFor($variant, $variant->product?->unit === 'cm2' ? 0 : 1);
        $this->search = '';
    }

    public function addMatch(): void
    {
        $term = trim($this->search);
        $variant = $term === '' ? null : ProductVariant::where('barcode', $term)->orWhere('sku', $term)->first();
        if ($variant) {
            $this->add($variant->id);
        }
    }

    public function remove(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function saveVendor(): void
    {
        $this->validate(['newVendor.name' => 'required|string|max:120', 'newVendor.phone' => 'nullable|string|max:30'], [], ['newVendor.name' => 'name']);
        $vendor = Vendor::create(array_map(fn ($v) => $v === '' ? null : $v, $this->newVendor) + ['current_balance' => 0, 'opening_balance' => 0]);
        $this->form['vendor_id'] = $vendor->id;
        $this->showVendor = false;
        $this->newVendor = ['name' => '', 'company' => '', 'phone' => ''];
    }

    /** $mode: draft | ordered | receive (save + receive everything now) */
    public function save(string $mode, PurchaseService $service): void
    {
        $this->validate([
            'form.vendor_id' => 'required|exists:vendors,id',
            'form.order_date' => 'required|date',
            'form.due_date' => 'nullable|date|after_or_equal:form.order_date',
            'form.discount' => 'nullable|numeric|min:0',
            'form.shipping' => 'nullable|numeric|min:0',
            'form.bill_number' => 'nullable|string|max:60',
            'lines' => 'required|array|min:1',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_cost' => 'required|numeric|min:0',
        ], ['lines.required' => 'Add at least one item.', 'lines.*.quantity.gt' => 'Enter a quantity.'], [
            'form.vendor_id' => 'vendor', 'form.order_date' => 'date', 'form.due_date' => 'due date',
        ]);

        $items = collect($this->lines)->map(fn ($l) => ['product_variant_id' => $l['variant_id'], 'quantity' => $l['quantity'], 'unit_cost' => $l['unit_cost']])->all();

        try {
            $po = $service->save($this->purchase, $this->form + ['status' => $mode === 'draft' ? 'draft' : 'ordered'], $items);

            if ($mode === 'receive') {
                $service->receive($po, $po->items->mapWithKeys(fn ($i) => [$i->id => (float) $i->quantity])->all(), $this->form['order_date']);
            }
        } catch (ValidationException $e) {
            $this->addError('form.vendor_id', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('toast', ['type' => 'success', 'message' => match ($mode) {
            'receive' => "{$po->number} saved and stock received.",
            'ordered' => "{$po->number} saved as ordered.",
            default => "{$po->number} saved as draft.",
        }]);
        $this->redirectRoute('admin.purchases.show', $po, navigate: true);
    }

    public function with(): array
    {
        $subtotal = round(collect($this->lines)->sum(fn ($l) => (float) $l['quantity'] * (float) $l['unit_cost']), 2);
        $term = trim($this->search);

        return [
            'vendors' => Vendor::where('is_active', true)->orWhere('id', $this->form['vendor_id'] ?: 0)->orderBy('name')->get(['id', 'name', 'company', 'current_balance']),
            'results' => mb_strlen($term) >= 2
                ? ProductVariant::active()->with('product:id,name,unit', 'stock')
                    ->where(fn ($q) => $q->where('sku', 'like', "%{$term}%")->orWhere('barcode', $term)->orWhere('label', 'like', "%{$term}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%")))
                    ->limit(10)->get()
                : collect(),
            'subtotal' => $subtotal,
            'grand' => round(max(0, $subtotal - (float) ($this->form['discount'] ?: 0) + (float) ($this->form['shipping'] ?: 0)), 2),
        ];
    }
}; ?>

<div>
    <x-admin.page-header :title="$purchase ? 'Edit '.$purchase->number : 'New purchase'" :crumbs="['Purchases' => route('admin.purchases.index')]" subtitle="Enter the vendor’s bill. Costs are what’s printed on the bill." />

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="form-icon position-relative">
                        <input type="search" class="form-control form-control-icon" placeholder="Search product, SKU or scan a barcode…" wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addMatch">
                        <i class="ri-search-2-line text-muted"></i>
                        @if ($results->isNotEmpty())
                            <div class="list-group position-absolute w-100 shadow-lg mt-1" style="z-index: 20; max-height: 360px; overflow: auto">
                                @foreach ($results as $r)
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" wire:click="add({{ $r->id }})" wire:key="r-{{ $r->id }}">
                                        <span><span class="fw-semibold">{{ $r->displayName() }}</span><span class="text-muted fs-12 ms-2">{{ $r->sku }}</span></span>
                                        <span class="fs-12 text-muted">stock {{ qty($r->stock?->quantity ?? 0) }}{{ $r->product?->unit === 'cm2' ? ' cm²' : '' }} · cost {{ money($r->cost_price) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-mw align-middle mb-0">
                        <thead class="table-light"><tr><th>Item</th><th style="width: 150px">Quantity</th><th style="width: 150px">Unit cost (Rs.)</th><th class="text-end" style="width: 120px">Amount</th><th style="width: 40px"></th></tr></thead>
                        @forelse ($lines as $i => $line)
                            <tbody wire:key="line-{{ $line['variant_id'] }}" x-data="{ calc: false, rolls: 1, w: '', l: '', price: '' }">
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $line['name'] }}</div>
                                        <div class="fs-12 text-muted">{{ $line['sku'] }} · in stock {{ qty($line['stock']) }}{{ $line['area'] ? ' cm²' : '' }} · avg cost {{ $line['area'] ? 'Rs. '.number_format($line['avg'], 4) : money($line['avg']) }}</div>
                                        @if ($line['area'])<button type="button" class="btn btn-link btn-sm p-0 fs-12" x-on:click="calc = !calc"><i class="ri-calculator-line me-1"></i>Roll calculator</button>@endif
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" min="0" step="{{ $line['area'] ? '1' : '1' }}" class="form-control @error('lines.'.$i.'.quantity') is-invalid @enderror" wire:model.live.debounce.400ms="lines.{{ $i }}.quantity">
                                            @if ($line['area'])<span class="input-group-text">cm²</span>@endif
                                        </div>
                                        @if ($line['area'] && (float) $line['quantity'] > 0)<div class="fs-11 text-muted">= {{ qty((float) $line['quantity'] / 10000) }} m²</div>@endif
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="{{ $line['area'] ? '0.0001' : '0.01' }}" class="form-control form-control-sm @error('lines.'.$i.'.unit_cost') is-invalid @enderror" wire:model.live.debounce.400ms="lines.{{ $i }}.unit_cost">
                                        @if ($line['area'])<div class="fs-11 text-muted">per cm²</div>@endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ money((float) $line['quantity'] * (float) $line['unit_cost']) }}</td>
                                    <td><button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" wire:click="remove({{ $i }})" title="Remove"><i class="ri-close-line"></i></button></td>
                                </tr>
                                @if ($line['area'])
                                    <tr x-show="calc" x-cloak class="table-light">
                                        <td colspan="5">
                                            <div class="d-flex flex-wrap align-items-end gap-2">
                                                <div><label class="form-label fs-12 mb-1">Rolls / sheets</label><input type="number" min="1" class="form-control form-control-sm" style="width: 90px" x-model.number="rolls"></div>
                                                <div><label class="form-label fs-12 mb-1">Width (cm)</label><input type="number" min="0" step="0.1" class="form-control form-control-sm" style="width: 100px" x-model.number="w"></div>
                                                <div><label class="form-label fs-12 mb-1">Length (m)</label><input type="number" min="0" step="0.01" class="form-control form-control-sm" style="width: 100px" x-model.number="l"></div>
                                                <div><label class="form-label fs-12 mb-1">Price per roll (Rs.)</label><input type="number" min="0" class="form-control form-control-sm" style="width: 130px" x-model.number="price"></div>
                                                <div class="fs-12 text-muted pb-1" x-show="w && l">= <span x-text="(rolls * w * l * 100).toLocaleString()"></span> cm²</div>
                                                <button type="button" class="btn btn-sm btn-primary" x-bind:disabled="!(rolls && w && l)"
                                                    x-on:click="const area = rolls * w * l * 100; $wire.set('lines.{{ $i }}.quantity', Math.round(area)); if (price) { $wire.set('lines.{{ $i }}.unit_cost', +(price / (w * l * 100)).toFixed(4)) } calc = false">Apply</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="5"><x-admin.empty icon="ri-shopping-basket-2-line" title="No items yet" text="Search above or scan a barcode to add items from the bill." /></td></tr></tbody>
                        @endforelse
                    </table>
                </div>
                @error('lines')<div class="card-footer text-danger fs-13">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between"><label class="form-label">Vendor *</label><button type="button" class="btn btn-link btn-sm p-0" wire:click="$set('showVendor', true)">+ New vendor</button></div>
                        <select class="form-select @error('form.vendor_id') is-invalid @enderror" wire:model="form.vendor_id">
                            <option value="">Choose vendor…</option>
                            @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}{{ $v->company ? ' — '.$v->company : '' }}</option>@endforeach
                        </select>
                        @error('form.vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Bill date *</label><input type="date" class="form-control @error('form.order_date') is-invalid @enderror" wire:model="form.order_date"></div>
                        <div class="col-6"><label class="form-label">Payment due</label><input type="date" class="form-control @error('form.due_date') is-invalid @enderror" wire:model="form.due_date">@error('form.due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div>
                    <div class="mb-3"><label class="form-label">Vendor bill / invoice no.</label><input class="form-control" wire:model="form.bill_number" placeholder="Optional"></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Discount</label><input type="number" min="0" step="0.01" class="form-control" wire:model.live.debounce.400ms="form.discount"></div>
                        <div class="col-6"><label class="form-label">Freight / other</label><input type="number" min="0" step="0.01" class="form-control" wire:model.live.debounce.400ms="form.shipping"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" rows="2" wire:model="form.notes"></textarea></div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Items</span><span>{{ money($subtotal) }}</span></div>
                        @if ((float) $form['discount'] > 0)<div class="d-flex justify-content-between mb-1"><span class="text-muted">Discount</span><span>−{{ money($form['discount']) }}</span></div>@endif
                        @if ((float) $form['shipping'] > 0)<div class="d-flex justify-content-between mb-1"><span class="text-muted">Freight / other</span><span>{{ money($form['shipping']) }}</span></div>@endif
                        <div class="d-flex justify-content-between fs-5 fw-bold"><span>Bill total</span><span>{{ money($grand) }}</span></div>
                        @if ((float) $form['discount'] > 0 || (float) $form['shipping'] > 0)<div class="fs-12 text-muted">Discount and charges are shared across the items in what you owe; stock cost stays at the bill price.</div>@endif
                    </div>
                </div>
                <div class="card-footer d-grid gap-2">
                    <button class="btn btn-primary" wire:click="save('receive')" wire:loading.attr="disabled"><i class="ri-inbox-archive-line me-1"></i>Save & receive all stock</button>
                    <div class="d-flex gap-2">
                        <button class="btn btn-light-primary flex-fill" wire:click="save('ordered')" wire:loading.attr="disabled">Save as ordered</button>
                        <button class="btn btn-light flex-fill" wire:click="save('draft')" wire:loading.attr="disabled">Save draft</button>
                    </div>
                    <div class="fs-12 text-muted">“Ordered” = goods not here yet; receive them later (partial deliveries are fine).</div>
                </div>
            </div>
        </div>
    </div>

    <x-admin.modal :show="$showVendor" close="$set('showVendor', false)" title="New vendor">
        <form wire:submit="saveVendor" id="qv-form" class="row g-3">
            <div class="col-12"><label class="form-label">Name *</label><input class="form-control @error('newVendor.name') is-invalid @enderror" wire:model="newVendor.name">@error('newVendor.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" wire:model="newVendor.company"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" wire:model="newVendor.phone"></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showVendor', false)">Cancel</button>
            <button type="submit" form="qv-form" class="btn btn-primary">Add vendor</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
