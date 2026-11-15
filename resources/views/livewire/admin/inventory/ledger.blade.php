<?php

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Stock ledger')] class extends Component
{
    use WithPagination;

    public ProductVariant $variant;

    #[Url(except: '')]
    public string $type = '';

    public function mount($variant): void
    {
        $id = $variant instanceof ProductVariant ? $variant->getKey() : (int) $variant;
        $this->variant = ProductVariant::withTrashed()->with('product', 'color.images', 'stock')->findOrFail($id);
    }

    public function with(): array
    {
        $base = StockMovement::where('product_variant_id', $this->variant->id);

        return [
            'movements' => (clone $base)->with('user:id,name', 'reference')->when($this->type, fn ($q) => $q->where('type', $this->type))->latest('id')->paginate(30),
            'totals' => [
                'in' => (float) (clone $base)->where('quantity', '>', 0)->sum('quantity'),
                'out' => (float) abs((clone $base)->where('quantity', '<', 0)->sum('quantity')),
            ],
            'unit' => $this->variant->product?->unit === 'cm2' ? ' cm²' : '',
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Stock ledger" :crumbs="['Inventory' => route('admin.inventory.index')]">
        <x-slot:actions>
            <a href="{{ route('admin.products.edit', $variant->product_id) }}" wire:navigate class="btn btn-sm btn-light"><i class="ri-pencil-line me-1"></i>Edit product</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card mb-0 h-100">
                <div class="card-body d-flex gap-3 align-items-center">
                    <img src="{{ $variant->color?->images->first() ? upload_url($variant->color->images->first()->path) : asset('assets/store/img/placeholder.svg') }}" class="mw-thumb mw-thumb-lg" alt="">
                    <div>
                        <h5 class="mb-1">{{ $variant->product?->name }}</h5>
                        <div class="text-muted fs-13 mb-2">{{ $variant->label }} · <code>{{ $variant->sku }}</code> · {{ $variant->barcode }}</div>
                        <span class="badge bg-primary-subtle text-primary fs-13">On hand: {{ qty($variant->quantity()) }}{{ $unit }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0 h-100"><div class="card-body"><h6 class="fs-12 text-muted">Total in</h6><h4 class="text-success mb-0">+{{ qty($totals['in']) }}{{ $unit }}</h4></div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card mb-0 h-100"><div class="card-body"><h6 class="fs-12 text-muted">Total out</h6><h4 class="text-danger mb-0">−{{ qty($totals['out']) }}{{ $unit }}</h4></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="card-title mb-0 flex-grow-1">Movements</h5>
            <select class="form-select form-select-sm" style="width: 200px" wire:model.live="type">
                <option value="">All types</option>
                @foreach (\App\Models\StockMovement::TYPES as $k => [$label]) <option value="{{ $k }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-mw mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Reference</th><th>Note</th><th>By</th><th class="text-end">Qty</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td class="fs-13 text-nowrap">{{ $m->created_at->format('d M Y, h:i a') }}</td>
                            <td><span class="badge bg-{{ $m->typeColor() }}-subtle text-{{ $m->typeColor() }}">{{ $m->typeLabel() }}</span></td>
                            <td class="fs-13">
                                @if ($m->reference instanceof \App\Models\Order)
                                    <a href="{{ route('admin.orders.show', $m->reference) }}" wire:navigate>{{ $m->reference->number }}</a>
                                @elseif ($m->reference)
                                    {{ class_basename($m->reference) }} #{{ $m->reference_id }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fs-13 text-muted">{{ $m->note }}</td>
                            <td class="fs-13">{{ $m->user?->name ?? 'System' }}</td>
                            <td class="text-end fw-semibold {{ $m->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $m->quantity > 0 ? '+' : '−' }}{{ qty(abs($m->quantity)) }}</td>
                            <td class="text-end">{{ qty($m->balance_after) }}{{ $unit }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty icon="ri-book-open-line" title="No movements yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="card-footer">{{ $movements->links() }}</div>@endif
    </div>
</div>
