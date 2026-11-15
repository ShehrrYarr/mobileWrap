<?php

use App\Models\Category;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Inventory')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filter = '';

    #[Url(except: '')]
    public string $category = '';

    public bool $showAdjust = false;

    public ?int $variantId = null;

    public array $adjust = ['mode' => 'add', 'quantity' => '', 'unit_cost' => '', 'note' => ''];

    public function updated($prop): void
    {
        if (in_array($prop, ['search', 'filter', 'category'])) {
            $this->resetPage();
        }
    }

    public function openAdjust(int $variantId, string $mode = 'add'): void
    {
        $this->variantId = $variantId;
        $this->adjust = ['mode' => $mode, 'quantity' => '', 'unit_cost' => '', 'note' => ''];
        $this->resetErrorBag();
        $this->showAdjust = true;
    }

    public function saveAdjust(InventoryService $inventory): void
    {
        $this->validate([
            'adjust.mode' => 'required|in:add,remove,set,wastage',
            'adjust.quantity' => 'required|numeric|min:0',
            'adjust.unit_cost' => 'nullable|numeric|min:0',
            'adjust.note' => 'nullable|string|max:255',
        ], [], ['adjust.quantity' => 'quantity']);

        $variant = ProductVariant::with('stock', 'product')->findOrFail($this->variantId);
        $qty = (float) $this->adjust['quantity'];
        $note = $this->adjust['note'] ?: null;

        match ($this->adjust['mode']) {
            'add' => $inventory->adjust($variant, $qty, 'adjustment_in', null, $note ?? 'Stock added', $this->adjust['unit_cost'] !== '' ? (float) $this->adjust['unit_cost'] : null),
            'remove' => $inventory->adjust($variant, -$qty, 'adjustment_out', null, $note ?? 'Stock removed'),
            'wastage' => $inventory->adjust($variant, -$qty, 'wastage', null, $note ?? 'Damaged / wastage'),
            'set' => $inventory->setQuantity($variant, $qty, $note ?? 'Stock count'),
        };

        $this->showAdjust = false;
        $this->dispatch('toast', type: 'success', message: 'Stock updated for '.$variant->displayName().'.');
    }

    public function with(): array
    {
        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('inventory_stocks', 'inventory_stocks.product_variant_id', '=', 'product_variants.id')
            ->whereNull('products.deleted_at')
            ->with(['product:id,name,unit,low_stock_threshold,type,category_id', 'color.images', 'stock'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('products.name', 'like', "%{$this->search}%")->orWhere('product_variants.sku', 'like', "%{$this->search}%")->orWhere('product_variants.barcode', $this->search)->orWhere('product_variants.label', 'like', "%{$this->search}%")))
            ->when($this->category, fn ($q) => $q->whereIn('products.category_id', Category::find($this->category)?->familyIds() ?? [0]))
            ->when($this->filter === 'low', fn ($q) => $q->whereRaw('COALESCE(inventory_stocks.quantity, 0) <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)'))
            ->when($this->filter === 'out', fn ($q) => $q->whereRaw('COALESCE(inventory_stocks.quantity, 0) <= 0'))
            ->when($this->filter === 'materials', fn ($q) => $q->where('products.type', 'raw_material'))
            ->orderBy('products.name')->orderBy('product_variants.id')
            ->paginate(25);

        $value = \Illuminate\Support\Facades\DB::table('inventory_stocks')->join('product_variants', 'product_variants.id', '=', 'inventory_stocks.product_variant_id')
            ->whereNull('product_variants.deleted_at')->selectRaw('SUM(inventory_stocks.quantity * product_variants.cost_price) as v')->value('v');

        return [
            'variants' => $variants,
            'categories' => Category::roots()->orderBy('sort_order')->get(),
            'stockValue' => (float) $value,
            'current' => $this->variantId ? ProductVariant::with('product', 'stock')->find($this->variantId) : null,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Inventory" subtitle="Live stock per variant. Every change here is written to the stock ledger.">
        <x-slot:actions>
            @can('cost.view')
                <span class="badge bg-success-subtle text-success fs-13 py-2 px-3">Stock value: {{ money($stockValue) }}</span>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5">
                    <div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Search product, SKU or scan barcode…" wire:model.live.debounce.300ms="search" autofocus><i class="ri-barcode-line text-muted"></i></div>
                </div>
                <div class="col-6 col-lg-3">
                    <select class="form-select" wire:model.live="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="btn-group w-100" role="group">
                        @foreach (['' => 'All', 'low' => 'Low', 'out' => 'Out', 'materials' => 'Materials'] as $k => $label)
                            <button type="button" class="btn btn-sm {{ $filter === $k ? 'btn-primary' : 'btn-light' }}" wire:click="$set('filter', '{{ $k }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Item</th><th>SKU / Barcode</th><th class="text-end">On hand</th><th class="text-end">Alert at</th>@can('cost.view')<th class="text-end">Avg cost</th>@endcan<th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($variants as $v)
                        @php($q = $v->quantity())
                        @php($unit = $v->product?->unit === 'cm2' ? ' cm²' : '')
                        <tr wire:key="inv-{{ $v->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $v->color?->images->first() ? upload_url($v->color->images->first()->path) : asset('assets/store/img/placeholder.svg') }}" class="mw-thumb" alt="">
                                    <div class="min-w-0">
                                        <div class="fw-medium text-truncate" style="max-width: 300px">{{ $v->product?->name }}</div>
                                        <div class="fs-12 text-muted">{{ $v->label }} @unless ($v->is_active)<span class="badge bg-light text-muted">inactive</span>@endunless</div>
                                    </div>
                                </div>
                            </td>
                            <td class="fs-13"><code>{{ $v->sku }}</code><div class="text-muted fs-12">{{ $v->barcode }}</div></td>
                            <td class="text-end">
                                <span class="badge fs-12 {{ $q <= 0 ? 'bg-danger' : ($v->isLowStock() ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success') }}">{{ qty($q) }}{{ $unit }}</span>
                            </td>
                            <td class="text-end text-muted fs-13">{{ qty($v->lowStockThreshold()) }}{{ $unit }}</td>
                            @can('cost.view')<td class="text-end fs-13">{{ money($v->stock?->average_cost ?: $v->cost_price) }}</td>@endcan
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-light-success icon-btn-sm" wire:click="openAdjust({{ $v->id }}, 'add')" title="Add stock"><i class="ri-add-line"></i></button>
                                <button class="btn btn-sm btn-light-warning icon-btn-sm" wire:click="openAdjust({{ $v->id }}, 'remove')" title="Remove stock"><i class="ri-subtract-line"></i></button>
                                <button class="btn btn-sm btn-light icon-btn-sm" wire:click="openAdjust({{ $v->id }}, 'set')" title="Stock count"><i class="ri-equalizer-line"></i></button>
                                <a href="{{ route('admin.inventory.ledger', $v->id) }}" wire:navigate class="btn btn-sm btn-light-primary icon-btn-sm" title="Stock ledger"><i class="ri-book-open-line"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="ri-archive-stack-line" title="No stock items match" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($variants->hasPages())<div class="card-footer">{{ $variants->links() }}</div>@endif
    </div>

    <x-admin.modal :show="$showAdjust" close="$set('showAdjust', false)" title="Adjust stock" :subtitle="$current?->displayName()">
        @if ($current)
            @php($u = $current->product?->unit === 'cm2' ? 'cm²' : 'units')
            <div class="alert alert-light d-flex justify-content-between mb-3"><span>Current stock</span><strong>{{ qty($current->quantity()) }} {{ $u }}</strong></div>
            <form wire:submit="saveAdjust" id="adj-form" class="row g-3">
                <div class="col-12">
                    <div class="btn-group w-100">
                        @foreach (['add' => 'Add', 'remove' => 'Remove', 'wastage' => 'Wastage', 'set' => 'Set count'] as $k => $label)
                            <input type="radio" class="btn-check" id="m-{{ $k }}" value="{{ $k }}" wire:model.live="adjust.mode">
                            <label class="btn btn-outline-primary btn-sm" for="m-{{ $k }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ $adjust['mode'] === 'set' ? 'Counted quantity' : 'Quantity' }} ({{ $u }})</label>
                    <input type="number" step="any" min="0" class="form-control @error('adjust.quantity') is-invalid @enderror" wire:model="adjust.quantity" autofocus>
                    @error('adjust.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if ($adjust['mode'] === 'add')
                    <div class="col-md-6">
                        <label class="form-label">Unit cost (optional)</label>
                        <input type="number" step="0.01" min="0" class="form-control" wire:model="adjust.unit_cost" placeholder="{{ (float) $current->cost_price }}">
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label">Note</label>
                    <input type="text" class="form-control" wire:model="adjust.note" placeholder="Reason, e.g. received from vendor / found damaged">
                </div>
            </form>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showAdjust', false)">Cancel</button>
            <button type="submit" form="adj-form" class="btn btn-primary" wire:loading.attr="disabled">Save adjustment</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
