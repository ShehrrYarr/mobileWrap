<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Barcode labels')] class extends Component
{
    #[Url(as: 'product', except: null)]
    public ?int $productId = null;

    public string $search = '';

    /** variant_id => copies */
    public array $items = [];

    public string $size = '38x25';

    public bool $showPrice = true;

    public bool $showStore = true;

    public function mount(): void
    {
        $this->size = setting('labels.size', '38x25');
        if ($this->productId) {
            foreach (ProductVariant::active()->where('product_id', $this->productId)->pluck('id') as $id) {
                $this->items[$id] = 1;
            }
        }
    }

    public function add(int $variantId): void
    {
        $this->items[$variantId] = ($this->items[$variantId] ?? 0) + 1;
        $this->search = '';
    }

    public function addMatches(): void
    {
        $variant = ProductVariant::active()->where('barcode', trim($this->search))->orWhere('sku', trim($this->search))->first();
        if ($variant) {
            $this->add($variant->id);
        }
    }

    public function setCopies(int $variantId, $copies): void
    {
        $copies = (int) $copies;
        if ($copies <= 0) {
            unset($this->items[$variantId]);
        } else {
            $this->items[$variantId] = min(500, $copies);
        }
    }

    public function copiesFromStock(): void
    {
        foreach (ProductVariant::with('stock')->whereIn('id', array_keys($this->items))->get() as $v) {
            $this->items[$v->id] = max(1, (int) floor($v->availableQuantity()));
        }
    }

    public function print(): void
    {
        abort_unless(in_array($this->size, ['38x25', '50x30', '40x30']), 422);
        if (! $this->items) {
            $this->dispatch('toast', type: 'warning', message: 'Add at least one item.');

            return;
        }
        session(['label_job' => ['size' => $this->size, 'price' => $this->showPrice, 'store' => $this->showStore, 'items' => $this->items]]);
        $this->dispatch('open-labels', url: route('admin.labels.print'));
    }

    public function with(): array
    {
        return [
            'results' => strlen($this->search) >= 2
                ? ProductVariant::active()->with('product:id,name,type')
                    ->whereHas('product', fn ($q) => $q->where('type', 'standard'))
                    ->where(fn ($q) => $q->where('sku', 'like', "%{$this->search}%")->orWhere('barcode', $this->search)->orWhere('label', 'like', "%{$this->search}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")))
                    ->limit(10)->get()
                : collect(),
            'selected' => ProductVariant::with(['product:id,name', 'stock', 'color'])->whereIn('id', array_keys($this->items))->get(),
            'total' => array_sum($this->items),
        ];
    }
}; ?>

<div x-on:open-labels.window="window.open($event.detail.url ?? $event.detail[0]?.url, 'mw-labels', 'width=520,height=760')">
    <x-admin.page-header title="Barcode labels" subtitle="Print price & barcode stickers on your thermal label printer." />

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="position-relative">
                        <div class="form-icon">
                            <input type="search" class="form-control form-control-icon form-control-lg" placeholder="Search product, SKU or scan a barcode to add…" wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="addMatches" autofocus>
                            <i class="ri-barcode-line text-muted"></i>
                        </div>
                        @if ($results->isNotEmpty())
                            <div class="list-group position-absolute w-100 shadow" style="z-index: 10">
                                @foreach ($results as $r)
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:click="add({{ $r->id }})">
                                        <span><b>{{ $r->product?->name }}</b> <span class="text-muted">{{ $r->label }}</span></span>
                                        <code>{{ $r->barcode ?: $r->sku }}</code>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Item</th><th>Barcode</th><th class="text-end">Price</th><th class="text-center">In stock</th><th style="width: 130px">Copies</th></tr></thead>
                        <tbody>
                            @forelse ($selected as $v)
                                <tr wire:key="lbl-{{ $v->id }}">
                                    <td><div class="fw-medium">{{ $v->product?->name }}</div><div class="fs-12 text-muted">{{ $v->label }}</div></td>
                                    <td class="fs-13"><code>{{ $v->barcode ?: $v->sku }}</code>@unless ($v->barcode)<div class="fs-11 text-warning">No barcode — SKU used</div>@endunless</td>
                                    <td class="text-end">{{ money($v->retail_price) }}</td>
                                    <td class="text-center">{{ qty($v->availableQuantity()) }}</td>
                                    <td><input type="number" min="0" max="500" class="form-control form-control-sm" value="{{ $items[$v->id] ?? 1 }}" wire:change="setCopies({{ $v->id }}, $event.target.value)"></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-admin.empty icon="ri-barcode-line" title="No items yet" text="Search above, scan a barcode, or use “Labels” on a product in the products list." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Label setup</h5></div>
                <div class="card-body d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Label size</label>
                        <select class="form-select" wire:model.live="size"><option value="38x25">38 × 25 mm</option><option value="40x30">40 × 30 mm</option><option value="50x30">50 × 30 mm</option></select>
                    </div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="lp" wire:model.live="showPrice"><label class="form-check-label" for="lp">Show retail price</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="ls" wire:model.live="showStore"><label class="form-check-label" for="ls">Show store name</label></div>
                    <button type="button" class="btn btn-light btn-sm" wire:click="copiesFromStock" @disabled(! $items)>Set copies = stock on hand</button>
                    <div class="border rounded-3 p-3 text-center bg-light">
                        <div class="fs-12 text-muted mb-1">Preview</div>
                        @php([$w, $h] = explode('x', $size))
                        <div class="mx-auto bg-white border d-flex flex-column justify-content-center" style="width: {{ $w * 3 }}px; height: {{ $h * 3 }}px; padding: 6px; font-size: 10px; line-height: 1.15">
                            @if ($showStore)<div class="fw-bold text-uppercase" style="font-size: 8px">{{ store_name() }}</div>@endif
                            <div class="fw-semibold text-truncate">{{ $selected->first()?->product?->name ?? 'Product name' }}</div>
                            <div class="d-flex justify-content-center my-1">{!! \App\Support\Barcode::svg($selected->first()?->barcode ?? '2000001001007', $w * 2.4, $h * 1.1) !!}</div>
                            <div class="d-flex justify-content-between"><span>{{ $selected->first()?->barcode ?? '2000001001007' }}</span>@if ($showPrice)<b>{{ money($selected->first()?->retail_price ?? 999) }}</b>@endif</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" wire:click="print" @disabled(! $items)><i class="ri-printer-line me-1"></i>Print {{ $total }} {{ Str::plural('label', $total) }}</button>
                    <p class="fs-12 text-muted mb-0">In the print dialog choose your label printer, set paper to the label size and margins to “None”.</p>
                </div>
            </div>
        </div>
    </div>
</div>
