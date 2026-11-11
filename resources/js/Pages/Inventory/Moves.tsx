import { Head, Link, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Row { id: number; number: string; date: string; lines: number; title: string; note: string | null; value: number | null; direction: 'increase' | 'decrease' | null }
interface Props {
    kind: 'transfer' | 'adjustment';
    rows: Paginated<Row>;
    filters: TableFilters & { status?: string | null };
    stats: { all: number; month: number; increase?: number; decrease?: number; written_off?: number };
    canManage: boolean;
    currency: string;
}

/** Stock transfers and stock adjustments (Inventory Pro). */
export default function Moves({ kind, rows, filters, stats, canManage, currency }: Props) {
    const transfer = kind === 'transfer';
    const base = transfer ? 'transfers' : 'adjustments';
    const title = transfer ? 'Stock transfers' : 'Stock adjustments';
    const filter = (status: string | null) => router.get(route(`${base}.index`), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, replace: true });

    const columns: Column<Row>[] = [
        { key: 'number', label: 'Number', render: (r) => <DrawerLink href={route(`${base}.show`, r.id)} className="fw-semibold" aria-label={`${transfer ? 'Transfer' : 'Adjustment'} ${r.number}`}>{r.number}</DrawerLink> },
        { key: 'title', label: transfer ? 'From → to' : 'Reason', render: (r) => <div><span className="fw-semibold">{r.title}</span>{r.direction && <span className="pill ms-2" style={{ ['--accent' as string]: r.direction === 'increase' ? 'var(--app-success)' : 'var(--app-danger)' }}>{r.direction === 'increase' ? 'Added' : 'Removed'}</span>}{r.note && <small className="d-block text-muted text-truncate" style={{ maxWidth: 360 }}>{r.note}</small>}</div> },
        { key: 'date', label: 'Date', render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        { key: 'lines', label: 'Lines', align: 'end', render: (r) => <span className="tabular">{r.lines}</span> },
        ...(transfer ? [] : [{ key: 'value', label: 'Value (cost)', align: 'end' as const, render: (r: Row) => <span className="tabular">{formatMoney(r.value ?? 0, currency)}</span> }]),
    ];

    return (
        <>
            <Head title={title} />
            <PageHeader icon={transfer ? 'arrow-left-right' : 'sliders2-vertical'} title={title} help="inventory-pro"
                description={transfer ? 'Move stock between warehouses. Batches keep their cost and expiry.' : 'Correct stock for damage, expiry, loss or a stock count. Removed stock leaves the books at its cost.'}
                actions={canManage && <DrawerLink href={route(`${base}.create`)} className="btn btn-primary" title={transfer ? 'New transfer' : 'New adjustment'}><i className="bi bi-plus-lg me-1" />{transfer ? 'New transfer' : 'New adjustment'}</DrawerLink>} />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: stats.all, accent: 'var(--chart-6)', onClick: () => filter(null), selected: !filters.status },
                { key: 'month', icon: 'calendar3', label: 'This month', value: stats.month, accent: 'var(--chart-1)', onClick: () => filter('month'), selected: filters.status === 'month' },
                ...(transfer ? [] : [
                    { key: 'increase', icon: 'plus-circle', label: 'Added', value: stats.increase ?? 0, accent: 'var(--app-success)', onClick: () => filter('increase'), selected: filters.status === 'increase' },
                    { key: 'decrease', icon: 'dash-circle', label: 'Removed', value: stats.decrease ?? 0, accent: 'var(--app-danger)', onClick: () => filter('decrease'), selected: filters.status === 'decrease' },
                    { key: 'written', icon: 'cash-stack', label: 'Written off this month', value: formatMoney(stats.written_off ?? 0, currency), accent: 'var(--app-warning)' },
                ]),
            ]} />
            <SectionCard icon="table" title={title} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search number or note…"
                    empty={filters.q || filters.status ? undefined : <EmptyState icon={transfer ? 'arrow-left-right' : 'sliders2-vertical'} title={`No ${title.toLowerCase()} yet`}
                        action={canManage && <Link href={route(`${base}.create`)} className="btn btn-primary btn-sm">{transfer ? 'New transfer' : 'New adjustment'}</Link>} />} />
            </SectionCard>
        </>
    );
}
