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

export interface DocMeta { side: 'sales' | 'purchase'; label: string; plural: string; series: string; slug: string; icon: string; accent: string; side_route: 'selling' | 'buying' }
interface Row { id: number; number: string; party: string | null; party_sub: string | null; date: string; due_date: string | null; status: string; total: number; balance: number; reference: string | null }
interface Props {
    type: string;
    meta: DocMeta;
    rows: Paginated<Row>;
    filters: TableFilters & { status?: string | null };
    statuses: { key: string; label: string }[];
    stats: Record<string, number>;
    canCreate: boolean;
    currency: string;
}

const ACCENT: Record<string, string> = { draft: 'var(--chart-8)', paid: 'var(--app-success)', unpaid: 'var(--app-warning)', partial: 'var(--chart-3)', void: 'var(--chart-6)', overdue: 'var(--app-danger)' };

/** Any B2B document list (quotations, orders, invoices, credit notes, purchase orders, bills, vendor credits). */
export default function DocumentsIndex({ type, meta, rows, filters, statuses, stats, canCreate, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const payable = type === 'sales_invoice' || type === 'bill';
    const base = `${meta.side_route}.${meta.slug}`;
    const filter = (status: string | null) => router.get(route(`${base}.index`), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, replace: true });
    const party = meta.side === 'sales' ? 'Customer' : 'Supplier';
    const hint: Record<string, string> = {
        credit_note: 'Credit notes are created from an invoice (open the invoice → Credit note).',
        bill: 'Bills are created from a purchase order after receiving the goods (open the order → Create bill).',
        vendor_credit: 'Vendor credits are created from a bill (open the bill → Vendor credit).',
    };

    const columns: Column<Row>[] = [
        { key: 'number', label: 'Number', sortable: true, render: (r) => <DrawerLink href={route(`${meta.side_route}.docs.show`, r.id)} className="fw-semibold" aria-label={`${meta.label} ${r.number}`}>{r.number}</DrawerLink> },
        { key: 'party', label: party, render: (r) => <div className="min-w-0"><span className="fw-semibold">{r.party ?? '—'}</span>{(r.party_sub || r.reference) && <small className="d-block text-muted text-truncate" style={{ maxWidth: 280 }}>{[r.party_sub, r.reference && `Ref ${r.reference}`].filter(Boolean).join(' · ')}</small>}</div> },
        { key: 'date', label: 'Date', sortable: true, render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        ...(payable || type === 'quotation' ? [{ key: 'due_date', label: type === 'quotation' ? 'Valid until' : 'Due', sortable: true, render: (r: Row) => <span className={`text-nowrap ${r.status === 'overdue' ? 'text-danger fw-semibold' : 'text-muted'}`}>{r.due_date ?? '—'}</span> }] : []),
        { key: 'status', label: 'Status', render: (r) => statusPill(r.status) },
        { key: 'total', label: 'Total', sortable: true, align: 'end', render: (r) => <span className="tabular">{money(r.total)}</span> },
        ...(payable || type === 'credit_note' || type === 'vendor_credit' ? [{ key: 'balance', label: 'Open', sortable: true, align: 'end' as const, render: (r: Row) => <span className="tabular fw-semibold">{r.balance > 0 ? money(r.balance) : '—'}</span> }] : []),
        { key: 'print', label: '', align: 'end', render: (r) => <a href={route(`${meta.side_route}.docs.print`, r.id)} target="_blank" rel="noreferrer" className="btn btn-icon btn-soft" title="Print / PDF" aria-label={`Print ${r.number}`}><i className="bi bi-printer" /></a> },
    ];

    return (
        <>
            <Head title={meta.plural} />
            <PageHeader icon={meta.icon} title={meta.plural} help={meta.side === 'sales' ? 'b2b-sales' : 'b2b-purchasing'}
                description={hint[type] ?? `Every ${meta.label.toLowerCase()} with its status. Click one to open it on the right.`}
                actions={canCreate && <DrawerLink href={route(`${base}.create`)} className="btn btn-primary" title={`New ${meta.label.toLowerCase()}`}><i className="bi bi-plus-lg me-1" />New {meta.label.toLowerCase()}</DrawerLink>} />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: stats.all ?? 0, accent: meta.accent, onClick: () => filter(null), selected: !filters.status },
                ...statuses.map((s) => ({ key: s.key, icon: 'circle-fill', label: s.label, value: stats[s.key] ?? 0, accent: ACCENT[s.key] ?? 'var(--chart-1)', onClick: () => filter(s.key), selected: filters.status === s.key })),
                ...(payable ? [
                    { key: 'overdue', icon: 'alarm', label: 'Overdue', value: stats.overdue ?? 0, accent: 'var(--app-danger)', onClick: () => filter('overdue'), selected: filters.status === 'overdue' },
                    { key: 'open', icon: 'hourglass-split', label: type === 'bill' ? 'You owe' : 'Owed to you', value: money(stats.open_amount ?? 0), accent: 'var(--app-warning)' },
                ] : []),
            ]} />
            <SectionCard icon="table" title={meta.plural} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder={`Search number, reference or ${party.toLowerCase()}…`}
                    empty={filters.q || filters.status ? undefined : <EmptyState icon={meta.icon} title={`No ${meta.plural.toLowerCase()} yet`} hint={hint[type]}
                        action={canCreate && <DrawerLink href={route(`${base}.create`)} className="btn btn-primary btn-sm" title={`New ${meta.label.toLowerCase()}`}>New {meta.label.toLowerCase()}</DrawerLink>} />} />
            </SectionCard>
        </>
    );
}
