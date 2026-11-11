import { Head, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Row { id: number; number: string | null; date: string; memo: string | null; source: string | null; amount: number; manual: boolean }
interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { status?: string | null; from: string; to: string };
    stats: Record<string, number>;
    canManage: boolean;
    purchasesOnCredit: boolean;
    currency: string;
}

export const SOURCES: Record<string, [string, string, string]> = {
    manual: ['Manual', 'pencil-square', 'var(--chart-8)'], pos_invoice: ['POS sales', 'cart-check', 'var(--chart-3)'], pos_return: ['POS returns', 'arrow-return-left', 'var(--app-warning)'],
    purchase: ['Purchases', 'box-arrow-in-down', 'var(--chart-2)'], document: ['B2B documents', 'file-earmark-text', 'var(--chart-4)'], payment: ['Payments', 'cash-coin', 'var(--app-success)'],
    expense: ['Expenses', 'wallet2', 'var(--chart-5)'], stock_adjustment: ['Stock adjustments', 'sliders2-vertical', 'var(--chart-6)'],
};

/** Every journal in the books, automatic and manual. */
export default function Journals({ rows, filters, stats, canManage, purchasesOnCredit, currency }: Props) {
    const go = (patch: Record<string, unknown>) => router.get(route('accounting.journals.index'), { ...filters, ...patch, page: 1 }, { preserveState: true, replace: true });
    const columns: Column<Row>[] = [
        { key: 'number', label: 'Journal', render: (r) => <DrawerLink href={route('accounting.journals.show', r.id)} className="fw-semibold" aria-label={`Journal ${r.number ?? r.id}`}>{r.number ?? `#${r.id}`}</DrawerLink> },
        { key: 'date', label: 'Date', render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        { key: 'memo', label: 'Memo', render: (r) => <span className="text-truncate d-inline-block" style={{ maxWidth: 420 }}>{r.memo}</span> },
        { key: 'source', label: 'From', render: (r) => { const s = SOURCES[r.source ?? 'manual']; return <span className="pill" style={{ ['--accent' as string]: s?.[2] }}>{s?.[0] ?? r.source}</span>; } },
        { key: 'amount', label: 'Amount', align: 'end', render: (r) => <span className="tabular">{formatMoney(r.amount, currency)}</span> },
    ];
    return (
        <>
            <Head title="Journals" />
            <PageHeader icon="journal-text" title="Journals" help="accounting" description="Sales, purchases, payments and expenses post here by themselves. Add a manual journal for anything else (opening balances, depreciation, corrections)."
                actions={canManage && <DrawerLink href={route('accounting.journals.create')} className="btn btn-primary" title="Manual journal"><i className="bi bi-plus-lg me-1" />Manual journal</DrawerLink>} />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: Object.values(stats ?? {}).reduce((a, b) => a + Number(b), 0), accent: 'var(--chart-8)', onClick: () => go({ status: null }), selected: !filters.status },
                ...Object.entries(SOURCES).filter(([k]) => (stats ?? {})[k]).map(([k, [label, icon, accent]]) => ({ key: k, icon, label, value: stats[k] ?? 0, accent, onClick: () => go({ status: k }), selected: filters.status === k })),
            ]} />
            <div className="app-card d-flex flex-wrap align-items-center gap-3 p-3 mb-3">
                <label className="small fw-semibold mb-0" htmlFor="j-from">From</label><input id="j-from" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.from} onChange={(e) => go({ from: e.target.value })} />
                <label className="small fw-semibold mb-0" htmlFor="j-to">To</label><input id="j-to" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.to} onChange={(e) => go({ to: e.target.value })} />
                <span className="flex-grow-1" />
                {canManage && (
                    <div className="form-check form-switch mb-0">
                        <input id="j-credit" className="form-check-input" type="checkbox" checked={purchasesOnCredit} onChange={(e) => router.post(route('accounting.settings'), { purchases_on_credit: e.target.checked }, { preserveScroll: true })} />
                        <label className="form-check-label small" htmlFor="j-credit">Plain purchases are on credit (owed to the supplier)</label>
                    </div>
                )}
                {canManage && <button type="button" className="btn btn-sm btn-light" onClick={() => router.post(route('accounting.backfill'), {}, { preserveScroll: true })} title="Posts every past sale, purchase and payment again (safe to repeat)"><i className="bi bi-arrow-repeat me-1" />Re-post history</button>}
            </div>
            <SectionCard icon="table" title="Journals" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters', 'stats']} searchPlaceholder="Search number or memo…" empty={<EmptyState icon="journal-text" title="No journals in this period" />} />
            </SectionCard>
        </>
    );
}
