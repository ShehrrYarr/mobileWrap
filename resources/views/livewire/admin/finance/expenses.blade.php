<?php

use App\Livewire\Concerns\WithReportPeriod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Expenses')] class extends Component
{
    use WithFileUploads, WithPagination, WithReportPeriod;

    #[Url(except: '')]
    public string $category = '';

    /** "cash:1" / "bank:2" */
    #[Url(except: '')]
    public string $account = '';

    #[Url(except: '')]
    public string $search = '';

    public ?string $modal = null; // expense | categories

    /** Expense being edited (null = adding a new one). */
    public ?int $editing = null;

    public array $form = [];

    public $attachment = null;

    public bool $removeAttachment = false;

    public string $newCategory = '';

    public function updated($prop): void
    {
        if (in_array($prop, ['period', 'from', 'to', 'category', 'account', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function filterBy(string $field, string $value): void
    {
        if (in_array($field, ['category', 'account'], true)) {
            $this->{$field} = $this->{$field} === $value ? '' : $value;
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('category', 'account', 'search');
        $this->resetPage();
    }

    public function create(): void
    {
        $this->editing = null;
        $this->form = [
            'expense_category_id' => $this->category ?: ExpenseCategory::where('is_active', true)->orderBy('name')->value('id'),
            'account' => FinanceService::accountColumns($this->account) ? $this->account : array_key_first(FinanceService::accountOptions()),
            'amount' => '',
            'date' => now()->toDateString(),
            'note' => '',
        ];
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $e = Expense::findOrFail($id);
        $this->editing = $e->id;
        $this->form = [
            'expense_category_id' => $e->expense_category_id,
            'account' => $e->accountKey(),
            'amount' => rtrim(rtrim(number_format((float) $e->amount, 2, '.', ''), '0'), '.'),
            'date' => $e->date->toDateString(),
            'note' => (string) $e->note,
        ];
        $this->openForm();
    }

    protected function openForm(): void
    {
        $this->attachment = null;
        $this->removeAttachment = false;
        $this->resetErrorBag();
        $this->modal = 'expense';
    }

    public function save(ExpenseService $expenses): void
    {
        $this->validate([
            'form.expense_category_id' => 'required|exists:expense_categories,id',
            'form.account' => 'required|string',
            'form.amount' => 'required|numeric|min:1|max:99999999',
            'form.date' => 'required|date|before_or_equal:today',
            'form.note' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ], [], ['form.expense_category_id' => 'category', 'form.account' => 'paid from', 'form.amount' => 'amount', 'form.date' => 'date']);

        $account = FinanceService::accountFromKey($this->form['account']);
        if (! $account) {
            $this->addError('form.account', 'Choose the cash drawer or bank account the money came from.');

            return;
        }
        $expense = $this->editing ? Expense::with('account')->findOrFail($this->editing) : null;
        $before = $expense ? ['key' => $expense->accountKey(), 'label' => FinanceService::accountLabel($expense->account), 'amount' => (float) $expense->amount] : null;

        $path = $this->attachment?->store('expenses/'.now()->format('Y/m'), 'uploads');
        try {
            $expense
                ? $expenses->update($expense, $this->form, $account, $path, $this->removeAttachment)
                : $expenses->create($this->form, $account, $path);
        } catch (ValidationException $e) {
            if ($path) {
                Storage::disk('uploads')->delete($path);
            }
            $this->addError('form.amount', collect($e->errors())->flatten()->first());

            return;
        }

        $this->modal = null;
        $this->editing = null;
        $this->attachment = null;
        $this->dispatch('toast', type: 'success', message: $before
            ? $this->updatedMessage($before, $account)
            : 'Expense of '.money($this->form['amount']).' recorded.');
    }

    /** Tell the user exactly where the money moved. */
    protected function updatedMessage(array $before, $account): string
    {
        $amount = round((float) $this->form['amount'], 2);
        $label = FinanceService::accountLabel($account);

        if ($before['key'] !== $this->form['account']) {
            return 'Expense updated — '.money($before['amount']).' put back on '.$before['label'].' and '.money($amount).' taken from '.$label.'.';
        }
        $diff = round($amount - $before['amount'], 2);
        if (abs($diff) >= 0.01) {
            return 'Expense updated — '.money(abs($diff)).($diff > 0 ? ' more taken from ' : ' put back on ').$label.'.';
        }

        return 'Expense updated.';
    }

    public function delete(int $id, ExpenseService $expenses): void
    {
        $expenses->delete(Expense::findOrFail($id));
        $this->dispatch('toast', type: 'success', message: 'Expense deleted and the money put back on its account.');
    }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => 'required|string|max:60|unique:expense_categories,name'], [], ['newCategory' => 'category']);
        ExpenseCategory::create(['name' => trim($this->newCategory), 'is_active' => true]);
        $this->newCategory = '';
    }

    public function toggleCategory(int $id): void
    {
        $c = ExpenseCategory::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    public function with(): array
    {
        $reports = app(ReportService::class);
        $range = $this->periodRange();
        $cat = (int) $this->category ?: null;
        $cols = FinanceService::accountColumns($this->account);
        $acc = $cols ? $this->account : null;
        $term = trim($this->search);

        $list = Expense::query()
            ->whereBetween('date', [$range->from->toDateString(), $range->to->toDateString()])
            ->when($cat, fn ($q) => $q->where('expense_category_id', $cat))
            ->when($cols, fn ($q) => $q->where('account_type', $cols[0])->where('account_id', $cols[1]))
            ->when($term !== '', fn ($q) => $q->where('note', 'like', '%'.addcslashes($term, '%_\\').'%'));

        $options = [];
        $editingExpense = null;
        if ($this->modal === 'expense') {
            $options = FinanceService::accountOptions();
            $editingExpense = $this->editing ? Expense::with('account')->find($this->editing) : null;
            // An expense paid from an account that has since been closed keeps that account.
            if ($editingExpense && ($key = $editingExpense->accountKey()) && ! isset($options[$key])) {
                $options[$key] = FinanceService::accountLabel($editingExpense->account).' (closed)';
            }
        }

        $s = $reports->expenseSummary($range, $cat, $acc);
        $prev = $reports->expenseSummary($range->previous(), $cat, $acc);
        $canReport = auth('web')->user()->can('reports.view');

        return [
            'range' => $range,
            'expenses' => $list->clone()->with('category', 'account', 'user:id,name', 'editor:id,name')->latest('date')->latest('id')->paginate(25),
            'listTotal' => (float) $list->clone()->sum('amount'),
            's' => $s,
            'change' => ReportService::change($s['total'], $prev['total']),
            'prevTotal' => $prev['total'],
            'byCategory' => $reports->expensesByCategory($range, null, $acc),
            'byAccount' => $reports->expensesByAccount($range, $cat),
            'categories' => ExpenseCategory::withCount('expenses')->orderBy('name')->get(),
            'accounts' => FinanceService::accountList(),
            'options' => $options,
            'editingExpense' => $editingExpense,
            'filtered' => $cat || $acc || $term !== '',
            'reportUrl' => $canReport ? route('admin.reports.expenses', $this->periodQuery(['category' => $cat, 'account' => $acc])) : null,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Expenses" subtitle="Rent, salaries, bills and other running costs — paid from cash or a bank account.">
        <x-slot:actions>
            <button class="btn btn-sm btn-light" wire:click="$set('modal', 'categories')"><i class="ri-price-tag-3-line me-1"></i>Categories</button>
            @if ($reportUrl)<a href="{{ $reportUrl }}" wire:navigate class="btn btn-sm btn-light"><i class="ri-bar-chart-box-line me-1"></i>Report</a>@endif
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>Add expense</button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.report-period :period="$period" :range="$range">
        <div>
            <label class="form-label fs-12 text-muted mb-1">Category</label>
            <select class="form-select" style="min-width: 160px" wire:model.live="category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label fs-12 text-muted mb-1">Paid from</label>
            <select class="form-select" style="min-width: 170px" wire:model.live="account"><option value="">Cash &amp; all banks</option>@foreach ($accounts as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label fs-12 text-muted mb-1">Search note</label>
            <input type="search" class="form-control" style="min-width: 170px" wire:model.live.debounce.400ms="search" placeholder="e.g. rent">
        </div>
        @if ($filtered)
            <div><button type="button" class="btn btn-light" wire:click="clearFilters"><i class="ri-close-line me-1"></i>Clear</button></div>
        @endif
    </x-admin.report-period>

    <div class="row g-4">
        <div class="col-xl-4 order-xl-2">
            <div class="card" wire:loading.class="opacity-50">
                <div class="card-body">
                    <div class="fs-12 text-muted">Spent · {{ $range->label() }}</div>
                    <h3 class="mb-1">{{ money($s['total']) }}</h3>
                    <div class="fs-12">
                        @if ($change !== null)
                            <span class="{{ abs($change) < 0.05 ? 'text-muted' : ($change > 0 ? 'text-danger' : 'text-success') }}"><i class="{{ $change >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>{{ \App\Services\ReportService::pct($change) }}</span>
                            <span class="text-muted">vs previous period ({{ money($prevTotal) }})</span>
                        @else
                            <span class="text-muted">{{ $s['count'] }} expense(s)</span>
                        @endif
                    </div>

                    <div class="row g-2 mt-2">
                        <div class="col-6"><div class="border rounded p-2"><div class="fs-11 text-muted"><i class="ri-money-dollar-box-line me-1"></i>From cash</div><div class="fw-semibold">{{ money($s['cash']) }}</div></div></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="fs-11 text-muted"><i class="ri-bank-line me-1"></i>From banks</div><div class="fw-semibold">{{ money($s['bank']) }}</div></div></div>
                    </div>

                    <h6 class="fs-13 mt-4 mb-2">Paid from</h6>
                    @php $accTotal = array_sum(array_column($byAccount, 'amount')); @endphp
                    @forelse ($byAccount as $row)
                        <button type="button" class="d-block w-100 text-start border-0 bg-transparent p-0 mb-3" wire:click="filterBy('account', '{{ $row['key'] }}')">
                            <div class="d-flex justify-content-between fs-13 mb-1">
                                <span class="{{ $account === $row['key'] ? 'fw-bold text-primary' : '' }}"><i class="{{ $row['kind'] === 'cash' ? 'ri-money-dollar-box-line' : 'ri-bank-line' }} me-1 text-muted"></i>{{ $row['name'] }} <span class="text-muted">({{ $row['n'] }})</span></span>
                                <span class="fw-semibold">{{ money($row['amount']) }}</span>
                            </div>
                            <div class="progress" style="height: 6px"><div class="progress-bar bg-info" style="width: {{ $accTotal > 0 ? $row['amount'] / $accTotal * 100 : 0 }}%"></div></div>
                        </button>
                    @empty
                        <p class="text-muted fs-13 mb-0">Nothing paid in this period.</p>
                    @endforelse

                    <h6 class="fs-13 mt-4 mb-2">By category</h6>
                    @php $catTotal = array_sum(array_column($byCategory, 'amount')); @endphp
                    @forelse ($byCategory as $row)
                        <button type="button" class="d-block w-100 text-start border-0 bg-transparent p-0 mb-3" wire:click="filterBy('category', '{{ $row['id'] }}')">
                            <div class="d-flex justify-content-between fs-13 mb-1">
                                <span class="{{ $category == $row['id'] ? 'fw-bold text-primary' : '' }}">{{ $row['name'] }} <span class="text-muted">({{ $row['n'] }})</span></span>
                                <span class="fw-semibold">{{ money($row['amount']) }}</span>
                            </div>
                            <div class="progress" style="height: 6px"><div class="progress-bar" style="width: {{ $catTotal > 0 ? $row['amount'] / $catTotal * 100 : 0 }}%"></div></div>
                        </button>
                    @empty
                        <p class="text-muted fs-13 mb-0">No expenses in this period.</p>
                    @endforelse
                    <p class="fs-12 text-muted mb-0">Click a bar to filter the list; click it again to clear.</p>
                </div>
            </div>
        </div>

        <div class="col-xl-8 order-xl-1">
            <div class="card" wire:loading.class="opacity-50">
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Note</th><th>Paid from</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($expenses as $e)
                                <tr wire:key="ex-{{ $e->id }}">
                                    <td class="fs-13 text-nowrap">{{ $e->date->format('d M Y') }}</td>
                                    <td><span class="badge bg-light text-body">{{ $e->category?->name }}</span></td>
                                    <td class="fs-13">
                                        {{ $e->note }}
                                        @if ($e->attachment)<a href="{{ upload_url($e->attachment) }}" target="_blank" class="ms-1" title="View receipt" data-no-loader><i class="ri-attachment-2"></i></a>@endif
                                        <div class="fs-12 text-muted">
                                            {{ $e->user?->name }}
                                            @if ($e->edited_at)<span class="badge bg-warning-subtle text-warning ms-1" title="Edited by {{ $e->editor?->name ?? 'someone' }} on {{ $e->edited_at->format('d M Y, h:i a') }}">edited</span>@endif
                                        </div>
                                    </td>
                                    <td class="fs-13 text-nowrap"><i class="{{ $e->account instanceof \App\Models\CashDrawer ? 'ri-money-dollar-box-line' : 'ri-bank-line' }} text-muted me-1"></i>{{ \App\Services\FinanceService::accountLabel($e->account) }}</td>
                                    <td class="text-end fw-semibold text-nowrap">{{ money($e->amount) }}</td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-light icon-btn-sm" wire:click="edit({{ $e->id }})" title="Edit"><i class="ri-pencil-line"></i></button>
                                        <button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete this {{ money($e->amount) }} expense? The money goes back on its account.', () => $wire.delete({{ $e->id }}))" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">
                                    @if ($filtered)
                                        <x-admin.empty icon="ri-filter-off-line" title="No expenses match these filters" text="Try another category, account or period." />
                                    @else
                                        <x-admin.empty icon="ri-wallet-3-line" title="No expenses in this period" text="Add rent, salaries, bills and other running costs here." />
                                    @endif
                                </td></tr>
                            @endforelse
                        </tbody>
                        @if ($expenses->total())
                            <tfoot class="table-light"><tr class="fw-semibold"><td colspan="4">{{ $expenses->total() }} expense(s){{ $filtered ? ' matching the filters' : '' }}</td><td class="text-end text-nowrap">{{ money($listTotal) }}</td><td></td></tr></tfoot>
                        @endif
                    </table>
                </div>
                @if ($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif
            </div>
        </div>
    </div>

    <x-admin.modal :show="$modal === 'expense'" close="$set('modal', null)" :title="$editing ? 'Edit expense' : 'Add expense'">
        <form wire:submit="save" id="ex-form" class="row g-3">
            <div class="col-md-6"><label class="form-label">Category</label>
                <select class="form-select @error('form.expense_category_id') is-invalid @enderror" wire:model="form.expense_category_id">
                    @foreach ($categories as $c)
                        @if ($c->is_active || (int) ($form['expense_category_id'] ?? 0) === $c->id)<option value="{{ $c->id }}">{{ $c->name }}</option>@endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" min="0" step="0.01" class="form-control @error('form.amount') is-invalid @enderror" wire:model="form.amount">@error('form.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-7"><label class="form-label">Paid from</label>
                <select class="form-select @error('form.account') is-invalid @enderror" wire:model="form.account">@foreach ($options as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                @error('form.account')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5"><label class="form-label">Date</label><input type="date" max="{{ now()->toDateString() }}" class="form-control @error('form.date') is-invalid @enderror" wire:model="form.date">@error('form.date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="form.note" placeholder="e.g. November rent"></div>
            <div class="col-12">
                <label class="form-label">Bill / receipt photo <span class="text-muted fs-12">(optional)</span></label>
                @if ($editingExpense?->attachment && ! $removeAttachment && ! $attachment)
                    <div class="d-flex align-items-center gap-2 mb-2 fs-13">
                        <i class="ri-attachment-2 text-muted"></i><a href="{{ upload_url($editingExpense->attachment) }}" target="_blank" data-no-loader>Current receipt</a>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" wire:click="$set('removeAttachment', true)">Remove</button>
                    </div>
                @elseif ($removeAttachment)
                    <div class="fs-13 text-muted mb-2">Receipt will be removed. <button type="button" class="btn btn-sm btn-link p-0" wire:click="$set('removeAttachment', false)">Undo</button></div>
                @endif
                <input type="file" class="form-control @error('attachment') is-invalid @enderror" wire:model="attachment" accept="image/*,application/pdf">
                @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div wire:loading wire:target="attachment" class="fs-12 text-muted mt-1">Uploading…</div>
                @if ($editingExpense?->attachment)<div class="fs-12 text-muted mt-1">Choosing a new file replaces the current receipt.</div>@endif
            </div>
            @if ($editingExpense)
                <div class="col-12">
                    <div class="alert alert-light border fs-12 mb-0">
                        <i class="ri-information-line me-1"></i>Saving corrects the ledger: if you change the amount, the difference goes back to (or comes off) the account; if you change <b>Paid from</b>, the money goes back to {{ \App\Services\FinanceService::accountLabel($editingExpense->account) }} and comes off the new account.
                        Recorded by {{ $editingExpense->user?->name ?? '—' }} on {{ $editingExpense->created_at->format('d M Y') }}.
                    </div>
                </div>
            @endif
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="ex-form" class="btn btn-primary" wire:loading.attr="disabled">{{ $editing ? 'Save changes' : 'Save expense' }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal :show="$modal === 'categories'" close="$set('modal', null)" title="Expense categories">
        <form wire:submit="addCategory" class="d-flex gap-2 mb-3">
            <input class="form-control @error('newCategory') is-invalid @enderror" wire:model="newCategory" placeholder="New category name">
            <button class="btn btn-primary text-nowrap">Add</button>
        </form>
        @error('newCategory')<div class="text-danger fs-13 mb-2">{{ $message }}</div>@enderror
        <div class="list-group">
            @foreach ($categories as $c)
                <div class="list-group-item d-flex align-items-center gap-2" wire:key="ec-{{ $c->id }}">
                    <span class="flex-grow-1 {{ $c->is_active ? '' : 'text-muted text-decoration-line-through' }}">{{ $c->name }}</span>
                    <span class="fs-12 text-muted">{{ $c->expenses_count }} used</span>
                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" @checked($c->is_active) wire:click="toggleCategory({{ $c->id }})" title="Active"></div>
                </div>
            @endforeach
        </div>
    </x-admin.modal>
</div>
