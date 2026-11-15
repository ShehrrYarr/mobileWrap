<?php

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Purchases')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $vendor = '';

    #[Url(except: '')]
    public string $due = '';

    public function updated($prop): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $open = PurchaseOrder::whereIn('status', ['partial', 'received']);

        return [
            'purchases' => PurchaseOrder::with('vendor:id,name')->withCount('items')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$this->search}%")->orWhere('bill_number', 'like', "%{$this->search}%")))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->vendor, fn ($q) => $q->where('vendor_id', $this->vendor))
                ->when($this->due === 'unpaid', fn ($q) => $q->whereIn('status', ['partial', 'received'])->whereRaw('received_total - returned_total - paid_amount > 0.009'))
                ->when($this->due === 'overdue', fn ($q) => $q->whereIn('status', ['partial', 'received'])->whereRaw('received_total - returned_total - paid_amount > 0.009')->whereDate('due_date', '<', today()))
                ->latest('order_date')->latest('id')->paginate(20),
            'vendors' => Vendor::orderBy('name')->pluck('name', 'id'),
            'stats' => [
                'month' => (float) PurchaseOrder::whereIn('status', ['partial', 'received'])->whereBetween('order_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                'pending' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->count(),
                'unpaid' => (float) $open->clone()->selectRaw('COALESCE(SUM(GREATEST(received_total - returned_total - paid_amount, 0)), 0) s')->value('s'),
                'overdue' => $open->clone()->whereRaw('received_total - returned_total - paid_amount > 0.009')->whereDate('due_date', '<', today())->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Purchases" subtitle="Stock bought from vendors. Stock and payables are booked when goods arrive.">
        <x-slot:actions>
            <a href="{{ route('admin.purchases.create') }}" wire:navigate class="btn btn-sm btn-primary"><i class="ri-add-line me-1"></i>New purchase</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Purchased this month</div><h4 class="mb-0">{{ money($stats['month']) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Awaiting delivery</div><h4 class="mb-0">{{ $stats['pending'] }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Unpaid bills</div><h4 class="mb-0 text-danger">{{ money($stats['unpaid']) }}</h4></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Overdue bills</div><h4 class="mb-0 {{ $stats['overdue'] ? 'text-danger' : '' }}">{{ $stats['overdue'] }}</h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-3"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="PO or bill number" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
                <div class="col-md-3"><select class="form-select" wire:model.live="vendor"><option value="">All vendors</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select class="form-select" wire:model.live="status"><option value="">Any status</option>@foreach (\App\Models\PurchaseOrder::STATUSES as $k => [$label])<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><select class="form-select" wire:model.live="due"><option value="">Paid & unpaid</option><option value="unpaid">Unpaid balance</option><option value="overdue">Overdue</option></select></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Purchase</th><th>Vendor</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance due</th></tr></thead>
                <tbody>
                    @forelse ($purchases as $po)
                        <tr wire:key="po-{{ $po->id }}">
                            <td><a href="{{ route('admin.purchases.show', $po) }}" wire:navigate class="fw-semibold">{{ $po->number }}</a><div class="fs-12 text-muted">{{ $po->items_count }} items{{ $po->bill_number ? ' · bill '.$po->bill_number : '' }}</div></td>
                            <td><a href="{{ route('admin.vendors.show', $po->vendor_id) }}" wire:navigate class="text-body">{{ $po->vendor?->name }}</a></td>
                            <td class="fs-13">{{ $po->order_date->format('d M Y') }}@if ($po->due_date)<div class="fs-12 {{ $po->isOverdue() ? 'text-danger' : 'text-muted' }}">due {{ $po->due_date->format('d M') }}</div>@endif</td>
                            <td><span class="badge bg-{{ $po->statusColor() }}-subtle text-{{ $po->statusColor() }}">{{ $po->statusLabel() }}</span> @if ($po->isOverdue())<span class="badge bg-danger">Overdue</span>@endif</td>
                            <td class="text-end">{{ money($po->total) }}</td>
                            <td class="text-end fw-semibold text-nowrap {{ $po->balanceDue() > 0 ? 'text-danger' : 'text-muted' }}">
                                @if ($po->balanceDue() > 0){{ money($po->balanceDue()) }}
                                @elseif ($po->balanceDue() < 0)<span class="fs-12">advance {{ money(abs($po->balanceDue())) }}</span>
                                @elseif (in_array($po->status, ['partial', 'received']))<span class="text-success fs-12">paid</span>
                                @else — @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="ri-shopping-cart-2-line" title="No purchases found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchases->hasPages())<div class="card-footer">{{ $purchases->links() }}</div>@endif
    </div>
</div>
