@extends('admin.reports.pdf.layout')

@section('content')
    @php
        $R = \App\Services\ReportService::class;
        $share = fn ($v) => $s['total'] > 0 ? number_format($v / $s['total'] * 100, 1).'%' : '—';
    @endphp
    @include('admin.reports.pdf.cards', ['cards' => [
        ['Total expenses', money($s['total']), $prev['total'] > 0 ? $R::pct($R::change($s['total'], $prev['total'])).' vs previous period ('.money($prev['total']).')' : 'Nothing spent in the previous period'],
        ['Entries', number_format($s['count']), 'Avg '.money($s['average']).' · '.money($s['per_day']).'/day'],
        ['Paid from cash', money($s['cash']), $share($s['cash']).' of expenses'],
        ['Paid from banks', money($s['bank']), $share($s['bank']).' of expenses'],
    ]])

    <table class="split" style="margin-top: 4px">
        <tr>
            <td class="col col-l">
                <h2>By category</h2>
                <table class="data">
                    <thead><tr><th>Category</th><th class="r">Entries</th><th class="r">Amount</th><th class="r">Share</th><th class="r">Previous</th></tr></thead>
                    <tbody>
                        @forelse ($byCategory as $c)
                            <tr><td>{{ $c['name'] }}</td><td class="r">{{ $c['n'] }}</td><td class="r b">{{ money($c['amount']) }}</td><td class="r">{{ $share($c['amount']) }}</td>
                                <td class="r muted">{{ isset($prevByCategory[$c['id']]) ? money($prevByCategory[$c['id']]['amount']) : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="c muted">No expenses in this period.</td></tr>
                        @endforelse
                        @foreach ($prevByCategory as $id => $c)
                            @continue(collect($byCategory)->contains('id', $id))
                            <tr class="muted"><td>{{ $c['name'] }}</td><td class="r">—</td><td class="r">{{ money(0) }}</td><td></td><td class="r">{{ money($c['amount']) }}</td></tr>
                        @endforeach
                        @if (count($byCategory) > 1 || count($prevByCategory))
                            <tr class="total"><td>Total</td><td class="r">{{ $s['count'] }}</td><td class="r">{{ money($s['total']) }}</td><td></td><td class="r">{{ money($prev['total']) }}</td></tr>
                        @endif
                    </tbody>
                </table>
                <div class="note" style="margin-top: 3px">Previous period: {{ $prevRange->label() }}</div>
            </td>
            <td class="col col-r">
                <h2>Paid from</h2>
                <table class="data">
                    <thead><tr><th>Cash / bank account</th><th class="r">Entries</th><th class="r">Amount</th><th class="r">Share</th></tr></thead>
                    <tbody>
                        @forelse ($byAccount as $a)
                            <tr><td>{{ $a['name'] }}</td><td class="r">{{ $a['n'] }}</td><td class="r b">{{ money($a['amount']) }}</td><td class="r">{{ $share($a['amount']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="c muted">Nothing paid in this period.</td></tr>
                        @endforelse
                        @if (count($byAccount) > 1)
                            <tr class="total"><td>Total</td><td class="r">{{ $s['count'] }}</td><td class="r">{{ money($s['total']) }}</td><td></td></tr>
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @if ($months)
        <h2>Month by month</h2>
        <table class="data">
            <thead><tr><th>Month</th><th class="r">Entries</th><th class="r">From cash</th><th class="r">From banks</th><th class="r">Total</th></tr></thead>
            <tbody>
                @foreach ($months as $m)
                    <tr @if (! $m['n']) class="muted" @endif><td>{{ $m['label'] }}</td><td class="r">{{ $m['n'] ?: '—' }}</td><td class="r">{{ $m['cash'] ? money($m['cash']) : '—' }}</td><td class="r">{{ $m['bank'] ? money($m['bank']) : '—' }}</td><td class="r b">{{ $m['amount'] ? money($m['amount']) : '—' }}</td></tr>
                @endforeach
                <tr class="total"><td>Total</td><td class="r">{{ $s['count'] }}</td><td class="r">{{ money($s['cash']) }}</td><td class="r">{{ money($s['bank']) }}</td><td class="r">{{ money($s['total']) }}</td></tr>
            </tbody>
        </table>
    @endif

    <h2>All expenses</h2>
    <table class="data">
        <thead><tr><th style="width: 58px">Date</th><th>Category</th><th>Note</th><th>Paid from</th><th>Recorded by</th><th class="r">Amount</th></tr></thead>
        <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td class="nowrap">{{ $e->date->format('d M Y') }}</td>
                    <td>{{ $e->category?->name }}</td>
                    <td>{{ $e->note ?: '—' }}@if ($e->edited_at) <span class="muted">(edited)</span>@endif</td>
                    <td class="nowrap">{{ \App\Services\FinanceService::accountLabel($e->account) }}</td>
                    <td class="muted">{{ $e->user?->name ?? '—' }}</td>
                    <td class="r b nowrap">{{ money($e->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c muted">No expenses in this period.</td></tr>
            @endforelse
            @if (count($entries))
                <tr class="grand"><td colspan="5">Total · {{ count($entries) }} expense(s)</td><td class="r nowrap">{{ money($s['total']) }}</td></tr>
            @endif
        </tbody>
    </table>

    <div class="note">Expenses count on the date entered on them. Vendor payments for stock aren't expenses — stock is costed when it's sold (see the Profit &amp; loss report).</div>
@endsection
