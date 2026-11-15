<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Products')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated($prop): void
    {
        if (in_array($prop, ['search', 'category', 'brand', 'type', 'status'])) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'brand', 'type', 'status');
        $this->resetPage();
    }

    public function toggle(int $id, string $field): void
    {
        abort_unless(in_array($field, ['is_active', 'show_online', 'is_featured']), 403);
        $product = Product::findOrFail($id);
        $product->update([$field => ! $product->{$field}]);
        $this->dispatch('toast', type: 'success', message: $product->name.' updated.');
    }

    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);
        $product->delete();
        $this->dispatch('toast', type: 'success', message: '“'.$product->name.'” moved to trash.');
    }

    public function with(): array
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'colors' => fn ($q) => $q->with('images'), 'variants.stock'])
            ->withCount('variants')
            ->search($this->search)
            ->when($this->category, function ($q) {
                $cat = Category::find($this->category);
                $q->whereIn('category_id', $cat ? $cat->familyIds() : [0]);
            })
            ->when($this->brand, fn ($q) => $q->where('brand_id', $this->brand))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->status === 'hidden', fn ($q) => $q->where('show_online', false))
            ->when($this->status === 'featured', fn ($q) => $q->where('is_featured', true))
            ->latest('id')
            ->paginate(15);

        return [
            'products' => $products,
            'categories' => Category::with('children')->roots()->orderBy('sort_order')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Products" subtitle="Accessories and laser raw materials, with colour variants and stock.">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" wire:navigate class="btn btn-sm btn-primary"><i class="ri-add-line me-1"></i>Add product</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4">
                    <div class="form-icon">
                        <input type="search" class="form-control form-control-icon" placeholder="Search name, SKU, barcode or device…" wire:model.live.debounce.350ms="search">
                        <i class="ri-search-2-line text-muted"></i>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <select class="form-select" wire:model.live="category">
                        <option value="">All categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @foreach ($cat->children as $child)
                                <option value="{{ $child->id }}">— {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select class="form-select" wire:model.live="brand">
                        <option value="">All brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select class="form-select" wire:model.live="type">
                        <option value="">All types</option>
                        <option value="standard">Accessories</option>
                        <option value="raw_material">Laser raw materials</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2 d-flex gap-2">
                    <select class="form-select" wire:model.live="status">
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="hidden">Hidden online</option>
                        <option value="featured">Featured</option>
                    </select>
                    @if ($search || $category || $brand || $type || $status)
                        <button class="btn btn-light icon-btn flex-shrink-0" wire:click="resetFilters" title="Clear filters"><i class="ri-filter-off-line"></i></button>
                    @endif
                </div>
            </div>
        </div>

        <div class="table-responsive" wire:loading.class="opacity-50" wire:target="search,category,brand,type,status,gotoPage,nextPage,previousPage">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Colours</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Stock</th>
                        <th class="text-center">Active</th>
                        <th class="text-center">Online</th>
                        <th class="text-center">Featured</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $stock = $product->variants->sum(fn ($v) => (float) ($v->stock?->quantity ?? 0));
                            $prices = $product->variants->pluck('retail_price')->map(fn ($p) => (float) $p);
                            $low = $product->variants->contains(fn ($v) => $v->isLowStock());
                        @endphp
                        <tr wire:key="p-{{ $product->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $product->imageUrl() }}" class="mw-thumb" alt="">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="fw-semibold text-body d-block text-truncate" style="max-width: 280px">{{ $product->name }}</a>
                                        <div class="fs-12 text-muted">
                                            {{ $product->brand?->name ?? 'No brand' }} · {{ $product->variants_count }} {{ Str::plural('variant', $product->variants_count) }}
                                            @if ($product->isRawMaterial())
                                                <span class="badge bg-purple-subtle ms-1">Raw material</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    @foreach ($product->colors->take(5) as $c)
                                        <span class="mw-swatch" style="background: {{ $c->hex_code ?? '#ddd' }}" title="{{ $c->name }}"></span>
                                    @endforeach
                                    @if ($product->colors->count() > 5)
                                        <span class="fs-12 text-muted">+{{ $product->colors->count() - 5 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="fs-13">{{ $product->category?->name }}</td>
                            <td class="text-end fs-13">
                                @if ($product->isRawMaterial())
                                    <span class="text-muted">{{ money($product->variants->first()?->cost_price) }}/cm²</span>
                                @elseif ($prices->min() === $prices->max())
                                    {{ money($prices->min()) }}
                                @else
                                    {{ money($prices->min()) }} – {{ money($prices->max()) }}
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="badge {{ $stock <= 0 ? 'bg-danger-subtle text-danger' : ($low ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success') }}">
                                    {{ qty($stock) }}{{ $product->unit === 'cm2' ? ' cm²' : '' }}
                                </span>
                            </td>
                            @foreach (['is_active', 'show_online', 'is_featured'] as $field)
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block mb-0">
                                        <input class="form-check-input" type="checkbox" @checked($product->{$field}) wire:click="toggle({{ $product->id }}, '{{ $field }}')" @disabled($field !== 'is_active' && $product->isRawMaterial())>
                                    </div>
                                </td>
                            @endforeach
                            <td class="text-end text-nowrap">
                                @if (! $product->isRawMaterial())
                                    <a href="{{ route('product', $product->slug) }}" target="_blank" class="btn btn-sm btn-light icon-btn-sm" title="View in store"><i class="ri-external-link-line"></i></a>
                                @endif
                                @unless ($product->isRawMaterial())
                                    <a href="{{ route('admin.labels', ['product' => $product->id]) }}" wire:navigate class="btn btn-sm btn-light icon-btn-sm" title="Print barcode labels"><i class="ri-barcode-line"></i></a>
                                @endunless
                                <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="btn btn-sm btn-light-primary icon-btn-sm" title="Edit"><i class="ri-pencil-line"></i></a>
                                <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" title="Delete" x-data @click="confirmAction('“{{ addslashes($product->name) }}” will be moved to trash.', () => $wire.delete({{ $product->id }}))"><i class="ri-delete-bin-line"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-admin.empty icon="ri-shopping-basket-2-line" title="No products found" text="Try different filters, or add your first product." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-footer">{{ $products->links() }}</div>
        @endif
    </div>
</div>
