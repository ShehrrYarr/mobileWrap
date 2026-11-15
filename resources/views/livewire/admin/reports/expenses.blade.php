<?php

use App\Livewire\Concerns\WithReportPeriod;
use App\Models\ExpenseCategory;
use App\Services\FinanceService;
use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Expense report')] class extends Component
{
    use WithPagination, WithReportPeriod;

    #[Url(except: '')]
    public string $category = '';

    /** "cash:1" / "bank:2" */
    #[Url(except: '')]
    public string $account = '';

    /** categories | accounts | days | list */
    #[Url(except: 'categories')]
    public string $tab = 'categories';

    public function updated($prop): void
    {
        if (in_array($prop, ['period', 'from', 'to', 'category', 'account', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $reports = app(ReportService::class);
        $range = $this->periodRange();
        $prevRange = $range->previous();
        $cat = (int) $this->category ?: null;
        $acc = FinanceService::accountColumns($this->account) ? $this->account : null;

        $rows = match ($this->tab) {
            'accounts' => ['byAccount' => $reports->expensesByAccount($range, $cat, $acc)],
            'days' => ['days' => $reports->expensesByDay($range, $cat, $acc)],
            'list' => ['entries' => $reports->expenseEntries($range, $cat, $acc)->with('editor:id,name')->paginate(50)],
            default => [
                'byCategory' => $reports->expensesByCategory($range, $cat, $acc),
                'prevByCategory' => collect($reports->expensesByCategory($prevRange, $cat, $acc))->keyBy('id'),
            ],
        };

        return $rows + [
            'range' => $range,
            'prevRange' => $prevRange,
            's' => $reports->expenseSummary($range, $cat, $acc),
            'prev' => $reports->expenseSummary($prevRange, $cat, $acc),
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
            'accounts' => FinanceService::accountList(),
            'canManage' => auth('web')->user()->can('finance.manage'),
            'manageUrl' => route('admin.finance.expenses', $this->periodQuery(['category' => $cat, 'account' => $acc])),
            'pdfUrl' => route('admin.reports.pdf', ['report' => 'expenses'] + $this->periodQuery(['category' => $cat, 'account' => $acc])),
        ];
    }
}; ?>

<div>
    @php $R = \App\Services\ReportService::class; @endphp
    <x-admin.page-header title="Expense report" subtitle="Running costs by category and by the cash drawer or bank account they were paid from.">
        <x-slot:actions>
            @if ($canManage)<a href="{{ $manageUrl }}" wire:navigate class="btn btn-sm btn-light"><i class="ri-wallet-3-line me-1"></i>Manage expenses</a>@endif
            <a href="{{ $pdfUrl }}" data-pdf class="btn btn-sm btn-primary"><i class="ri-file-pdf-2-line me-1"></i>Download PDF</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.report-period :period="$period" :range="$range">
        <div>
            <label class="form-label fs-12 text-muted mb-1">Category</label>
            <select class="form-select" style="min-width: 160px" wire:model.live="category"><option value="">All categories</option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label fs-12 text-muted mb-1">Paid from</label>
            <select class="form-select" style="min-width: 170px" wire:model.live="account"><option value="">Cash &amp; all banks</option>@foreach ($accounts as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
    </x-admin.report-period>

    <div class="row g-3 mb-4" wire:loading.class="opacity-50">
        <div class="col-6 col-xl-3"><x-admin.stat label="Total expenses" :value="money($s['total'])" :change="$R::change($s['total'], $prev['total'])" :invert="true" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Entries" :value="number_format($s['count'])" :sub="'Avg '.money($s['average']).' · '.money($s['per_day']).'/day'" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Paid from cash" :value="money($s['cash'])" :sub="$s['total'] > 0 ? number_format($s['cash'] / $s['total'] * 100, 1).'% of expenses' : null" /></div>
        <div class="col-6 col-xl-3"><x-admin.stat label="Paid from banks" :value="money($s['bank'])" :sub="$s['total'] > 0 ? number_format($s['bank'] / $s['total'] * 100, 1).'% of expenses' : null" /></div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach (['categories' => ['ri-price-tag-3-line', 'By category'], 'accounts' => ['ri-safe-2-line', 'Cash & banks'], 'days' => ['ri-calendar-line', $range->groupsByMonth() ? 'Month by month' : 'Day by day'], 'list' => ['ri-list-check-2', 'All expenses']] as $key => [$icon, $label])
            <li class="nav-item"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" wire:click="$set('tab', '{{ $key }}')"><i class="{{ $icon }} me-1"></i>{{ $label }}</button></li>
        @endforeach
    </ul>

    <div class="card" wire:loading.class="opacity-50">
        <div class="table-responsive">
            @if ($tab === 'accounts')
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Paid from</th><th class="text-end">Entries</th><th class="text-end">Amount</th><th class="text-end">Share</th></tr></thead>
                    <tbody>
                        @forelse ($byAccount as $a)
                            @php $share = $s['total'] > 0 ? $a['amount'] / $s['total'] * 100 : 0; @endphp
                            <tr>
                                <td class="fw-medium"><i class="{{ $a['kind'] === 'cash' ? 'ri-money-dollar-box-line' : 'ri-bank-line' }} text-muted me-1"></i>{{ $a['name'] }}</td>
                                <td class="text-end">{{ $a['n'] }}</td>
                                <td class="text-end fw-semibold">{{ money($a['amount']) }}</td>
                                <td class="text-end" style="min-width: 140px"><div class="d-flex align-items-center gap-2 justify-content-end"><div class="progress flex-grow-1" style="height: 6px; max-width: 80px"><div class="progress-bar bg-info" style="width: {{ $share }}%"></div></div><span class="fs-12">{{ number_format($share, 1) }}%</span></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-admin.empty icon="ri-safe-2-line" title="No expenses in this period" /></td></tr>
                        @endforelse
                    </tbody>
                    @if (count($byAccount) > 1)
                        <tfoot class="table-light fw-bold"><tr><td>Total</td><td class="text-end">{{ $s['count'] }}</td><td class="text-end">{{ money($s['total']) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            @elseif ($tab === 'days')
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>{{ $range->groupsByMonth() ? 'Month' : 'Date' }}</th><th class="text-end">Entries</th><th class="text-end">From cash</th><th class="text-end">From banks</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @foreach ($days as $d)
                            <tr class="{{ $d['n'] ? '' : 'text-muted' }}">
                                <td class="text-nowrap">{{ $d['label'] }}</td><td class="text-end">{{ $d['n'] ?: '—' }}</td>
                                <td class="text-end">{{ $d['cash'] ? money($d['cash']) : '—' }}</td><td class="text-end">{{ $d['bank'] ? money($d['bank']) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ $d['amount'] ? money($d['amount']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold"><tr><td>Total</td><td class="text-end">{{ $s['count'] }}</td><td class="text-end">{{ money($s['cash']) }}</td><td class="text-end">{{ money($s['bank']) }}</td><td class="text-end">{{ money($s['total']) }}</td></tr></tfoot>
                </table>
            @elseif ($tab === 'list')
                <table class="table table-mw mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Note</th><th>Paid from</th><th>Recorded by</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($entries as $e)
                            <tr wire:key="re-{{ $e->id }}">
                                <td class="fs-13 text-nowrap">{{ $e->date->format('d M Y') }}</td>
                                <td><span class="badge bg-light text-body">{{ $e->category?->name }}</span></td>
                                <td class="fs-13">{{ $e->note ?: '—' }}
                                    @if ($e->attachment)<a href="{{ upload_url($e->attachment) }}" target="_blank" class="ms-1" title="View receipt" data-no-loader><i class="ri-attachment-2"></i></a>@endif
                                    @if ($e->edited_at)<span class="badge bg-warning-subtle text-warning ms-1" title="Edited by {{ $e->editor?->name ?? 'someone' }} on {{ $e->edited_at->format('d M Y, h:i a') }}">edited</span>@endif
                                </td>
                                <td class="fs-13 text-nowrap">{{ \App\Services\FinanceService::accountLabel($e->account) }}</td>
                                <td class="fs-13 text-muted">{{ $e->user?->name ?? '—' }}</td>
                                <td class="text-end fw-semibold text-nowrap">{{ money($e->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="ri-wallet-3-line" title="No expenses in this period" /></td></tr>
                        @endforelse
                    </tbody>
                    @if ($entries->total())
                        <tfoot class="table-light fw-bold"><tr><td colspan="5">{{ $entries->total() }} expense(s)</td><td class="text-end text-nowrap">{{ money($s['total']) }}</td></tr></tfoot>
                    @endif
                </table>
            @else
                <table class="table table-mw mb-0">
                    <thead class="table-light">
                        <tr><th>Category</th><th class="text-end">Entries</th><th class="text-end">Amount</th><th class="text-end">Share</th><th class="text-end text-muted">Previous<div class="fw-normal fs-11">{{ $prevRange->label() }}</div></th><th class="text-end">Change</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($byCategory as $c)
                            @php
                                $share = $s['total'] > 0 ? $c['amount'] / $s['total'] * 100 : 0;
                                $before = (float) ($prevByCategory[$c['id']]['amount'] ?? 0);
                                $chg = $R::change($c['amount'], $before);
                            @endphp
                            <tr>
                                <td class="fw-medium">{{ $c['name'] }}<div class="fs-12 text-muted">Largest {{ money($c['largest']) }}</div></td>
                                <td class="text-end">{{ $c['n'] }}</td>
                                <td class="text-end fw-semibold">{{ money($c['amount']) }}</td>
                                <td class="text-end" style="min-width: 140px"><div class="d-flex align-items-center gap-2 justify-content-end"><div class="progress flex-grow-1" style="height: 6px; max-width: 80px"><div class="progress-bar" style="width: {{ $share }}%"></div></div><span class="fs-12">{{ number_format($share, 1) }}%</span></div></td>
                                <td class="text-end text-muted">{{ $before ? money($before) : '—' }}</td>
                                <td class="text-end {{ $chg === null || abs($chg) < 0.05 ? 'text-muted' : ($chg > 0 ? 'text-danger' : 'text-success') }}">{{ $chg === null ? 'new' : $R::pct($chg) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="ri-price-tag-3-line" title="No expenses in this period" /></td></tr>
                        @endforelse
                        @foreach ($prevByCategory as $id => $c)
                            @continue(collect($byCategory)->contains('id', $id))
                            <tr class="text-muted"><td>{{ $c['name'] }}</td><td class="text-end">—</td><td class="text-end">{{ money(0) }}</td><td></td><td class="text-end">{{ money($c['amount']) }}</td><td class="text-end text-success">−100%</td></tr>
                        @endforeach
                    </tbody>
                    @if ($byCategory || count($prevByCategory))
                        <tfoot class="table-light fw-bold"><tr><td>Total</td><td class="text-end">{{ $s['count'] }}</td><td class="text-end">{{ money($s['total']) }}</td><td></td><td class="text-end text-muted">{{ money($prev['total']) }}</td><td class="text-end">{{ $R::pct($R::change($s['total'], $prev['total'])) }}</td></tr></tfoot>
                    @endif
                </table>
            @endif
        </div>
        @if ($tab === 'list' && $entries->hasPages())<div class="card-footer">{{ $entries->links() }}</div>@endif
    </div>

    <p class="fs-12 text-muted mt-3 mb-0">Expenses count on the date entered on them. Vendor payments for stock aren't expenses — stock is costed when it's sold (see Profit &amp; loss).</p>
</div>
