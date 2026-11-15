<?php

use App\Models\StockMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Stock movements')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        if (! $this->from && ! $this->to) {
            $this->from = now()->subDays(29)->toDateString();
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = StockMovement::query()
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->search, fn ($q) => $q->whereHas('variant', fn ($v) => $v->where(fn ($w) => $w->where('sku', 'like', "%{$this->search}%")->orWhere('barcode', $this->search)->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%")))));

        $summary = $query->clone()->reorder()
            ->selectRaw('type, COUNT(*) n, SUM(ABS(quantity) * unit_cost) value')
            ->groupBy('type')->get()->keyBy('type');

        return [
            'movements' => $query->with('variant.product:id,name,unit', 'user:id,name', 'reference')->latest('id')->paginate(40),
            'summary' => $summary,
            'valueIn' => (float) $summary->toBase()->only(['purchase', 'opening', 'sale_return', 'online_cancel', 'adjustment_in'])->sum('value'),
            'valueOut' => (float) $summary->toBase()->only(['sale', 'online_order', 'purchase_return', 'adjustment_out', 'laser_usage', 'wastage'])->sum('value'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Stock movements" :crumbs="['Stock levels' => route('admin.inventory.index')]" subtitle="Every stock change across all products: purchases, sales, returns, laser cuts, wastage and adjustments." />

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Stock value in (at cost)</div><h4 class="mb-0 text-success">{{ money($valueIn) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Stock value out (at cost)</div><h4 class="mb-0 text-danger">{{ money($valueOut) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Purchased (at cost)</div><h4 class="mb-0">{{ money($summary['purchase']->value ?? 0) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Wastage (at cost)</div><h4 class="mb-0 {{ ($summary['wastage']->value ?? 0) > 0 ? 'text-danger' : '' }}">{{ money($summary['wastage']->value ?? 0) }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Product, SKU or barcode" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
                <div class="col-md-3">
                    <select class="form-select" wire:model.live="type">
                        <option value="">All movement types</option>
                        @foreach (\App\Models\StockMovement::TYPES as $k => [$label])<option value="{{ $k }}">{{ $label }}{{ isset($summary[$k]) ? ' ('.$summary[$k]->n.')' : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><input type="date" class="form-control" wire:model.live="from" title="From"></div>
                <div class="col-6 col-md-2"><input type="date" class="form-control" wire:model.live="to" title="To"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-mw mb-0">
                <thead class="table-light"><tr><th>When</th><th>Item</th><th>Movement</th><th>Reference</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Stock after</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        @php($area = $m->variant?->product?->unit === 'cm2')
                        <tr wire:key="sm-{{ $m->id }}">
                            <td class="fs-13 text-nowrap">{{ $m->created_at->format('d M Y') }}<div class="fs-11 text-muted">{{ $m->created_at->format('h:i a') }} · {{ $m->user?->name ?? 'system' }}</div></td>
                            <td>
                                @if ($m->variant)<a href="{{ route('admin.inventory.ledger', $m->variant) }}" wire:navigate class="fw-medium text-body">{{ $m->variant->displayName() }}</a><div class="fs-12 text-muted">{{ $m->variant->sku }}</div>@else<span class="text-muted">Deleted item</span>@endif
                            </td>
                            <td><span class="badge bg-{{ $m->typeColor() }}-subtle text-{{ $m->typeColor() }}">{{ $m->typeLabel() }}</span></td>
                            <td class="fs-13">
                                @if ($m->reference instanceof \App\Models\Order)<a href="{{ route('admin.orders.show', $m->reference) }}" wire:navigate>{{ $m->reference->number }}</a>
                                @elseif ($m->reference instanceof \App\Models\PurchaseOrder)<a href="{{ route('admin.purchases.show', $m->reference) }}" wire:navigate>{{ $m->reference->number }}</a>
                                @elseif ($m->reference && isset($m->reference->number)){{ $m->reference->number }}
                                @endif
                                <div class="fs-12 text-muted text-truncate" style="max-width: 260px">{{ $m->note }}</div>
                            </td>
                            <td class="text-end fw-semibold {{ $m->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ qty($m->quantity) }}{{ $area ? ' cm²' : '' }}</td>
                            <td class="text-end fs-13">{{ $area ? 'Rs. '.number_format($m->unit_cost, 4) : money($m->unit_cost) }}</td>
                            <td class="text-end">{{ qty($m->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty icon="ri-arrow-up-down-line" title="No stock movements in this period" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="card-footer">{{ $movements->links() }}</div>@endif
    </div>
</div>
