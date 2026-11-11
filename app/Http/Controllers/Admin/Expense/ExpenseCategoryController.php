<?php

namespace App\Http\Controllers\Admin\Expense;

use App\Http\Controllers\Admin\Resource\ResourceController;
use App\Models\Expense\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Expense categories (Roadmap step 11); each posts to an expense account of the chart (by code). */
class ExpenseCategoryController extends ResourceController
{
    protected function schema(): array
    {
        $accounts = DB::table('accounts')->where('business_id', $this->businessId())->whereNull('deleted_at')->whereIn('type', ['expense'])
            ->orderBy('code')->limit(200)->get(['code', 'name']);

        return [
            'title' => 'Expense categories', 'singular' => 'Category', 'icon' => 'tags', 'accent' => 'var(--chart-5)', 'help' => 'expense',
            'route' => 'expense-categories', 'manage' => 'expense.manage',
            'description' => 'How expenses are grouped, and which account each one posts to in the books.',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                $accounts->isNotEmpty()
                    ? ['key' => 'account_code', 'label' => 'Posts to account', 'type' => 'select', 'options' => array_merge([['value' => '', 'label' => 'Miscellaneous Expenses']], $accounts->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} · {$a->name}"])->all())]
                    : ['key' => 'account_code', 'label' => 'Account code', 'type' => 'text', 'hint' => 'Used when Accounting is switched on.'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'Category', 'type' => 'title', 'sortable' => true],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'count', 'label' => 'Expenses', 'type' => 'number', 'align' => 'end', 'sortable' => true],
                ['key' => 'total', 'label' => 'Spent (approved)', 'type' => 'money', 'align' => 'end', 'sortable' => true],
            ],
            'statuses' => [['key' => 'used', 'label' => 'In use', 'icon' => 'check2-circle', 'accent' => 'var(--app-success)']],
        ];
    }

    protected function model(): string { return ExpenseCategory::class; }

    protected function searchable(): array { return ['expense_categories.name']; }

    protected function sortable(): array { return ['name' => 'expense_categories.name', 'count' => 'count', 'total' => 'total']; }

    protected function query(): Builder
    {
        $e = fn () => DB::table('expenses')->whereColumn('expenses.expense_category_id', 'expense_categories.id')->whereNull('expenses.deleted_at');

        return ExpenseCategory::query()->select('expense_categories.*')->selectSub($e()->selectRaw('count(*)'), 'count')
            ->selectSub($e()->whereIn('status', ['approved', 'reimbursed'])->selectRaw('coalesce(sum(amount), 0)'), 'total');
    }

    protected function applyStatus(Builder $query, string $status): Builder { return $query->having('count', '>', 0); }

    protected function counts(): array
    {
        return ['used' => ExpenseCategory::query()->whereExists(fn ($q) => $q->from('expenses')->whereColumn('expenses.expense_category_id', 'expense_categories.id')->whereNull('expenses.deleted_at'))->count()];
    }

    protected function row(Model $m): array
    {
        $name = $m->account_code ? DB::table('accounts')->where('business_id', $m->business_id)->where('code', $m->account_code)->whereNull('deleted_at')->value('name') : null;

        return [
            'cells' => ['name' => $m->name, 'name_sub' => $m->description, 'account' => $m->account_code ? trim("{$m->account_code} {$name}") : 'Miscellaneous', 'count' => (int) $m->count, 'total' => round((float) $m->total, 2)],
            'form' => ['name' => $m->name, 'account_code' => $m->account_code ?? '', 'description' => $m->description],
            'locked' => (int) $m->count > 0 ? 'Expenses use this category.' : null,
        ];
    }

    protected function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->where('business_id', $this->businessId())->whereNull('deleted_at')->ignore($model?->id)],
            'account_code' => ['nullable', 'string', 'max:20'], 'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function archiveBlocker(Model $model): ?string
    {
        return DB::table('expenses')->where('expense_category_id', $model->id)->whereNull('deleted_at')->exists() ? 'Expenses use this category.' : null;
    }

    protected function extra(Request $request): array { return ['currency' => currency_symbol()]; }
}
