<?php

namespace App\Http\Controllers\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Services\Expense\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Expenses (Roadmap step 11): list with approval filters, side-drawer form, approve / reject / reimburse. */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses)
    {
    }

    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    /** Without expense.manage / approve / reimburse a member only sees their own expenses. */
    private function visible(Request $request)
    {
        $u = $request->user();
        $all = $u->can('expense.manage') || $u->can('expense.approve') || $u->can('expense.reimburse');

        return Expense::query()->when(! $all, fn ($q) => $q->where('user_id', $u->id));
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $statuses = ['mine', 'draft', 'submitted', 'approved', 'rejected', 'reimbursed', 'to_reimburse'];
        $status = in_array($request->input('status'), $statuses, true) ? $request->input('status') : null;
        $apply = fn ($query, ?string $s) => match ($s) {
            null => $query,
            'mine' => $query->where('user_id', $request->user()->id),
            'to_reimburse' => $query->where('status', 'approved')->where('paid_by', 'employee'),
            default => $query->where('status', $s),
        };
        $rows = $apply($this->visible($request), $status)->with(['category:id,name', 'user:id,name'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('merchant', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")))
            ->orderByDesc('date')->orderByDesc('id')->paginate($perPage)->withQueryString()
            ->through(fn (Expense $e) => ['id' => $e->id, 'number' => $e->number, 'date' => $e->date?->format('d M Y'), 'merchant' => $e->merchant, 'description' => $e->description,
                'category' => $e->category?->name, 'by' => $e->user?->name, 'amount' => $e->amount, 'status' => $e->status, 'paid_by' => $e->paid_by]);

        return Inertia::render('Expenses/Index', [
            'rows' => $rows,
            'filters' => ['q' => $q, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => collect($statuses)->mapWithKeys(fn ($s) => [$s => $apply($this->visible($request), $s)->count()])->all() + [
                'all' => $this->visible($request)->count(),
                'month_spend' => round((float) $this->visible($request)->whereIn('status', ['approved', 'reimbursed'])->where('date', '>=', now()->startOfMonth())->sum('amount'), 2),
            ],
            'can' => ['approve' => $request->user()->can('expense.approve'), 'reimburse' => $request->user()->can('expense.reimburse'), 'manage' => $request->user()->can('expense.manage')],
            'selfApproval' => $this->expenses->selfApprovalAllowed($this->businessId()),
            'currency' => currency_symbol(),
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(Request $request, int $id)
    {
        $e = $this->visible($request)->findOrFail($id);
        abort_unless(in_array($e->status, ['draft', 'rejected'], true), 404);

        return $this->form($e);
    }

    private function form(?Expense $e)
    {
        $e?->load('category', 'tax');

        return Inertia::render('Expenses/Form', [
            'expense' => $e ? ['id' => $e->id, 'number' => $e->number, 'date' => $e->date?->toDateString(), 'merchant' => $e->merchant, 'description' => $e->description,
                'amount' => (string) $e->amount, 'paid_by' => $e->paid_by, 'payment_method' => $e->payment_method,
                'category' => $e->category ? ['value' => $e->category->id, 'label' => $e->category->name] : null,
                'tax' => $e->tax ? ['value' => $e->tax->id, 'label' => $e->tax->name, 'meta' => ['rate' => (float) $e->tax->rate]] : null,
                'receipt' => $this->expenses->receiptUrl($e), 'rejection_reason' => $e->rejection_reason] : null,
            'today' => today()->toDateString(),
            'currency' => currency_symbol(),
        ]);
    }

    public function store(Request $request)
    {
        $e = $this->expenses->save($request->except('receipt'), $request->file('receipt'), null, $request->user(), $this->businessId());

        return redirect()->route('expenses.show', $e->id)->with('success', $e->status === 'submitted' ? "Expense {$e->number} submitted for approval." : "Expense {$e->number} saved as a draft.");
    }

    public function update(Request $request, int $id)
    {
        $e = $this->visible($request)->findOrFail($id);
        $e = $this->expenses->save($request->except('receipt'), $request->file('receipt'), $e, $request->user(), $this->businessId());

        return redirect()->route('expenses.show', $e->id)->with('success', "Expense {$e->number} saved.");
    }

    public function show(Request $request, int $id)
    {
        $e = $this->visible($request)->with(['category', 'user:id,name', 'approver:id,name', 'tax'])->findOrFail($id);
        $u = $request->user();
        $mine = $e->user_id === $u->id;

        return Inertia::render('Expenses/Show', [
            'expense' => ['id' => $e->id, 'number' => $e->number, 'date' => $e->date?->format('d M Y'), 'merchant' => $e->merchant, 'description' => $e->description,
                'category' => $e->category?->name, 'amount' => $e->amount, 'tax' => $e->tax?->name, 'tax_amount' => $e->tax_amount, 'paid_by' => $e->paid_by,
                'payment_method' => $e->payment_method, 'status' => $e->status, 'by' => $e->user?->name, 'approver' => $e->approver?->name,
                'approved_at' => $e->approved_at?->format('d M Y H:i'), 'reimbursed_at' => $e->reimbursed_at?->format('d M Y H:i'), 'rejection_reason' => $e->rejection_reason,
                'receipt' => $this->expenses->receiptUrl($e)],
            'can' => [
                'edit' => in_array($e->status, ['draft', 'rejected'], true) && ($mine || $u->can('expense.manage')),
                'submit' => in_array($e->status, ['draft', 'rejected'], true) && ($mine || $u->can('expense.manage')),
                'approve' => $e->status === 'submitted' && $u->can('expense.approve') && (! $mine || $this->expenses->selfApprovalAllowed($this->businessId())),
                'reject' => $e->status === 'submitted' && $u->can('expense.approve'),
                'reimburse' => $e->status === 'approved' && $e->paid_by === 'employee' && $u->can('expense.reimburse'),
                'archive' => in_array($e->status, ['draft', 'rejected'], true) && ($mine || $u->can('expense.manage')),
            ],
            'currency' => currency_symbol(),
        ]);
    }

    public function action(Request $request, int $id, string $action)
    {
        $e = $this->visible($request)->findOrFail($id);
        $this->expenses->transition($e, $action, $request->user(), $this->businessId(), $request->input('reason'));
        $done = ['submit' => 'Submitted for approval.', 'approve' => 'Expense approved.', 'reject' => 'Expense rejected.', 'reimburse' => 'Marked as reimbursed.', 'archive' => 'Expense archived.'];

        return $action === 'archive'
            ? redirect()->route('expenses.index')->with('success', $done['archive'])
            : redirect()->route('expenses.show', $e->id)->with('success', $done[$action] ?? 'Done.');
    }

    public function receipt(Request $request, int $id)
    {
        $e = $this->visible($request)->findOrFail($id);
        abort_unless($e->receipt_path && Storage::disk('local')->exists($e->receipt_path), 404);

        return Storage::disk('local')->response($e->receipt_path);
    }

    public function policy(Request $request)
    {
        abort_unless($request->user()->can('expense.manage'), 403);
        $this->expenses->setSelfApproval($this->businessId(), $request->boolean('allow_self_approval'));

        return back()->with('success', $request->boolean('allow_self_approval') ? 'Approvers may now approve their own expenses.' : 'Approvers can no longer approve their own expenses.');
    }
}
