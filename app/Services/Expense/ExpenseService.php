<?php

namespace App\Services\Expense;

use App\Events\ExpenseChanged;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Tax;
use App\Models\User;
use App\Services\Numbering\NumberSeriesService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Expenses (Roadmap step 11): draft → submitted → approved / rejected → reimbursed (employee-paid only).
 * - Anyone with expense.submit records their own expenses; expense.manage sees and edits everyone's drafts.
 * - Approvers need expense.approve and, unless the business allows self-approval, can't approve their own.
 * - Reimbursing needs expense.reimburse. Approved and reimbursed expenses post to Accounting (ExpenseChanged).
 */
class ExpenseService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    public function ensureDefaults(int $businessId): void
    {
        $now = now();
        foreach (ExpenseCategory::DEFAULTS as $name => $code) {
            if (! DB::table('expense_categories')->where('business_id', $businessId)->where('name', $name)->whereNull('deleted_at')->exists()) {
                DB::table('expense_categories')->insert(['business_id' => $businessId, 'name' => $name, 'account_code' => $code, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function selfApprovalAllowed(int $businessId): bool
    {
        return (bool) DB::table('preferences')->where('business_id', $businessId)->where('type', 'expense')->where('slug', 'allow-self-approval')
            ->whereNull('deleted_at')->value('status');
    }

    public function setSelfApproval(int $businessId, bool $allowed): void
    {
        $q = DB::table('preferences')->where('business_id', $businessId)->where('type', 'expense')->where('slug', 'allow-self-approval')->whereNull('deleted_at');
        if ($q->exists()) {
            $q->update(['status' => $allowed, 'updated_at' => now()]);
        } else {
            DB::table('preferences')->insert(['business_id' => $businessId, 'type' => 'expense', 'slug' => 'allow-self-approval', 'preference' => 'Allow approving own expenses',
                'status' => $allowed, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function save(array $input, ?UploadedFile $receipt, ?Expense $expense, User $user, int $businessId): Expense
    {
        if ($expense && ! in_array($expense->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['expense' => 'Only drafts and rejected expenses can be changed.']);
        }
        if ($expense && $expense->user_id !== $user->id && ! $user->can('expense.manage')) {
            abort(403);
        }
        $data = validator($input, [
            'date' => ['required', 'date', 'before_or_equal:today'], 'expense_category_id' => ['required', 'integer'],
            'merchant' => ['nullable', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'], 'tax_id' => ['nullable', 'integer'],
            'paid_by' => ['required', 'in:business,employee'], 'payment_method' => ['nullable', 'in:cash,bank,card'], 'submit' => ['nullable', 'boolean'],
        ], ['expense_category_id.required' => 'Pick a category.'], ['expense_category_id' => 'category'])->validate();
        if ($receipt) {
            validator(['receipt' => $receipt], ['receipt' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf']])->validate();
        }
        if (! ExpenseCategory::query()->whereKey($data['expense_category_id'])->exists()) {
            throw ValidationException::withMessages(['expense_category_id' => 'Pick one of your categories.']);
        }
        $tax = ! empty($data['tax_id']) ? Tax::query()->find($data['tax_id']) : null;
        if (! empty($data['tax_id']) && ! $tax) {
            throw ValidationException::withMessages(['tax_id' => 'Pick a tax from your list.']);
        }
        $amount = round((float) $data['amount'], 2);
        // The amount paid includes the tax: tax = amount × rate ÷ (100 + rate).
        $taxAmount = $tax ? round($amount * (float) $tax->rate / (100 + (float) $tax->rate), 2) : 0.0;

        return DB::transaction(function () use ($expense, $data, $receipt, $user, $businessId, $tax, $amount, $taxAmount) {
            $expense ??= new Expense(['number' => $this->numbers->next($businessId, 'expense'), 'user_id' => $user->id]);
            $expense->fill([
                'date' => $data['date'], 'expense_category_id' => (int) $data['expense_category_id'], 'merchant' => $data['merchant'] ?? null,
                'description' => $data['description'] ?? null, 'amount' => $amount, 'tax_id' => $tax?->id, 'tax_amount' => $taxAmount,
                'paid_by' => $data['paid_by'], 'payment_method' => $data['paid_by'] === 'business' ? ($data['payment_method'] ?? 'cash') : 'cash',
                'status' => ! empty($data['submit']) ? 'submitted' : 'draft', 'rejection_reason' => null,
            ]);
            if ($receipt) {
                $expense->receipt_path = $receipt->store("expenses/{$businessId}", 'local');
            }
            $expense->save();

            return $expense;
        });
    }

    public function transition(Expense $expense, string $action, User $user, int $businessId, ?string $reason = null): Expense
    {
        return DB::transaction(function () use ($expense, $action, $user, $businessId, $reason) {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $fail = fn (string $m) => throw ValidationException::withMessages(['expense' => $m]);
            switch ($action) {
                case 'submit':
                    in_array($expense->status, ['draft', 'rejected'], true) || $fail('It was already submitted.');
                    ($expense->user_id === $user->id || $user->can('expense.manage')) || abort(403);
                    $expense->update(['status' => 'submitted', 'rejection_reason' => null]);
                    break;
                case 'approve':
                case 'reject':
                    $user->can('expense.approve') || abort(403);
                    $expense->status === 'submitted' || $fail('Only submitted expenses can be approved or rejected.');
                    if ($action === 'approve' && $expense->user_id === $user->id && ! $this->selfApprovalAllowed($businessId)) {
                        $fail('You can’t approve your own expense. Ask another approver, or allow self-approval in the expense policy.');
                    }
                    if ($action === 'reject' && trim((string) $reason) === '') {
                        $fail('Say why it is rejected, so it can be fixed.');
                    }
                    $expense->update($action === 'approve'
                        ? ['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]
                        : ['status' => 'rejected', 'rejection_reason' => mb_substr(trim((string) $reason), 0, 500), 'approved_by' => $user->id, 'approved_at' => now()]);
                    break;
                case 'reimburse':
                    $user->can('expense.reimburse') || abort(403);
                    ($expense->status === 'approved' && $expense->paid_by === 'employee') || $fail('Only approved expenses paid by an employee are reimbursed.');
                    $expense->update(['status' => 'reimbursed', 'reimbursed_by' => $user->id, 'reimbursed_at' => now()]);
                    break;
                case 'archive':
                    in_array($expense->status, ['draft', 'rejected'], true) || $fail('Only drafts and rejected expenses can be archived.');
                    ($expense->user_id === $user->id || $user->can('expense.manage')) || abort(403);
                    $expense->delete();
                    break;
                default:
                    abort(404);
            }
            event(new ExpenseChanged($expense->fresh() ?? $expense));

            return $expense;
        });
    }

    public function receiptUrl(Expense $e): ?string
    {
        return $e->receipt_path && Storage::disk('local')->exists($e->receipt_path) ? route('expenses.receipt', $e->id) : null;
    }
}
