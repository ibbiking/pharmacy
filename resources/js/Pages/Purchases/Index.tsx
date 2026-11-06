import { Head, Link, router, usePage } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { formatMoney, formatNumber } from '@/lib/format';
import { expiryAccent } from '@/Pages/Products/Index';

type Status = 'month' | 'expiring' | 'expired' | 'archived' | null;
export interface PurchaseRow {
    id: number; date: string | null; product: string; unit: string | null; base_unit: string | null; quantity: number; base_quantity: number;
    remaining: number; sold: number; supplier: string | null; invoice_no: string | null; batch: string | null; expiry: string | null;
    expiry_days: number | null; total: number; archived: boolean;
}
interface Props {
    rows: Paginated<PurchaseRow>;
    filters: TableFilters & { status?: Status };
    stats: { total: number; month: number; month_spend: number; expiring: number; expired: number; archived: number };
    currency: string;
    canManage: boolean;
}

/** Remaining stock of a batch as a bar: green while plenty is left. */
export function RemainingBar({ row }: { row: Pick<PurchaseRow, 'remaining' | 'base_quantity' | 'base_unit' | 'expiry_days' | 'archived'> }) {
    if (row.archived) return <span className="text-muted small">Archived</span>;
    const pct = row.base_quantity > 0 ? Math.min(100, (row.remaining / row.base_quantity) * 100) : 0;
    return (
        <div className="d-flex align-items-center gap-2 justify-content-end">
            <div className="lot-bar" style={{ ['--accent' as string]: row.remaining > 0 ? expiryAccent(row.expiry_days) : 'var(--chart-8)', width: 80 }}><span style={{ width: `${pct}%` }} /></div>
            <span className="tabular small text-nowrap">{row.remaining > 0 ? `${formatNumber(row.remaining, 0)} ${row.base_unit ?? ''}` : 'Sold out'}</span>
        </div>
    );
}

export default function PurchasesIndex({ rows, filters, stats, currency, canManage }: Props) {
    const { wording } = usePage().props;
    const filter = (status: Status) => router.get(route('purchases.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<PurchaseRow>[] = [
        { key: 'created_at', label: 'Date', sortable: true, render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        {
            key: 'product', label: wording.product ?? 'Product', render: (r) => (
                <Link href={route('purchases.show', r.id)} className="text-reset text-decoration-none d-block min-w-0">
                    <span className="fw-semibold d-block text-break">{r.product}{r.archived && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Archived</span>}</span>
                    <small className="text-muted">{formatNumber(r.quantity, 2)} {r.unit}{r.batch && ` · batch ${r.batch}`}</small>
                </Link>
            ),
        },
        { key: 'supplier', label: wording.supplier ?? 'Supplier', render: (r) => <div className="small"><span>{r.supplier ?? '—'}</span>{r.invoice_no && <div className="text-muted">Bill {r.invoice_no}</div>}</div> },
        {
            key: 'expiry_date', label: 'Expiry', sortable: true, render: (r) => r.expiry
                ? <span className="expiry-chip" style={{ ['--accent' as string]: expiryAccent(r.expiry_days) }}>{r.expiry_days !== null && r.expiry_days <= 0 ? 'Expired ' : ''}{r.expiry}</span>
                : <span className="text-muted">—</span>,
        },
        { key: 'remaining', label: 'Left', align: 'end', render: (r) => <RemainingBar row={r} /> },
        { key: 'total', label: 'Paid', sortable: true, align: 'end', className: 'tabular fw-semibold', render: (r) => formatMoney(r.total, currency) },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                <div className="d-inline-flex gap-1">
                    <Link href={route('purchases.show', r.id)} className="btn btn-icon btn-soft" aria-label={`Open purchase of ${r.product}`} title="Open"><i className="bi bi-eye" /></Link>
                    {canManage && !r.archived && <a href={route('purchases.edit', r.id)} className="btn btn-icon btn-soft" aria-label={`Edit purchase of ${r.product}`} title="Edit"><i className="bi bi-pencil" /></a>}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Purchases" />
            <PageHeader icon="box-seam" title="Purchases" description="Every batch you bought: what’s left of it, when it expires and what you paid."
                actions={canManage && <a href={route('purchases.create')} className="btn btn-primary"><i className="bi bi-plus-lg me-1" />Add purchase</a>} />

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All purchases', value: stats.total, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'month', icon: 'calendar-month', label: `This month · ${formatMoney(stats.month_spend, currency)}`, value: stats.month, accent: 'var(--chart-3)', onClick: () => filter('month'), selected: filters.status === 'month' },
                { key: 'expiring', icon: 'hourglass-split', label: 'In stock, expiring ≤ 90 days', value: stats.expiring, accent: 'var(--app-warning)', onClick: () => filter('expiring'), selected: filters.status === 'expiring' },
                { key: 'expired', icon: 'calendar-x', label: 'Expired, still on the shelf', value: stats.expired, accent: 'var(--app-danger)', onClick: () => filter('expired'), selected: filters.status === 'expired' },
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived, accent: 'var(--chart-8)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
            ]} />

            {filters.status === 'expired' && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-danger)' }}><i className="bi bi-calendar-x" /><div><strong>These batches can’t be sold</strong><p>The POS skips expired stock. Return it to the supplier or write it off.</p></div></div>
            )}

            <SectionCard icon="table" title={filters.status === 'archived' ? 'Archived purchases' : 'Purchases'} subtitle="Click a purchase for its costs, taxes and the invoices that sold from it" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search product, batch, bill no or supplier…"
                    empty={filters.q || filters.status ? undefined : (
                        <EmptyState icon="box-seam" title="No purchases yet" hint="Record what you bought to put it in stock."
                            action={canManage && <a href={route('purchases.create')} className="btn btn-primary btn-sm">Add purchase</a>} />
                    )} />
            </SectionCard>
        </>
    );
}
