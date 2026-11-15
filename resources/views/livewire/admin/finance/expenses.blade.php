<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('admin.layouts.app')] #[Title('Expenses')] class extends Component
{
    use WithFileUploads, WithPagination;

    /** YYYY-MM */
    #[Url]
    public string $month = '';

    #[Url(except: '')]
    public string $category = '';

    public ?string $modal = null; // expense | categories

    public array $form = [];

    public $attachment = null;

    public string $newCategory = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['month', 'category'])) {
            $this->resetPage();
        }
    }

    public function shiftMonth(int $by): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonthsNoOverflow($by)->format('Y-m');
        $this->resetPage();
    }

    public function create(): void
    {
        $this->form = [
            'expense_category_id' => $this->category ?: ExpenseCategory::where('is_active', true)->orderBy('name')->value('id'),
            'account' => array_key_first(FinanceService::accountOptions()),
            'amount' => '',
            'date' => now()->toDateString(),
            'note' => '',
        ];
        $this->attachment = null;
        $this->resetErrorBag();
        $this->modal = 'expense';
    }

    public function save(ExpenseService $expenses): void
    {
        $this->validate([
            'form.expense_category_id' => 'required|exists:expense_categories,id',
            'form.account' => 'required',
            'form.amount' => 'required|numeric|min:1',
            'form.date' => 'required|date|before_or_equal:today',
            'form.note' => 'nullable|string|max:255',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
        ], [], ['form.expense_category_id' => 'category', 'form.amount' => 'amount', 'form.date' => 'date']);

        $account = FinanceService::accountFromKey($this->form['account']);
        try {
            FinanceService::ensureFunds($account, (float) $this->form['amount']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('form.amount', collect($e->errors())->flatten()->first());

            return;
        }

        $path = $this->attachment?->store('expenses/'.now()->format('Y/m'), 'uploads');
        $expenses->create($this->form, $account, $path);

        $this->modal = null;
        $this->attachment = null;
        $this->dispatch('toast', type: 'success', message: 'Expense of '.money($this->form['amount']).' recorded.');
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
        $start = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $inMonth = Expense::whereBetween('date', [$start->toDateString(), $end->toDateString()]);
        $prev = (float) Expense::whereBetween('date', [$start->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $start->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()])->sum('amount');

        return [
            'expenses' => $inMonth->clone()->with('category', 'account', 'user:id,name')
                ->when($this->category, fn ($q) => $q->where('expense_category_id', $this->category))
                ->latest('date')->latest('id')->paginate(25),
            'byCategory' => $inMonth->clone()->selectRaw('expense_category_id, SUM(amount) total, COUNT(*) n')->groupBy('expense_category_id')->with('category')->get()->sortByDesc('total')->values(),
            'monthTotal' => (float) $inMonth->clone()->sum('amount'),
            'prevTotal' => $prev,
            'start' => $start,
            'categories' => ExpenseCategory::withCount('expenses')->orderBy('name')->get(),
            'options' => $this->modal === 'expense' ? FinanceService::accountOptions() : [],
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Expenses" :crumbs="['Bank & cash' => route('admin.finance.accounts')]" subtitle="Rent, salaries, bills and other running costs — paid from cash or a bank account.">
        <x-slot:actions>
            <button class="btn btn-sm btn-light" wire:click="$set('modal', 'categories')"><i class="ri-price-tag-3-line me-1"></i>Categories</button>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>Add expense</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-4 order-xl-2">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <button class="btn btn-sm btn-light icon-btn-sm" wire:click="shiftMonth(-1)" aria-label="Previous month"><i class="ri-arrow-left-s-line"></i></button>
                        <input type="month" class="form-control form-control-sm text-center fw-semibold" wire:model.live="month">
                        <button class="btn btn-sm btn-light icon-btn-sm" wire:click="shiftMonth(1)" aria-label="Next month"><i class="ri-arrow-right-s-line"></i></button>
                    </div>
                    <div class="fs-12 text-muted">Spent in {{ $start->format('F Y') }}</div>
                    <h3 class="mb-1">{{ money($monthTotal) }}</h3>
                    @if ($prevTotal > 0)
                        @php($change = ($monthTotal - $prevTotal) / $prevTotal * 100)
                        <div class="fs-12 {{ $change > 0 ? 'text-danger' : 'text-success' }}"><i class="{{ $change > 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i> {{ number_format(abs($change), 0) }}% vs last month ({{ money($prevTotal) }})</div>
                    @endif
                    <hr>
                    @forelse ($byCategory as $row)
                        @php($pct = $monthTotal > 0 ? $row->total / $monthTotal * 100 : 0)
                        <button type="button" class="d-block w-100 text-start border-0 bg-transparent p-0 mb-3" wire:click="$set('category', '{{ $category == $row->expense_category_id ? '' : $row->expense_category_id }}')">
                            <div class="d-flex justify-content-between fs-13 mb-1"><span class="{{ $category == $row->expense_category_id ? 'fw-bold text-primary' : '' }}">{{ $row->category?->name }} <span class="text-muted">({{ $row->n }})</span></span><span class="fw-semibold">{{ money($row->total) }}</span></div>
                            <div class="progress" style="height: 6px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                        </button>
                    @empty
                        <p class="text-muted fs-13 mb-0">No expenses this month.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-8 order-xl-1">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <h6 class="card-title mb-0 flex-grow-1">{{ $start->format('F Y') }}</h6>
                    <select class="form-select form-select-sm" style="width: 200px" wire:model.live="category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                </div>
                <div class="table-responsive">
                    <table class="table table-mw mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Note</th><th>Paid from</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($expenses as $e)
                                <tr wire:key="ex-{{ $e->id }}">
                                    <td class="fs-13 text-nowrap">{{ $e->date->format('d M') }}</td>
                                    <td><span class="badge bg-light text-body">{{ $e->category?->name }}</span></td>
                                    <td class="fs-13">{{ $e->note }} <span class="text-muted fs-12">{{ $e->user?->name }}</span>
                                        @if ($e->attachment)<a href="{{ upload_url($e->attachment) }}" target="_blank" class="ms-1" title="View receipt"><i class="ri-attachment-2"></i></a>@endif
                                    </td>
                                    <td class="fs-13">{{ $e->account instanceof \App\Models\CashDrawer ? 'Cash' : $e->account?->bank_name }}</td>
                                    <td class="text-end fw-semibold">{{ money($e->amount) }}</td>
                                    <td class="text-end"><button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete this {{ money($e->amount) }} expense? The money goes back on its account.', () => $wire.delete({{ $e->id }}))" title="Delete"><i class="ri-delete-bin-line"></i></button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-admin.empty icon="ri-wallet-3-line" title="No expenses recorded" text="Add rent, salaries, bills and other running costs here." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif
            </div>
        </div>
    </div>

    <x-admin.modal :show="$modal === 'expense'" close="$set('modal', null)" title="Add expense">
        <form wire:submit="save" id="ex-form" class="row g-3">
            <div class="col-md-6"><label class="form-label">Category</label><select class="form-select @error('form.expense_category_id') is-invalid @enderror" wire:model="form.expense_category_id">@foreach ($categories->where('is_active', true) as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Amount (Rs.)</label><input type="number" min="0" step="0.01" class="form-control @error('form.amount') is-invalid @enderror" wire:model="form.amount">@error('form.amount')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-7"><label class="form-label">Paid from</label><select class="form-select" wire:model="form.account">@foreach ($options as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label">Date</label><input type="date" class="form-control @error('form.date') is-invalid @enderror" wire:model="form.date">@error('form.date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" wire:model="form.note" placeholder="e.g. November rent"></div>
            <div class="col-12">
                <label class="form-label">Bill / receipt photo <span class="text-muted fs-12">(optional)</span></label>
                <input type="file" class="form-control @error('attachment') is-invalid @enderror" wire:model="attachment" accept="image/*,application/pdf">
                @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div wire:loading wire:target="attachment" class="fs-12 text-muted mt-1">Uploading…</div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('modal', null)">Cancel</button>
            <button type="submit" form="ex-form" class="btn btn-primary" wire:loading.attr="disabled">Save expense</button>
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
