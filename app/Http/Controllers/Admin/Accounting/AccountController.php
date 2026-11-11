<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Resource\ResourceController;
use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Chart of accounts (Accounting, Roadmap step 10). System accounts (used by automatic journals) can't be archived. */
class AccountController extends ResourceController
{
    protected function schema(): array
    {
        return [
            'title' => 'Chart of accounts', 'singular' => 'Account', 'icon' => 'journal-text', 'accent' => 'var(--chart-8)', 'help' => 'accounting',
            'route' => 'accounting.accounts', 'manage' => 'accounting.manage', 'show' => 'accounting.ledger',
            'description' => 'Every account your journals post to. Sales, purchases, payments and expenses post here automatically.',
            'fields' => [
                ['key' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'col' => 4, 'placeholder' => 'e.g. 6250'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 8],
                ['key' => 'sub_type', 'label' => 'Type', 'type' => 'select', 'required' => true,
                    'options' => collect(Account::SUB_TYPES)->map(fn ($v, $k) => ['value' => $k, 'label' => Account::TYPES[$v[0]].' · '.$v[1]])->values()->all()],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'Account', 'type' => 'title', 'sortable' => true],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'align' => 'end', 'sortable' => true],
            ],
            'statuses' => collect(Account::TYPES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label.($key === 'equity' ? '' : 's'), 'icon' => match ($key) {
                'asset' => 'safe', 'liability' => 'credit-card', 'equity' => 'pie-chart', 'income' => 'graph-up-arrow', default => 'receipt' }, 'accent' => match ($key) {
                'asset' => 'var(--chart-1)', 'liability' => 'var(--app-warning)', 'equity' => 'var(--chart-6)', 'income' => 'var(--app-success)', default => 'var(--app-danger)' }])->values()->all(),
            'default_dir' => 'asc',
        ];
    }

    protected function model(): string { return Account::class; }

    protected function searchable(): array { return ['accounts.code', 'accounts.name']; }

    protected function sortable(): array { return ['code' => 'accounts.code', 'name' => 'accounts.name', 'balance' => 'balance']; }

    protected function query(): Builder
    {
        $sum = DB::table('journal_lines')->whereColumn('journal_lines.account_id', 'accounts.id')->whereNull('journal_lines.deleted_at')
            ->selectRaw("coalesce(sum(case when accounts.type in ('asset','expense') then debit - credit else credit - debit end), 0)");

        return Account::query()->select('accounts.*')->selectSub($sum, 'balance');
    }

    protected function applyStatus(Builder $query, string $status): Builder
    {
        return $query->where('accounts.type', $status);
    }

    protected function counts(): array
    {
        return Account::query()->groupBy('type')->selectRaw('type, count(*) as n')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all();
    }

    protected function row(Model $m): array
    {
        return [
            'cells' => [
                'name' => "{$m->code} · {$m->name}", 'name_sub' => $m->description,
                'type' => ['text' => Account::SUB_TYPES[$m->sub_type][1] ?? $m->sub_type, 'accent' => $m->is_system ? 'var(--chart-8)' : 'var(--chart-1)'],
                'balance' => round((float) $m->balance, 2),
            ],
            'form' => ['code' => $m->code, 'name' => $m->name, 'sub_type' => $m->sub_type, 'description' => $m->description],
            'locked' => $m->is_system ? 'Automatic journals use this account.' : null,
        ];
    }

    protected function rules(?Model $model): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->where('business_id', $this->businessId())->whereNull('deleted_at')->ignore($model?->id)],
            'name' => ['required', 'string', 'max:150'],
            'sub_type' => ['required', Rule::in(array_keys(Account::SUB_TYPES))],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function fill(Model $model, array $data): void
    {
        // A system account keeps its type: automatic journals depend on it.
        if ($model->is_system) {
            $data['sub_type'] = $model->sub_type;
        }
        $model->fill($data + ['type' => Account::SUB_TYPES[$data['sub_type']][0]]);
        $model->type = Account::SUB_TYPES[$data['sub_type']][0];
    }

    protected function archiveBlocker(Model $model): ?string
    {
        if ($model->is_system) {
            return 'Automatic journals use this account: it can’t be archived.';
        }

        return DB::table('journal_lines')->where('account_id', $model->id)->whereNull('deleted_at')->exists() ? 'Journals use this account: it can’t be archived.' : null;
    }

    protected function defaultSort(): string { return 'code'; }

    protected function label(Model $model): string { return "{$model->code} {$model->name}"; }

    protected function extra(Request $request): array { return ['currency' => currency_symbol()]; }
}
