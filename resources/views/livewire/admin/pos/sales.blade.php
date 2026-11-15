<?php

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('POS sales')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $method = '';

    public function mount(): void
    {
        $this->date = $this->date ?: now()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $base = Order::pos()->whereNotIn('status', ['held'])
            ->when($this->date, fn ($q) => $q->whereDate('placed_at', $this->date));

        $byMethod = DB::table('order_payments')->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('orders.channel', 'pos')->whereNull('orders.deleted_at')->where('orders.status', '!=', 'held')
            ->when($this->date, fn ($q) => $q->whereDate('orders.placed_at', $this->date))
            ->selectRaw('order_payments.method, SUM(order_payments.amount) as total')->groupBy('order_payments.method')->pluck('total', 'method');

        return [
            'orders' => (clone $base)->with('user:id,name')->withCount('items')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$this->search}%")->orWhere('customer_name', 'like', "%{$this->search}%")->orWhere('customer_phone', 'like', "%{$this->search}%")))
                ->when($this->method, fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', $this->method)))
                ->latest('placed_at')->paginate(25),
            'summary' => [
                'count' => (clone $base)->count(),
                'total' => (float) (clone $base)->sum('grand_total'),
                'laser' => (float) (clone $base)->sum('service_fee_total'),
                'discounts' => (float) (clone $base)->sum(DB::raw('discount_total + coupon_discount')),
            ],
            'byMethod' => $byMethod,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="POS sales" subtitle="In-store invoices. Reprint receipts or start a return from here.">
        <x-slot:actions>
            <a href="{{ route('admin.pos') }}" class="btn btn-sm btn-primary"><i class="ri-computer-line me-1"></i>Open POS</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Sales {{ $date === now()->toDateString() ? 'today' : 'on '.\Carbon\Carbon::parse($date)->format('d M') }}</div><h4 class="mb-0">{{ money($summary['total']) }}</h4><span class="fs-12 text-muted">{{ $summary['count'] }} invoices</span></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Cash</div><h4 class="mb-0">{{ money($byMethod['cash'] ?? 0) }}</h4><span class="fs-12 text-muted">Bank {{ money($byMethod['bank_transfer'] ?? 0) }}</span></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">On khata</div><h4 class="mb-0">{{ money($byMethod['customer_credit'] ?? 0) }}</h4><span class="fs-12 text-muted">Exchange credit {{ money($byMethod['exchange_credit'] ?? 0) }}</span></div></div></div>
        <div class="col-6 col-xl-3"><div class="card mb-0 h-100"><div class="card-body"><div class="fs-12 text-muted">Laser services</div><h4 class="mb-0">{{ money($summary['laser']) }}</h4><span class="fs-12 text-muted">Discounts given {{ money($summary['discounts']) }}</span></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-5"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Invoice #, customer or phone" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
                <div class="col-6 col-md-3"><input type="date" class="form-control" wire:model.live="date"></div>
                <div class="col-6 col-md-3">
                    <select class="form-select" wire:model.live="method"><option value="">Any payment</option><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="customer_credit">Khata</option><option value="exchange_credit">Exchange credit</option></select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th>Items</th><th>Payment</th><th>Status</th><th>Cashier</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $o)
                        <tr wire:key="ps-{{ $o->id }}">
                            <td><span class="fw-semibold">{{ $o->number }}</span><div class="fs-12 text-muted">{{ $o->placed_at?->format('h:i a') }}</div></td>
                            <td>{{ $o->customer_name }}<div class="fs-12 text-muted">{{ $o->customer_phone }}</div></td>
                            <td>{{ $o->items_count }}</td>
                            <td class="fs-13">{{ $o->paymentMethodLabel() }}@if ((float) $o->due_total > 0)<div class="fs-12 text-warning">{{ money($o->due_total) }} on khata</div>@endif</td>
                            <td><span class="badge bg-{{ $o->statusColor() }}-subtle text-{{ $o->statusColor() }}">{{ $o->statusLabel() }}</span></td>
                            <td class="fs-13">{{ $o->user?->name }}</td>
                            <td class="text-end fw-semibold">{{ money($o->grand_total) }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-light icon-btn-sm" title="Receipt 80mm" onclick="window.open('{{ route('admin.pos.receipt', ['order' => $o, 'format' => '80mm']) }}', 'mw-receipt', 'width=440,height=720')"><i class="ri-printer-line"></i></button>
                                <button type="button" class="btn btn-sm btn-light icon-btn-sm" title="A4 invoice" onclick="window.open('{{ route('admin.pos.receipt', ['order' => $o, 'format' => 'a4']) }}', 'mw-receipt-a4', 'width=900,height=900')"><i class="ri-file-paper-2-line"></i></button>
                                @if (! in_array($o->status, ['returned', 'cancelled']))
                                    <a href="{{ route('admin.returns.create', ['invoice' => $o->number]) }}" wire:navigate class="btn btn-sm btn-light-warning icon-btn-sm" title="Return / exchange"><i class="ri-arrow-go-back-line"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty icon="ri-receipt-line" title="No POS sales for this day" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
    </div>
</div>
