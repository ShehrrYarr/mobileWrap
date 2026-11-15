<?php

use App\Livewire\Concerns\WithReportPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Sales report')] class extends Component
{
    use WithReportPeriod;

    #[Url(except: '')]
    public string $channel = '';

    /** days | products | categories | payments | people */
    #[Url(except: 'days')]
    public string $tab = 'days';

    public function with(): array
    {
        $reports = app(ReportService::class);
        $range = $this->periodRange();
        $ch = in_array($this->channel, ['pos', 'online'], true) ? $this->channel : null;

        $rows = match ($this->tab) {
            'products' => ['products' => $reports->salesByProduct($range, $ch, 100)],
            'categories' => ['categories' => $reports->salesByCategory($range, $ch)],
            'payments' => ['payments' => $reports->salesByPayment($range, $ch), 'channels' => $ch ? [] : $reports->salesByChannel($range)],
            'people' => ['staff' => $reports->salesByStaff($range, $ch), 'customers' => $reports->salesByCustomer($range, $ch)],
            default => ['days' => $reports->salesByDay($range, $ch)],
        };

        return $rows + [
            'range' => $range,
            's' => $reports->salesSummary($range, $ch),
            'prev' => $reports->salesSummary($range->previous(), $ch),
            'pending' => $reports->pendingOnline(),
            'canCost' => auth('web')->user()->can('cost.view'),
            'pdfUrl' => route('admin.reports.pdf', ['report' => 'sales'] + $this->periodQuery(['channel' => $ch])),
        ];
    }
}; ?>

<div>
    @php $R = \App\Services\ReportService::class; @endphp
    <x-admin.page-header title="Sales report" subtitle="What you sold, how customers paid and what you earned on it.">
        <x-slot:actions>
            <a href="{{ $pdfUrl }}" data-pdf class="btn btn-sm btn-primary"><i class="ri-file-pdf-2-line me-1"></i>Download PDF</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.report-period :period="$period" :range="$range">
        <div>
            <label class="form-label fs-12 text-muted mb-1">Channel</label>
            <select class="form-select" wire:model.live="channel"><option value="">All channels</option><option value="pos">In store (POS)</option><option value="online">Online store</option></select>
        </div>
    </x-admin.report-period>

    <div class="row g-3 mb-3" wire:loading.class="opacity-50">
        <div class="col-6 col-xl-3"><x-admin.stat label="Net sales" :value="money($s['net'])" :change="$R::change($s['net'], $prev['net'])" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Invoices" :value="number_format($s['invoices'])" :sub="'Avg '.money($s['average']).' · '.qty($s['units']).' items'" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Discounts given" :value="money($s['discounts'])" :sub="$s['gross'] > 0 ? number_format($s['discounts'] / $s['gross'] * 100, 1).'% of goods sales' : null" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Returns" :value="money($s['returns'])" :sub="$s['returns_count'].' return(s) · '.qty($s['returned_units']).' items'" tone="{{ $s['returns'] > 0 ? 'danger' : '' }}" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Laser cutting fees" :value="money($s['laser'])" :sub="$s['laser_pieces'].' piece(s) cut'" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Delivery charges" :value="money($s['delivery'])" sub="Charged on online orders" /></div>
        @if ($canCost)
            <div class="col-6 col-xl-3"><x-admin.stat label="Cost of goods" :value="money($s['goods_cost'])" sub="Weighted-average cost, net of restocked returns" /></div>
            <div class="col-6 col-xl-3"><x-admin.stat label="Margin on goods" :value="money($s['goods_margin'])" :sub="$s['goods_revenue'] > 0 ? number_format($s['goods_margin'] / $s['goods_revenue'] * 100, 1).'% of goods sales' : null" tone="{{ $s['goods_margin'] < 0 ? 'danger' : 'success' }}" /></div>
        @endif
    </div>
    @if ($pending['count'])
        <p class="fs-13 text-muted mb-3"><i class="ri-information-line me-1"></i>{{ $pending['count'] }} online order(s) worth {{ money($pending['amount']) }} aren't delivered yet, so they aren't counted as sales.
            <a href="{{ route('admin.orders.index') }}" wire:navigate>View orders</a></p>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach (['days' => ['ri-calendar-line', $range->groupsByMonth() ? 'Month by month' : 'Day by day'], 'products' => ['ri-shopping-basket-2-line', 'Products'], 'categories' => ['ri-folder-3-line', 'Categories'], 'payments' => ['ri-bank-card-line', 'Payments & channel'], 'people' => ['ri-team-line', 'Staff & customers']] as $key => [$icon, $label])
            <li class="nav-item"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="$set('tab', '{{ $key }}')"><i class="{{ $icon }} me-1"></i>{{ $label }}</button></li>
        @endforeach
    </ul>

    <div wire:loading.class="opacity-50">
        @if ($tab === 'days')
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>{{ $range->groupsByMonth() ? 'Month' : 'Date' }}</th><th class="text-end">Invoices</th><th class="text-end">Sales</th><th class="text-end">Discounts</th><th class="text-end">Returns</th><th class="text-end">Net sales</th>@if ($canCost)<th class="text-end">Cost of goods</th>@endif</tr></thead>
                        <tbody>
                            @foreach ($days as $d)
                                <tr class="{{ ! $d['invoices'] && ! $d['returns'] ? 'text-muted' : '' }}">
                                    <td class="text-nowrap">{{ $d['label'] }}</td><td class="text-end">{{ $d['invoices'] }}</td><td class="text-end">{{ money($d['sales']) }}</td>
                                    <td class="text-end">{{ $d['discounts'] ? money($d['discounts']) : '—' }}</td><td class="text-end {{ $d['returns'] ? 'text-danger' : '' }}">{{ $d['returns'] ? money($d['returns']) : '—' }}</td>
                                    <td class="text-end fw-semibold">{{ money($d['net']) }}</td>@if ($canCost)<td class="text-end">{{ money($d['cost']) }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold"><tr><td>Total</td><td class="text-end">{{ $s['invoices'] }}</td><td class="text-end">{{ money($s['sales_total']) }}</td><td class="text-end">{{ money($s['discounts']) }}</td><td class="text-end">{{ money($s['returns']) }}</td><td class="text-end">{{ money($s['net']) }}</td>@if ($canCost)<td class="text-end">{{ money($s['goods_cost']) }}</td>@endif</tr></tfoot>
                    </table>
                </div>
            </div>
        @elseif ($tab === 'products')
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="text-end">Sold</th><th class="text-end">Returned</th><th class="text-end">Invoices</th><th class="text-end">Amount</th>@if ($canCost)<th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th>@endif</tr></thead>
                        <tbody>
                            @forelse ($products as $i => $p)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td><span class="fw-medium">{{ $p['name'] }}</span>@if ($p['label'] && $p['label'] !== 'Standard')<span class="text-muted"> — {{ $p['label'] }}</span>@endif<div class="fs-12 text-muted">{{ $p['sku'] }}</div></td>
                                    <td class="text-end">{{ qty($p['qty']) }}</td><td class="text-end text-muted">{{ (float) $p['returned_qty'] ? qty($p['returned_qty']) : '—' }}</td><td class="text-end">{{ $p['invoices'] }}</td>
                                    <td class="text-end fw-semibold">{{ money($p['amount']) }}</td>
                                    @if ($canCost)<td class="text-end">{{ money($p['cost']) }}</td><td class="text-end {{ $p['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ money($p['profit']) }}</td><td class="text-end">{{ (float) $p['amount'] ? number_format($p['profit'] / $p['amount'] * 100, 1).'%' : '—' }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="9"><x-admin.empty icon="ri-shopping-basket-2-line" title="No products sold in this period" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer fs-12 text-muted">Top 100 by amount. Amounts are after line discounts, before invoice discounts and returns.</div>
            </div>
        @elseif ($tab === 'categories')
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Category</th><th class="text-end">Qty</th><th class="text-end">Amount</th><th class="text-end">Share</th>@if ($canCost)<th class="text-end">Cost</th><th class="text-end">Profit</th><th class="text-end">Margin</th>@endif</tr></thead>
                        <tbody>
                            @php $catTotal = array_sum(array_map(fn ($c) => (float) $c['amount'], $categories)); @endphp
                            @forelse ($categories as $c)
                                <tr>
                                    <td class="fw-medium">{{ $c['name'] }}</td><td class="text-end">{{ qty($c['qty']) }}</td><td class="text-end fw-semibold">{{ money($c['amount']) }}</td>
                                    <td class="text-end" style="min-width: 140px">
                                        @php $share = $catTotal ? (float) $c['amount'] / $catTotal * 100 : 0; @endphp
                                        <div class="d-flex align-items-center gap-2 justify-content-end"><div class="progress flex-grow-1" style="height: 6px; max-width: 80px"><div class="progress-bar" style="width: {{ $share }}%"></div></div><span class="fs-12">{{ number_format($share, 1) }}%</span></div>
                                    </td>
                                    @if ($canCost)<td class="text-end">{{ $c['cost'] === null ? '—' : money($c['cost']) }}</td><td class="text-end">{{ $c['profit'] === null ? '—' : money($c['profit']) }}</td><td class="text-end">{{ $c['profit'] === null || ! (float) $c['amount'] ? '—' : number_format($c['profit'] / $c['amount'] * 100, 1).'%' }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-admin.empty icon="ri-folder-3-line" title="No sales in this period" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($canCost)<div class="card-footer fs-12 text-muted">Laser material is costed in the Profit &amp; loss report (laser material used).</div>@endif
            </div>
        @elseif ($tab === 'payments')
            <div class="row g-3">
                <div class="{{ $channels ? 'col-xl-7' : 'col-12' }}">
                    <div class="card h-100 mb-0">
                        <div class="card-header"><h6 class="card-title mb-0">Payment methods</h6></div>
                        <div class="table-responsive">
                            <table class="table table-mw mb-0">
                                <thead class="table-light"><tr><th>Method</th><th class="text-end">Invoices</th><th class="text-end">Amount</th><th class="text-end">Share</th></tr></thead>
                                <tbody>
                                    @php $payTotal = array_sum(array_column($payments, 'amount')); @endphp
                                    @forelse ($payments as $row)
                                        <tr><td class="fw-medium">{{ $row['name'] }}</td><td class="text-end">{{ $row['invoices'] }}</td><td class="text-end fw-semibold">{{ money($row['amount']) }}</td><td class="text-end">{{ $payTotal ? number_format($row['amount'] / $payTotal * 100, 1) : 0 }}%</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No payments in this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer fs-12 text-muted">Khata = sold on credit (customer owes it). Exchange credit = value of returned items put towards a new sale.</div>
                    </div>
                </div>
                @if ($channels)
                    <div class="col-xl-5">
                        <div class="card h-100 mb-0">
                            <div class="card-header"><h6 class="card-title mb-0">Channels</h6></div>
                            <div class="table-responsive">
                                <table class="table table-mw mb-0">
                                    <thead class="table-light"><tr><th>Channel</th><th class="text-end">Invoices</th><th class="text-end">Amount</th></tr></thead>
                                    <tbody>@foreach ($channels as $row)<tr><td class="fw-medium">{{ $row['name'] }}</td><td class="text-end">{{ $row['invoices'] }}</td><td class="text-end fw-semibold">{{ money($row['amount']) }}</td></tr>@endforeach</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="row g-3">
                <div class="col-xl-5">
                    <div class="card h-100 mb-0">
                        <div class="card-header"><h6 class="card-title mb-0">Sales by staff</h6></div>
                        <div class="table-responsive">
                            <table class="table table-mw mb-0">
                                <thead class="table-light"><tr><th>Staff</th><th class="text-end">Invoices</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                    @forelse ($staff as $row)<tr><td class="fw-medium">{{ $row['name'] }}</td><td class="text-end">{{ $row['invoices'] }}</td><td class="text-end fw-semibold">{{ money($row['amount']) }}</td></tr>
                                    @empty<tr><td colspan="3" class="text-center text-muted py-4">No sales in this period.</td></tr>@endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="card h-100 mb-0">
                        <div class="card-header"><h6 class="card-title mb-0">Top customers</h6></div>
                        <div class="table-responsive">
                            <table class="table table-mw mb-0">
                                <thead class="table-light"><tr><th>Customer</th><th>Type</th><th class="text-end">Invoices</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                    @forelse ($customers as $row)
                                        <tr><td class="fw-medium">{{ $row['name'] }}<div class="fs-12 text-muted">{{ $row['phone'] }}</div></td>
                                            <td>@if ($row['type'])<span class="badge {{ $row['type'] === 'Wholesale' ? 'bg-warning-subtle text-warning' : 'bg-light text-muted' }}">{{ $row['type'] }}</span>@endif</td>
                                            <td class="text-end">{{ $row['invoices'] }}</td><td class="text-end fw-semibold">{{ money($row['amount']) }}</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No sales in this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <p class="fs-12 text-muted mt-3 mb-0">Sales count when paid at the POS or when an online order is delivered. Returns count on the day they were made. Net sales = sales − returns.</p>
</div>
