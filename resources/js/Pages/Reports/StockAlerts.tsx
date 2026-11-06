import { Head, Link, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { formatNumber } from '@/lib/format';

type Status = 'out' | 'ok' | null;
interface Row { id: number; name: string; stock: number; alert: number; unit: string | null; own_rule: boolean }
interface Props { rows: Paginated<Row>; filters: TableFilters & { status?: Status }; stats: { low: number; out: number; ok: number } }

export default function StockAlerts({ rows, filters, stats }: Props) {
    const go = (status: Status) => router.get(route('reports.stock_indication'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });
    const columns: Column<Row>[] = [
        { key: 'name', label: 'Product', render: (r) => <Link href={route('products.show', r.id)} className="fw-semibold text-reset">{r.name}</Link> },
        { key: 'stock', label: 'Sellable stock', align: 'end', className: 'tabular', render: (r) => <span className={r.stock <= 0 ? 'text-danger fw-semibold' : r.stock <= r.alert ? 'text-warning fw-semibold' : ''}>{formatNumber(r.stock, 2)} {r.unit}</span> },
        { key: 'alert', label: 'Alert at', align: 'end', className: 'tabular', render: (r) => <>{formatNumber(r.alert, 2)} {r.unit}{!r.own_rule && <small className="d-block text-muted">business-wide</small>}</> },
        { key: 'actions', label: '', align: 'end', render: (r) => (
            <div className="d-inline-flex gap-1">
                <Link href={route('purchases.create', { product_id: r.id })} className="btn btn-sm btn-primary"><i className="bi bi-box-arrow-in-down me-1" />Reorder</Link>
                <Link href={route('products.sale-price-preferences', r.id)} className="btn btn-icon btn-soft" title="Change the alert" aria-label={`Change the alert of ${r.name}`}><i className="bi bi-bell" /></Link>
            </div>
        ) },
    ];

    return (
        <>
            <Head title="Stock alerts" />
            <PageHeader icon="bell" title="Stock alerts" description="Products whose sellable stock (expired batches don’t count) is at or below their alert level." />
            <StatStrip stats={[
                { key: 'low', icon: 'exclamation-triangle', label: 'Low (at or below alert)', value: stats.low, accent: 'var(--app-warning)', onClick: () => go(null), selected: !filters.status },
                { key: 'out', icon: 'x-octagon', label: 'Out of stock', value: stats.out, accent: 'var(--app-danger)', onClick: () => go('out'), selected: filters.status === 'out' },
                { key: 'ok', icon: 'check2-circle', label: 'Above alert', value: stats.ok, accent: 'var(--app-success)', onClick: () => go('ok'), selected: filters.status === 'ok' },
            ]} />
            <SectionCard icon="table" title="Products" subtitle="Lowest first" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search products…"
                    empty={filters.q || filters.status ? undefined : <EmptyState icon="check2-circle" title="Nothing is running low" hint="Set alert levels on each product’s Sale preferences, or a business-wide one in Business settings." />} />
            </SectionCard>
        </>
    );
}
