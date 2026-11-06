import { Head, Link, router, usePage } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { formatNumber } from '@/lib/format';

type Status = 'drafts' | 'in_stock' | 'out' | 'expired' | 'expiring' | 'archived' | null;
interface Row { id: number; name: string; strengths: string[]; formulas: string[]; company: string | null; type: string | null; barcode: string | null; stock: number; expired_stock: number; unit: string | null; next_expiry: string | null; expiry_days: number | null; draft: boolean; archived: boolean }
interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { status?: Status };
    stats: { total: number; drafts: number; in_stock: number; out: number; expired: number; expiring: number; archived: number };
    canManage: boolean;
    canBuy: boolean;
}

export const expiryAccent = (days: number | null) => (days === null ? 'var(--chart-8)' : days <= 0 ? 'var(--app-danger)' : days <= 30 ? 'var(--app-danger)' : days <= 90 ? 'var(--app-warning)' : 'var(--app-success)');

export default function ProductsIndex({ rows, filters, stats, canManage, canBuy }: Props) {
    const { wording } = usePage().props;
    const plural = wording.products ?? 'Products';
    const singular = (wording.product ?? 'Product').toLowerCase();
    const drafts = filters.status === 'drafts';

    const filter = (status: Status) =>
        router.get(route('products.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<Row>[] = [
        {
            key: 'product_name', label: wording.product ?? 'Product', sortable: true, render: (r) => (
                <Link href={route('products.show', r.id)} className="d-flex align-items-center gap-2 text-reset text-decoration-none">
                    <span className="avatar-tile" style={{ ['--accent' as string]: `var(--chart-${(r.id % 8) + 1})` }}><i className="bi bi-capsule" /></span>
                    <span className="min-w-0">
                        <span className="fw-semibold d-block text-break">{r.name}{r.strengths.length > 0 && <small className="text-muted fw-normal ms-1">{r.strengths.join(', ')}</small>}{r.draft && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--app-warning)' }}>Draft</span>}{r.archived && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Archived</span>}</span>
                        <small className="text-muted d-block text-truncate" style={{ maxWidth: 380 }}>{[r.formulas.join(' + '), r.company].filter(Boolean).join(' · ') || '—'}</small>
                    </span>
                </Link>
            ),
        },
        { key: 'type', label: wording.product_type ?? 'Type', render: (r) => <span className="text-muted">{r.type ?? '—'}</span> },
        {
            key: 'stock', label: 'In stock', sortable: true, align: 'end', render: (r) => r.draft ? <span className="text-muted">—</span> : (
                <div className="stock-cell tabular">
                    <span className={r.stock > 0 ? 'fw-semibold' : 'text-danger fw-semibold'}>{r.stock > 0 ? `${formatNumber(r.stock, 0)} ${r.unit ?? ''}` : 'Out of stock'}</span>
                    {r.expired_stock > 0 && <span className="expiry-chip" style={{ ['--accent' as string]: 'var(--app-danger)' }}>{formatNumber(r.expired_stock, 0)} expired</span>}
                </div>
            ),
        },
        {
            key: 'next_expiry', label: 'Next expiry', sortable: true, render: (r) => r.next_expiry
                ? <span className="expiry-chip" style={{ ['--accent' as string]: expiryAccent(r.expiry_days) }}>{r.next_expiry}{r.expiry_days !== null && r.expiry_days <= 90 && ` · ${r.expiry_days}d`}</span>
                : <span className="text-muted">—</span>,
        },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                r.archived ? (canManage ? <button type="button" className="btn btn-sm btn-soft" aria-label={`Restore ${r.name}`} onClick={() => router.post(route('products.restore', r.id), {}, { preserveScroll: true })}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button> : null) :
                <div className="d-inline-flex gap-1">
                    {r.draft && canManage ? (
                        <Link href={route('products.setup-wizard', r.id)} className="btn btn-sm btn-primary" aria-label={`Finish setup of ${r.name}`}><i className="bi bi-magic me-1" />Finish setup</Link>
                    ) : (
                        <>
                            <Link href={route('products.show', r.id)} className="btn btn-icon btn-soft" aria-label={`Open ${r.name}`} title="Open"><i className="bi bi-eye" /></Link>
                            {canBuy && <a href={route('purchases.create', { product_id: r.id })} className="btn btn-icon btn-soft" aria-label={`Add stock for ${r.name}`} title="Add stock (purchase)"><i className="bi bi-box-arrow-in-down" /></a>}
                        </>
                    )}
                    {canManage && <Link href={route('products.edit', r.id)} className="btn btn-icon btn-soft" aria-label={`Edit ${r.name}`} title="Edit"><i className="bi bi-pencil" /></Link>}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={plural} />
            <PageHeader icon="capsule" title={plural} help="products" description={`Your ${plural.toLowerCase()} with live stock from every batch: what’s in stock, what’s running out and what’s expiring.`}
                actions={canManage && <Link href={route('products.create')} className="btn btn-primary"><i className="bi bi-plus-lg me-1" />Add {singular}</Link>} />

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: `All ${plural.toLowerCase()}`, value: stats.total, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'in', icon: 'check2-circle', label: 'In stock', value: stats.in_stock, accent: 'var(--app-success)', onClick: () => filter('in_stock'), selected: filters.status === 'in_stock' },
                { key: 'out', icon: 'x-octagon', label: 'Out of stock', value: stats.out, accent: 'var(--app-danger)', onClick: () => filter('out'), selected: filters.status === 'out' },
                { key: 'expiring', icon: 'hourglass-split', label: 'Expiring ≤ 90 days', value: stats.expiring, accent: 'var(--app-warning)', onClick: () => filter('expiring'), selected: filters.status === 'expiring' },
                { key: 'expired', icon: 'calendar-x', label: 'Expired stock', value: stats.expired, accent: 'var(--chart-5)', onClick: () => filter('expired'), selected: filters.status === 'expired' },
                { key: 'drafts', icon: 'pencil-square', label: 'Drafts', value: stats.drafts, accent: 'var(--chart-8)', onClick: () => filter('drafts'), selected: drafts },
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived, accent: 'var(--chart-6)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
            ]} />

            {drafts && stats.drafts > 0 && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-magic" /><div><strong>Drafts aren’t sellable yet</strong><p>Click “Finish setup” to add the packaging (e.g. Box → Strip → Tablet). Then record a purchase to put them in stock.</p></div></div>
            )}
            {filters.status === 'expired' && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-danger)' }}><i className="bi bi-calendar-x" /><div><strong>Expired stock is never sold</strong><p>The POS skips expired batches automatically. Return them to the supplier or write them off.</p></div></div>
            )}

            <SectionCard icon="table" title={drafts ? 'Drafts' : filters.status === 'archived' ? `Archived ${plural.toLowerCase()}` : `All ${plural.toLowerCase()}`} subtitle="Click a row for batches, packaging and prices" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder={`Search ${plural.toLowerCase()}, manufacturer or scan a barcode…`}
                    empty={filters.q || filters.status ? undefined : (
                        <EmptyState icon="capsule" title={`No ${plural.toLowerCase()} yet`} hint="Add one, or import from the generic catalogue."
                            action={canManage && <Link href={route('products.create')} className="btn btn-primary btn-sm">Add {singular}</Link>} />
                    )} />
            </SectionCard>
        </>
    );
}
