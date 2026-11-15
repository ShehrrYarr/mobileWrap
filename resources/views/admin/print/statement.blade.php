@extends('admin.print.layout', ['paper' => 'a4'])

@section('title', $s->title.' — '.$s->partyName)

@section('heading')
    <h2>{{ strtoupper($s->title) }}</h2>
    <div class="small muted">
        @if ($s->from || $s->to)
            {{ $s->from ? \Illuminate\Support\Carbon::parse($s->from)->format('d M Y') : 'Start' }} – {{ $s->to ? \Illuminate\Support\Carbon::parse($s->to)->format('d M Y') : 'Today' }}
        @else
            All transactions
        @endif
    </div>
    <div class="small muted">Printed {{ now()->format('d M Y h:i a') }}</div>
@endsection

@section('body')
    <div class="row" style="align-items: flex-end; margin-bottom: 12px">
        <div>
            <div class="small muted">{{ $s->type === 'account' ? 'Account' : 'Statement for' }}</div>
            <div style="font-size: 16px; font-weight: 700">{{ $s->partyName }}</div>
            @foreach ($s->partyLines as $line)<div class="small muted">{{ $line }}</div>@endforeach
        </div>
    </div>

    <div class="grid" style="margin-bottom: 14px">
        <div class="box"><span class="small muted">Opening balance</span><b>{{ money($s->opening) }}</b></div>
        <div class="box"><span class="small muted">{{ $s->debitLabel }}</span><b>{{ money($s->totalDebit) }}</b></div>
        <div class="box"><span class="small muted">{{ $s->creditLabel }}</span><b>{{ money($s->totalCredit) }}</b></div>
        <div class="box" style="border-color: #111"><span class="small muted">Closing {{ strtolower($s->balanceLabel) }}</span><b>{{ money($s->closing) }}</b></div>
    </div>

    <table>
        <thead>
            <tr><th style="width: 80px">Date</th><th>Details</th><th class="num">{{ $s->debitLabel }}</th><th class="num">{{ $s->creditLabel }}</th><th class="num">Balance</th></tr>
        </thead>
        <tbody>
            <tr><td></td><td class="muted"><i>Opening balance</i></td><td></td><td></td><td class="num">{{ money($s->opening) }}</td></tr>
            @forelse ($s->rows as $r)
                <tr>
                    <td>{{ $r->date->format('d/m/Y') }}</td>
                    <td><b style="text-transform: capitalize">{{ $r->type }}</b>@if ($r->ref) · {{ $r->ref }}@endif<div class="small muted">{{ $r->note }}</div></td>
                    <td class="num">{{ $r->debit > 0 ? money($r->debit) : '' }}</td>
                    <td class="num">{{ $r->credit > 0 ? money($r->credit) : '' }}</td>
                    <td class="num">{{ money($r->balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted" style="padding: 24px">No transactions in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td></td><td>Totals / closing balance</td><td class="num">{{ money($s->totalDebit) }}</td><td class="num">{{ money($s->totalCredit) }}</td><td class="num">{{ money($s->closing) }}</td></tr>
        </tfoot>
    </table>

    @if ($s->type === 'customer' && $s->closing > 0)
        <p style="margin-top: 16px"><b>Amount due: {{ money($s->closing) }}.</b> Please clear your balance at your earliest convenience. Thank you for your business.</p>
    @endif

    <div class="sign"><div>Prepared by {{ auth('web')->user()?->name }}</div><div>{{ $s->type === 'account' ? 'Checked by' : 'Received / acknowledged' }}</div></div>
@endsection
