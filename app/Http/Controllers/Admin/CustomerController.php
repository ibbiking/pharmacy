<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Resource\ResourceController;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Customers (Core, Roadmap step 7): who B2B documents are for; balance = opening + invoices − payments − credit notes. */
class CustomerController extends ResourceController
{
    protected function schema(): array
    {
        return [
            'title' => 'Customers', 'singular' => 'Customer', 'icon' => 'person-vcard', 'accent' => 'var(--chart-4)', 'help' => 'b2b-sales',
            'route' => 'customers', 'manage' => 'core.customers.manage', 'show' => 'customers.show',
            'description' => 'The people and companies you sell to on account. Quotations, orders, invoices and payments are kept on their profile.',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 6],
                ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'col' => 6],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'col' => 6],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'col' => 6],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                ['key' => 'tax_number', 'label' => 'Tax number (NTN / STRN)', 'type' => 'text', 'col' => 6],
                ['key' => 'payment_terms_days', 'label' => 'Payment terms (days)', 'type' => 'number', 'col' => 6, 'hint' => '0 = due on receipt. Sets the due date of new invoices.'],
                ['key' => 'credit_limit', 'label' => 'Credit limit', 'type' => 'money', 'col' => 6, 'hint' => 'Invoices that would take the balance above it are refused.'],
                ['key' => 'opening_balance', 'label' => 'Opening balance (they owe you)', 'type' => 'money', 'col' => 6],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'Customer', 'type' => 'title', 'sortable' => true],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'terms', 'label' => 'Terms'],
                ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'align' => 'end', 'sortable' => true],
            ],
            'statuses' => [['key' => 'owing', 'label' => 'Owe you', 'icon' => 'hourglass-split', 'accent' => 'var(--app-warning)']],
        ];
    }

    protected function model(): string
    {
        return Customer::class;
    }

    protected function searchable(): array
    {
        return ['customers.name', 'customers.company', 'customers.phone', 'customers.email'];
    }

    protected function sortable(): array
    {
        return ['name' => 'customers.name', 'balance' => 'balance'];
    }

    protected function query(): Builder
    {
        return Customer::query()->select('customers.*')->selectSub(self::balanceSql(), 'balance');
    }

    /** Opening balance + open invoice amounts (documents table, step 8). */
    public static function balanceSql()
    {
        if (! Schema::hasTable('documents')) {
            return DB::query()->selectRaw('customers.opening_balance');
        }

        return DB::query()->selectRaw("customers.opening_balance + coalesce((select sum(case when d.type = 'sales_invoice' then d.balance when d.type = 'credit_note' then -d.balance else 0 end)
            from documents d where d.customer_id = customers.id and d.deleted_at is null and d.status not in ('draft', 'void')), 0)
            - coalesce((select sum(pa.unapplied) from payments pa where pa.customer_id = customers.id and pa.deleted_at is null), 0)");
    }

    protected function applyStatus(Builder $query, string $status): Builder
    {
        return $query->having('balance', '>', 0);
    }

    protected function counts(): array
    {
        return ['owing' => DB::query()->fromSub($this->query()->toBase(), 'c')->where('balance', '>', 0)->count()];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function row(Model $m): array
    {
        return [
            'cells' => [
                'name' => $m->name, 'name_sub' => trim(implode(' · ', array_filter([$m->company, $m->email]))) ?: null,
                'phone' => $m->phone, 'terms' => $m->payment_terms_days ? "Net {$m->payment_terms_days}" : 'On receipt',
                'balance' => round((float) $m->balance, 2),
            ],
            'form' => [
                'name' => $m->name, 'company' => $m->company, 'email' => $m->email, 'phone' => $m->phone, 'address' => $m->address, 'tax_number' => $m->tax_number,
                'payment_terms_days' => $m->payment_terms_days, 'credit_limit' => $m->credit_limit, 'opening_balance' => $m->opening_balance, 'notes' => $m->notes,
            ],
        ];
    }

    protected function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('customers', 'name')->where('business_id', $this->businessId())->whereNull('deleted_at')->ignore($model?->id)],
            'company' => ['nullable', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'], 'tax_number' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_balance' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'], 'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function fill(Model $model, array $data): void
    {
        $data['name'] = trim($data['name']);
        $data['payment_terms_days'] = (int) ($data['payment_terms_days'] ?? 0);
        $data['opening_balance'] = (float) ($data['opening_balance'] ?? 0);
        $model->fill($data);
    }

    protected function archiveBlocker(Model $model): ?string
    {
        $balance = (float) (Customer::query()->select('customers.id')->selectSub(self::balanceSql(), 'b')->whereKey($model->id)->value('b') ?? 0);

        return abs($balance) > 0.009 ? 'This customer still has a balance. Settle it first.' : null;
    }

    protected function extra(Request $request): array
    {
        return ['currency' => currency_symbol()];
    }

    /** Profile: details, balance, documents and payments (B2B Sales), opened in the side drawer. */
    public function show(Request $request, int $id)
    {
        $customer = Customer::query()->select('customers.*')->selectSub(self::balanceSql(), 'balance')->findOrFail($id);
        $hasDocs = Schema::hasTable('documents') && app(\App\Services\Modules\ModuleManager::class)->isActive($this->businessId(), 'b2b_sales');

        return Inertia::render('Customers/Show', [
            'customer' => $this->row($customer)['form'] + ['id' => $customer->id, 'balance' => round((float) $customer->balance, 2)],
            'documents' => $hasDocs ? \App\Models\Documents\Document::query()->where('customer_id', $customer->id)->latest('date')->latest('id')
                ->paginate(10, ['*'], 'docs')->withQueryString()
                ->through(fn ($d) => ['id' => $d->id, 'number' => $d->number, 'type' => $d->type, 'label' => $d->typeLabel(), 'date' => $d->date?->format('d M Y'),
                    'status' => $d->status, 'total' => (float) $d->total, 'balance' => (float) $d->balance]) : null,
            'canStatement' => $hasDocs,
            'currency' => currency_symbol(),
        ]);
    }
}
