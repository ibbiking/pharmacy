import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import SupplierFormModal, { type SupplierFields } from '@/Components/Suppliers/SupplierFormModal';
import { formatMoney, initials } from '@/lib/format';

type Status = 'active' | 'idle' | 'archived' | null;
export interface SupplierRow extends SupplierFields { purchases: number; purchased_total: number; last_purchase: string | null; archived: boolean }
interface Props {
    rows: Paginated<SupplierRow>;
    filters: TableFilters & { status?: Status };
    stats: { total: number; active: number; archived: number; purchased_30d: number };
    currency: string;
    canManage: boolean;
    open: 'create' | number | null;
}

export default function SuppliersIndex({ rows, filters, stats, currency, canManage, open }: Props) {
    const { wording } = usePage().props;
    const [editing, setEditing] = useState<SupplierRow | 'new' | null>(null);
    const [archiving, setArchiving] = useState<SupplierRow | null>(null);
    const [busy, setBusy] = useState(false);
    const plural = wording.suppliers ?? 'Suppliers';

    useEffect(() => {
        if (open === 'create') setEditing('new');
        else if (typeof open === 'number') setEditing(rows.data.find((r) => r.id === open) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const filter = (status: Status) =>
        router.get(route('suppliers.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<SupplierRow>[] = [
        {
            key: 'name', label: 'Supplier', sortable: true, render: (r) => (
                <Link href={route('suppliers.show', r.id)} className="d-flex align-items-center gap-2 text-reset text-decoration-none">
                    <span className="avatar-tile" style={{ ['--accent' as string]: `var(--chart-${(r.id % 8) + 1})` }}>{initials(r.name)}</span>
                    <span className="min-w-0">
                        <span className="fw-semibold d-block text-break">{r.name}{r.archived && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Archived</span>}</span>
                        {r.company && <small className="text-muted">{r.company}</small>}
                    </span>
                </Link>
            ),
        },
        {
            key: 'contact', label: 'Contact', render: (r) => (
                <div className="small">
                    {r.phone && <a href={`tel:${r.phone}`} className="d-block text-reset"><i className="bi bi-telephone me-1 text-muted" />{r.phone}</a>}
                    {r.email && <a href={`mailto:${r.email}`} className="d-block text-reset text-break"><i className="bi bi-envelope me-1 text-muted" />{r.email}</a>}
                    {!r.phone && !r.email && <span className="text-muted">—</span>}
                </div>
            ),
        },
        { key: 'purchases_count', label: 'Purchases', sortable: true, align: 'end', className: 'tabular', render: (r) => (r.purchases ? r.purchases : <span className="text-muted">—</span>) },
        { key: 'purchased_total', label: 'Purchased', sortable: true, align: 'end', className: 'tabular fw-semibold', render: (r) => (r.purchased_total ? formatMoney(r.purchased_total, currency) : <span className="text-muted fw-normal">—</span>) },
        { key: 'last_purchase_at', label: 'Last purchase', sortable: true, render: (r) => <span className="text-muted">{r.last_purchase ?? 'Never'}</span> },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                <div className="d-inline-flex gap-1">
                    <Link href={route('suppliers.show', r.id)} className="btn btn-icon btn-soft" aria-label={`Open ${r.name}`} title="Open profile"><i className="bi bi-person-lines-fill" /></Link>
                    {canManage && (r.archived ? (
                        <button type="button" className="btn btn-sm btn-soft" aria-label={`Restore ${r.name}`} onClick={() => router.post(route('suppliers.restore', r.id), {}, { preserveScroll: true })}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button>
                    ) : (
                        <>
                            <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(r)} aria-label={`Edit ${r.name}`} title="Edit"><i className="bi bi-pencil" /></button>
                            <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(r)} aria-label={`Archive ${r.name}`} title="Archive"><i className="bi bi-archive" /></button>
                        </>
                    ))}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={plural} />
            <PageHeader icon="truck" title={plural} description="Who you buy from: contacts, purchase totals and history."
                actions={canManage && <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />Add supplier</button>} />

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All suppliers', value: stats.total, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'active', icon: 'bag-check', label: 'With purchases', value: stats.active, accent: 'var(--app-success)', onClick: () => filter('active'), selected: filters.status === 'active' },
                { key: 'idle', icon: 'hourglass', label: 'No purchases yet', value: stats.total - stats.active, accent: 'var(--chart-8)', onClick: () => filter('idle'), selected: filters.status === 'idle' },
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived, accent: 'var(--app-warning)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
                { key: '30d', icon: 'cash-stack', label: 'Purchased (30 days)', value: formatMoney(stats.purchased_30d, currency), accent: 'var(--chart-2)' },
            ]} />

            <SectionCard icon="table" title={filters.status === 'archived' ? 'Archived suppliers' : 'All suppliers'} subtitle="Click a supplier for the full profile" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search name, phone, email, company…"
                    empty={filters.q || filters.status ? undefined : (
                        <EmptyState icon="truck" title="No suppliers yet" hint="Add the people and companies you buy stock from."
                            action={canManage && <button type="button" className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Add your first supplier</button>} />
                    )} />
            </SectionCard>

            <SupplierFormModal supplier={editing} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} title="Archive supplier?" danger confirmLabel="Archive" busy={busy}
                message={archiving ? `“${archiving.name}” won’t be offered on new purchases.${archiving.purchases ? ` Its ${archiving.purchases} past purchase(s) keep showing the name.` : ''} You can restore it any time.` : ''}
                onConfirm={() => archiving && router.delete(route('suppliers.destroy', archiving.id), { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(null); } })}
                onClose={() => setArchiving(null)} />
        </>
    );
}
