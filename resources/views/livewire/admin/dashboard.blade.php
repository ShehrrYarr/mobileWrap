<?php

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $online = Order::online()->whereNotIn('status', ['cancelled']);

        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $daily = Order::online()->whereNotIn('status', ['cancelled'])
            ->where('placed_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(placed_at) as d, COUNT(*) as c, SUM(grand_total) as t')
            ->groupBy('d')->get()->keyBy('d');

        $lowStock = ProductVariant::query()
            ->join('inventory_stocks', 'inventory_stocks.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('products.deleted_at')
            ->where('product_variants.is_active', true)
            ->whereRaw('inventory_stocks.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
            ->select('product_variants.*', 'inventory_stocks.quantity as stock_qty', 'products.unit as unit')
            ->with('product:id,name', 'color.images')
            ->orderBy('inventory_stocks.quantity')->limit(8)->get();

        return [
            'stats' => [
                ['label' => 'Online sales (30 days)', 'value' => money((clone $online)->where('placed_at', '>=', now()->subDays(30))->sum('grand_total')), 'icon' => 'ri-money-dollar-circle-line', 'color' => 'success'],
                ['label' => 'Orders awaiting action', 'value' => Order::online()->whereIn('status', ['pending', 'confirmed', 'processing'])->count(), 'icon' => 'ri-time-line', 'color' => 'warning', 'url' => route('admin.orders.index', ['status' => 'pending'])],
                ['label' => 'Payments to verify', 'value' => DB::table('order_payments')->where('status', 'pending')->where('method', 'bank_transfer')->count(), 'icon' => 'ri-bank-card-line', 'color' => 'info', 'url' => route('admin.orders.index', ['payment' => 'verify'])],
                ['label' => 'Low-stock variants', 'value' => $lowStock->count() >= 8 ? '8+' : $lowStock->count(), 'icon' => 'ri-error-warning-line', 'color' => 'danger', 'url' => route('admin.inventory.index', ['filter' => 'low'])],
            ],
            'counts' => [
                'products' => Product::where('type', 'standard')->count(),
                'customers' => Customer::count(),
                'stockValue' => InventoryStock::join('product_variants', 'product_variants.id', '=', 'inventory_stocks.product_variant_id')->sum(DB::raw('inventory_stocks.quantity * product_variants.cost_price')),
            ],
            'chart' => [
                'labels' => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values(),
                'orders' => $days->map(fn ($d) => (int) ($daily[$d]->c ?? 0))->values(),
                'sales' => $days->map(fn ($d) => (float) ($daily[$d]->t ?? 0))->values(),
            ],
            'recent' => Order::online()->with('customer:id,name')->latest('placed_at')->limit(7)->get(),
            'lowStock' => $lowStock,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Dashboard" :subtitle="'Welcome back, '.auth('web')->user()->name.' — here’s what’s happening at '.store_name().'.'">
        <x-slot:actions>
            @can('catalog.manage')
                <a href="{{ route('admin.products.create') }}" wire:navigate class="btn btn-sm btn-primary"><i class="ri-add-line me-1"></i>Add product</a>
            @endcan
            <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-light-primary"><i class="ri-external-link-line me-1"></i>Storefront</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        @foreach ($stats as $s)
            <div class="col-sm-6 col-xl-3">
                <a @isset($s['url']) href="{{ $s['url'] }}" wire:navigate @endisset class="card card-h-100 mb-0 text-decoration-none">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-2 fs-12 text-muted">{{ $s['label'] }}</h6>
                            <h4 class="fw-semibold mb-0 text-body">{{ $s['value'] }}</h4>
                        </div>
                        <span class="mw-stat-icon bg-{{ $s['color'] }}-subtle text-{{ $s['color'] }}"><i class="{{ $s['icon'] }}"></i></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card card-h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Online orders — last 14 days</h5>
                    <span class="badge bg-light text-muted">{{ $counts['products'] }} products · {{ $counts['customers'] }} customers</span>
                </div>
                <div class="card-body">
                    <div id="mw-sales-chart" wire:ignore style="min-height: 300px"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Low stock</h5>
                    <a href="{{ route('admin.inventory.index', ['filter' => 'low']) }}" wire:navigate class="btn btn-sm btn-light-primary">View all</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($lowStock as $v)
                            <li class="list-group-item d-flex align-items-center gap-3">
                                <img src="{{ $v->color?->images->first() ? upload_url($v->color->images->first()->path) : asset('assets/store/img/placeholder.svg') }}" class="mw-thumb" alt="">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-medium text-truncate">{{ $v->product?->name }}</div>
                                    <div class="fs-12 text-muted text-truncate">{{ $v->label }} · {{ $v->sku }}</div>
                                </div>
                                <span class="badge {{ $v->stock_qty <= 0 ? 'bg-danger' : 'bg-warning-subtle text-warning' }}">{{ qty($v->stock_qty) }}{{ $v->unit === 'cm2' ? ' cm²' : '' }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center py-5">All stock levels are healthy.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Recent online orders</h5>
                    <a href="{{ route('admin.orders.index') }}" wire:navigate class="btn btn-sm btn-light-primary">All orders</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-mw mb-0">
                        <thead class="table-light">
                            <tr><th>Order</th><th>Customer</th><th>Placed</th><th>Payment</th><th>Status</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($recent as $order)
                                <tr class="cursor-pointer" onclick="Livewire.navigate('{{ route('admin.orders.show', $order) }}')">
                                    <td class="fw-semibold text-primary">{{ $order->number }}</td>
                                    <td>{{ $order->customer_name }}<div class="fs-12 text-muted">{{ $order->shipping_city }}</div></td>
                                    <td>{{ $order->placed_at?->diffForHumans() }}</td>
                                    <td>{{ $order->paymentMethodLabel() }} <span class="badge bg-{{ $order->paymentStatusColor() }}-subtle text-{{ $order->paymentStatusColor() }} ms-1">{{ $order->paymentStatusLabel() }}</span></td>
                                    <td><span class="badge bg-{{ $order->statusColor() }}-subtle text-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
                                    <td class="text-end fw-semibold">{{ money($order->grand_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">No online orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@assets
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
@endassets

@script
<script>
    const data = @js($chart);
    const el = document.getElementById('mw-sales-chart');
    if (el && window.ApexCharts) {
        el.innerHTML = '';
        new ApexCharts(el, {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Sales (Rs.)', type: 'column', data: data.sales }, { name: 'Orders', type: 'line', data: data.orders }],
            xaxis: { categories: data.labels },
            yaxis: [{ labels: { formatter: v => 'Rs. ' + Math.round(v).toLocaleString() } }, { opposite: true, labels: { formatter: v => Math.round(v) } }],
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            colors: [getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#3e60d5', '#f7b84b'],
            dataLabels: { enabled: false },
            grid: { borderColor: 'rgba(125,125,125,.15)' },
            legend: { position: 'top', horizontalAlign: 'right' },
        }).render();
    }
</script>
@endscript
