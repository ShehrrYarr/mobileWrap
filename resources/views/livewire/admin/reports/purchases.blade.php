<?php

use App\Livewire\Concerns\WithReportPeriod;
use App\Models\Vendor;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Purchase report')] class extends Component
{
    use WithReportPeriod;

    #[Url(except: '')]
    public string $vendor = '';

    /** vendors | bills | products */
    #[Url(except: 'vendors')]
    public string $tab = 'vendors';

    public function with(): array
    {
        $reports = app(ReportService::class);
        $range = $this->periodRange();
        $vendorId = (int) $this->vendor ?: null;

        $rows = match ($this->tab) {
            'bills' => ['bills' => $reports->purchaseBills($range, $vendorId)],
            'products' => ['products' => $reports->purchasesByProduct($range, $vendorId)],
            default => ['byVendor' => $reports->purchasesByVendor($range, $vendorId)],
        };

        return $rows + [
            'range' => $range,
            's' => $reports->purchaseSummary($range, $vendorId),
            'prev' => $reports->purchaseSummary($range->previous(), $vendorId),
            'vendors' => Vendor::orderBy('name')->pluck('name', 'id'),
            'pdfUrl' => route('admin.reports.pdf', ['report' => 'purchases'] + $this->periodQuery(['vendor' => $vendorId])),
        ];
    }
}; ?>

<div>
    @php $R = \App\Services\ReportService::class; @endphp
    <x-admin.page-header title="Purchase report" subtitle="Stock bought from vendors, returns and what you paid them.">
        <x-slot:actions>
            <a href="{{ $pdfUrl }}" data-pdf class="btn btn-sm btn-primary"><i class="ri-file-pdf-2-line me-1"></i>Download PDF</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.report-period :period="$period" :range="$range">
        <div>
            <label class="form-label fs-12 text-muted mb-1">Vendor</label>
            <select class="form-select" wire:model.live="vendor"><option value="">All vendors</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
    </x-admin.report-period>

    <div class="row g-3 mb-4" wire:loading.class="opacity-50">
        <div class="col-6 col-xl-3"><x-admin.stat label="Goods received" :value="money($s['received'])" :change="$R::change($s['received'], $prev['received'])" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Returned to vendors" :value="money($s['returned'])" :sub="'Net purchases '.money($s['net'])" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Paid to vendors" :value="money($s['paid'])" :change="$R::change($s['paid'], $prev['paid'])" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Payable now" :value="money($s['payable_now'])" :sub="money($s['awaiting']).' ordered, not yet received'" tone="{{ $s['payable_now'] > 0 ? 'danger' : '' }}" /></div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach (['vendors' => ['ri-truck-line', 'By vendor'], 'bills' => ['ri-file-list-3-line', 'Purchase bills'], 'products' => ['ri-archive-stack-line', 'Stock received']] as $key => [$icon, $label])
            <li class="nav-item"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="$set('tab', '{{ $key }}')"><i class="{{ $icon }} me-1"></i>{{ $label }}</button></li>
        @endforeach
    </ul>

    <div class="card" wire:loading.class="opacity-50">
        <div class="table-responsive">
            @if ($tab === 'vendors')
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Vendor</th><th class="text-end">Received</th><th class="text-end">Returned</th><th class="text-end">Net</th><th class="text-end">Paid</th><th class="text-end">Balance today</th></tr></thead>
                    <tbody>
                        @forelse ($byVendor as $v)
                            <tr>
                                <td class="fw-medium">{{ $v['name'] }}</td><td class="text-end">{{ money($v['received']) }}</td><td class="text-end text-muted">{{ (float) $v['returned'] ? money($v['returned']) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ money($v['received'] - $v['returned']) }}</td><td class="text-end">{{ money($v['paid']) }}</td>
                                <td class="text-end {{ (float) $v['balance'] > 0 ? 'text-danger' : 'text-muted' }}">{{ (float) $v['balance'] > 0 ? money($v['balance']).' owed' : ((float) $v['balance'] < 0 ? money(abs($v['balance'])).' advance' : 'settled') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="ri-truck-line" title="No vendor activity in this period" /></td></tr>
                        @endforelse
                    </tbody>
                    @if (count($byVendor) > 1)
                        <tfoot class="table-light fw-bold"><tr><td>Total</td><td class="text-end">{{ money($s['received']) }}</td><td class="text-end">{{ money($s['returned']) }}</td><td class="text-end">{{ money($s['net']) }}</td><td class="text-end">{{ money($s['paid']) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            @elseif ($tab === 'bills')
                <table class="table table-hover table-mw mb-0">
                    <thead class="table-light"><tr><th>Bill date</th><th>Purchase</th><th>Vendor</th><th>Status</th><th class="text-end">Bill total</th><th class="text-end">Received</th><th class="text-end">Returned</th><th class="text-end">Paid</th><th class="text-end">Due</th></tr></thead>
                    <tbody>
                        @forelse ($bills as $po)
                            <tr class="{{ in_array($po->status, ['draft', 'cancelled']) ? 'text-muted' : '' }}">
                                <td class="text-nowrap fs-13">{{ $po->order_date->format('d M Y') }}</td>
                                <td><a href="{{ route('admin.purchases.show', $po) }}" wire:navigate class="fw-semibold">{{ $po->number }}</a>@if ($po->bill_number)<div class="fs-12 text-muted">bill {{ $po->bill_number }}</div>@endif</td>
                                <td>{{ $po->vendor?->name }}</td>
                                <td><span class="badge bg-{{ $po->statusColor() }}-subtle text-{{ $po->statusColor() }}">{{ $po->statusLabel() }}</span></td>
                                <td class="text-end">{{ money($po->total) }}</td><td class="text-end">{{ money($po->received_total) }}</td>
                                <td class="text-end">{{ (float) $po->returned_total ? money($po->returned_total) : '—' }}</td><td class="text-end">{{ money($po->paid_amount) }}</td>
                                <td class="text-end fw-semibold {{ $po->balanceDue() > 0 ? 'text-danger' : '' }}">{{ $po->balanceDue() < 0 ? 'advance '.money(abs($po->balanceDue())) : money($po->balanceDue()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-admin.empty icon="ri-file-list-3-line" title="No purchase bills dated in this period" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty received</th><th class="text-end">Avg cost</th><th class="text-end">Value</th><th class="text-end">Returned</th><th class="text-end">Returned value</th></tr></thead>
                    <tbody>
                        @forelse ($products as $p)
                            @php $area = $p['unit'] === 'cm2'; @endphp
                            <tr>
                                <td><span class="fw-medium">{{ $p['name'] }}</span>@if ($p['label'] && $p['label'] !== 'Standard')<span class="text-muted"> — {{ $p['label'] }}</span>@endif<div class="fs-12 text-muted">{{ $p['sku'] }}</div></td>
                                <td class="text-end">{{ qty($p['qty']) }}{{ $area ? ' cm²' : '' }}</td>
                                <td class="text-end">{{ (float) $p['qty'] ? ($area ? 'Rs. '.number_format($p['value'] / $p['qty'], 4) : money($p['value'] / $p['qty'])) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ money($p['value']) }}</td>
                                <td class="text-end text-muted">{{ (float) $p['returned_qty'] ? qty($p['returned_qty']).($area ? ' cm²' : '') : '—' }}</td>
                                <td class="text-end text-muted">{{ (float) $p['returned_value'] ? money($p['returned_value']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="ri-archive-stack-line" title="No stock received in this period" /></td></tr>
                        @endforelse
                    </tbody>
                    @if ($products)
                        @php
                            $atCost = array_sum(array_column($products, 'value'));
                            $billExtras = round($s['received'] - $atCost, 2);
                        @endphp
                        <tfoot class="table-light">
                            <tr class="fw-bold"><td colspan="3">Total at stock cost</td><td class="text-end">{{ money($atCost) }}</td><td></td><td class="text-end">{{ money(array_sum(array_column($products, 'returned_value'))) }}</td></tr>
                            @if (abs($billExtras) >= 0.01)
                                <tr class="fs-13 text-muted"><td colspan="3">Bill discounts / freight (on vendor bills, not in stock cost)</td><td class="text-end">{{ $billExtras > 0 ? '+' : '' }}{{ money($billExtras) }}</td><td colspan="2"></td></tr>
                                <tr class="fw-semibold"><td colspan="3">Goods received (as billed)</td><td class="text-end">{{ money($s['received']) }}</td><td colspan="2"></td></tr>
                            @endif
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>
    </div>

    <p class="fs-12 text-muted mt-3 mb-0">Purchases count when goods are received — that's when stock and the vendor payable are booked. Bill discounts and freight change what you owe the vendor, not stock cost.</p>
</div>
