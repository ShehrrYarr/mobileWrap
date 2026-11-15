<?php

use App\Livewire\Concerns\WithReportPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Profit & loss')] class extends Component
{
    use WithReportPeriod;

    public function with(): array
    {
        $reports = app(ReportService::class);
        $range = $this->periodRange();

        return [
            'range' => $range,
            'prevRange' => $range->previous(),
            'pl' => $reports->profitLoss($range),
            'prev' => $reports->profitLoss($range->previous()),
            'pdfUrl' => route('admin.reports.pdf', ['report' => 'profit-loss'] + $this->periodQuery()),
        ];
    }
}; ?>

<div>
    @php
        $R = \App\Services\ReportService::class;
        $prevExpenses = collect($prev['expenses'])->keyBy('name');
        $line = fn ($label, $now, $before, $invert = false) => compact('label', 'now', 'before', 'invert');
    @endphp
    <x-admin.page-header title="Profit & loss" subtitle="Revenue, cost of sales, expenses and what's left — compared with the previous period.">
        <x-slot:actions>
            <a href="{{ $pdfUrl }}" data-pdf class="btn btn-sm btn-primary"><i class="ri-file-pdf-2-line me-1"></i>Download PDF</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.report-period :period="$period" :range="$range" />

    <div class="row g-3 mb-4" wire:loading.class="opacity-50">
        <div class="col-6 col-xl-3"><x-admin.stat label="Net revenue" :value="money($pl['revenue'])" :change="$R::change($pl['revenue'], $prev['revenue'])" :sub="$pl['invoices'].' invoices'" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Gross profit" :value="money($pl['gross_profit'])" :sub="$pl['gross_margin'] === null ? null : number_format($pl['gross_margin'], 1).'% margin'" tone="{{ $pl['gross_profit'] < 0 ? 'danger' : 'success' }}" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Expenses" :value="money($pl['expense_total'])" :change="$R::change($pl['expense_total'], $prev['expense_total'])" :invert="true" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat :label="$pl['net_profit'] < 0 ? 'Net loss' : 'Net profit'" :value="money($pl['net_profit'])" :sub="$pl['net_margin'] === null ? null : number_format($pl['net_margin'], 1).'% of revenue'" tone="{{ $pl['net_profit'] < 0 ? 'danger' : 'success' }}" /></div>
    </div>

    <div class="card" wire:loading.class="opacity-50">
        <div class="table-responsive">
            <table class="table table-mw mb-0">
                <thead class="table-light">
                    <tr><th></th><th class="text-end">{{ $range->label() }}</th><th class="text-end text-muted">Previous<div class="fw-normal fs-11">{{ $prevRange->label() }}</div></th><th class="text-end" style="width: 110px">Change</th></tr>
                </thead>
                <tbody>
                    <tr class="table-light"><td colspan="4" class="fw-semibold">Revenue</td></tr>
                    @foreach ([
                        $line('Product sales (before discounts)', $pl['gross_sales'], $prev['gross_sales']),
                        $line('Less: discounts', -$pl['discounts'], -$prev['discounts']),
                        $line('Laser cutting fees', $pl['laser'], $prev['laser']),
                        $line('Delivery charges', $pl['delivery'], $prev['delivery']),
                        $line('Less: customer returns', -$pl['returns'], -$prev['returns']),
                    ] as $r)
                        @include('admin.reports.partials.pl-row', $r)
                    @endforeach
                    <tr class="fw-bold border-top border-dark"><td>Net revenue</td><td class="text-end">{{ money($pl['revenue']) }}</td><td class="text-end text-muted">{{ money($prev['revenue']) }}</td><td class="text-end">{{ $R::pct($R::change($pl['revenue'], $prev['revenue'])) }}</td></tr>

                    <tr class="table-light"><td colspan="4" class="fw-semibold">Cost of sales</td></tr>
                    @foreach ([
                        $line('Cost of goods sold', $pl['cogs'], $prev['cogs'], true),
                        $line('Less: cost of restocked returns', -$pl['restocked_cost'], -$prev['restocked_cost'], true),
                        $line('Laser material used', $pl['laser_material'], $prev['laser_material'], true),
                        $line('Wastage (failed cuts, damaged stock)', $pl['wastage'], $prev['wastage'], true),
                        $line('Stock adjustments (net)', $pl['adjustments'], $prev['adjustments'], true),
                    ] as $r)
                        @include('admin.reports.partials.pl-row', $r)
                    @endforeach
                    <tr class="fw-bold border-top border-dark"><td>Total cost of sales</td><td class="text-end">{{ money($pl['cost']) }}</td><td class="text-end text-muted">{{ money($prev['cost']) }}</td><td class="text-end">{{ $R::pct($R::change($pl['cost'], $prev['cost'])) }}</td></tr>

                    <tr class="fw-bold fs-5 {{ $pl['gross_profit'] < 0 ? 'table-danger' : 'table-success' }}">
                        <td>Gross profit @if ($pl['gross_margin'] !== null)<span class="fs-12 fw-normal text-muted">({{ number_format($pl['gross_margin'], 1) }}% margin)</span>@endif</td>
                        <td class="text-end">{{ money($pl['gross_profit']) }}</td><td class="text-end text-muted fs-6">{{ money($prev['gross_profit']) }}</td><td class="text-end fs-6">{{ $R::pct($R::change($pl['gross_profit'], $prev['gross_profit'])) }}</td>
                    </tr>

                    <tr class="table-light"><td colspan="4" class="fw-semibold">Expenses <a href="{{ route('admin.finance.expenses') }}" wire:navigate class="fs-12 fw-normal ms-1">manage</a></td></tr>
                    @forelse ($pl['expenses'] as $e)
                        @include('admin.reports.partials.pl-row', $line($e['name'], (float) $e['amount'], (float) ($prevExpenses[$e['name']]['amount'] ?? 0), true))
                    @empty
                        <tr><td class="ps-4 text-muted">No expenses recorded in this period</td><td class="text-end">{{ money(0) }}</td><td></td><td></td></tr>
                    @endforelse
                    @foreach ($prev['expenses'] as $e)
                        @continue(collect($pl['expenses'])->contains('name', $e['name']))
                        @include('admin.reports.partials.pl-row', $line($e['name'], 0.0, (float) $e['amount'], true))
                    @endforeach
                    <tr class="fw-bold border-top border-dark"><td>Total expenses</td><td class="text-end">{{ money($pl['expense_total']) }}</td><td class="text-end text-muted">{{ money($prev['expense_total']) }}</td><td class="text-end">{{ $R::pct($R::change($pl['expense_total'], $prev['expense_total'])) }}</td></tr>

                    <tr class="fw-bold fs-5 {{ $pl['net_profit'] < 0 ? 'table-danger' : 'table-success' }}">
                        <td>{{ $pl['net_profit'] < 0 ? 'Net loss' : 'Net profit' }}</td>
                        <td class="text-end">{{ money($pl['net_profit']) }}</td><td class="text-end text-muted fs-6">{{ money($prev['net_profit']) }}</td><td class="text-end fs-6">{{ $R::pct($R::change($pl['net_profit'], $prev['net_profit'])) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <p class="fs-12 text-muted mt-3 mb-0">
        Sales count when paid at the POS or when an online order is delivered; returns on the day they were made. Cost of goods uses the weighted-average cost saved on each sale.
        Laser material, wastage and stock adjustments are valued at cost from the stock ledger. Vendor payments aren't expenses — stock is costed when it's sold.
    </p>
</div>
