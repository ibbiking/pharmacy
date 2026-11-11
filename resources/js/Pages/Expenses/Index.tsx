import { Head, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { statusPill } from '@/Components/Documents/status';
import { formatMoney } from '@/lib/format';

interface Row { id: number; number: string; date: string; merchant: string | null; description: string | null; category: string | null; by: string | null; amount: number; status: string; paid_by: string }
interface Props { rows: Paginated<Row>; filters: TableFilters & { status?: string | null }; stats: Record<string, number>; can: { approve: boolean; reimburse: boolean; manage: boolean }; selfApproval: boolean; currency: string }

export default function ExpensesIndex({ rows, filters, stats, can, selfApproval, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const filter = (status: string | null) => router.get(route('expenses.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, replace: true });
    const columns: Column<Row>[] = [
        { key: 'number', label: 'Expense', render: (r) => <DrawerLink href={route('expenses.show', r.id)} className="fw-semibold" aria-label={`Expense ${r.number}`}>{r.number}</DrawerLink> },
        { key: 'merchant', label: 'Merchant / note', render: (r) => <div><span className="fw-semibold">{r.merchant ?? '—'}</span><small className="d-block text-muted text-truncate" style={{ maxWidth: 300 }}>{[r.category, r.description].filter(Boolean).join(' · ')}</small></div> },
        { key: 'by', label: 'By', render: (r) => <span>{r.by}<small className="d-block text-muted">{r.paid_by === 'employee' ? 'Paid by employee' : 'Paid by business'}</small></span> },
        { key: 'date', label: 'Date', render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        { key: 'status', label: 'Status', render: (r) => statusPill(r.status) },
        { key: 'amount', label: 'Amount', align: 'end', render: (r) => <span className="tabular fw-semibold">{money(r.amount)}</span> },
    ];
    const chip = (key: string, label: string, icon: string, accent: string) => ({ key, icon, label, value: stats[key] ?? 0, accent, onClick: () => filter(key), selected: filters.status === key });
    return (
        <>
            <Head title="Expenses" />
            <PageHeader icon="wallet2" title="Expenses" help="expense" description="Record what was spent, attach the receipt and send it for approval. Approved expenses post to the books."
                actions={<>
                    {can.manage && <DrawerLink href={route('expense-categories.index')} className="btn btn-light" title="Expense categories"><i className="bi bi-tags me-1" />Categories</DrawerLink>}
                    <DrawerLink href={route('expenses.create')} className="btn btn-primary" title="New expense"><i className="bi bi-plus-lg me-1" />New expense</DrawerLink>
                </>} />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: stats.all ?? 0, accent: 'var(--chart-5)', onClick: () => filter(null), selected: !filters.status },
                chip('mine', 'Mine', 'person', 'var(--chart-1)'),
                chip('submitted', 'Waiting for approval', 'hourglass-split', 'var(--app-warning)'),
                chip('approved', 'Approved', 'check2-circle', 'var(--app-success)'),
                chip('to_reimburse', 'To reimburse', 'cash-coin', 'var(--chart-6)'),
                chip('rejected', 'Rejected', 'x-circle', 'var(--app-danger)'),
                { key: 'spend', icon: 'graph-up', label: 'Spent this month', value: money(stats.month_spend ?? 0), accent: 'var(--chart-3)' },
            ]} />
            {can.manage && (
                <div className="app-card d-flex align-items-center gap-3 p-3 mb-3">
                    <div className="form-check form-switch mb-0">
                        <input id="ex-self" className="form-check-input" type="checkbox" checked={selfApproval} onChange={(e) => router.post(route('expenses.policy'), { allow_self_approval: e.target.checked }, { preserveScroll: true })} />
                        <label className="form-check-label" htmlFor="ex-self">Approvers may approve their own expenses</label>
                    </div>
                    <small className="text-muted">Off = someone else must approve them (recommended).</small>
                </div>
            )}
            <SectionCard icon="table" title="Expenses" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search number, merchant or note…" empty={<EmptyState icon="wallet2" title="No expenses here" />} />
            </SectionCard>
        </>
    );
}
