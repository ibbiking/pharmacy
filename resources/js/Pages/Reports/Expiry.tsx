import { Head, Link, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { formatMoney, formatNumber } from '@/lib/format';
import { expiryAccent } from '@/Pages/Products/Index';

type Status = 'expired' | '30' | '90' | 'later' | null;
interface Row { id: number; product_id: number; product: string; batch: string | null; purchase_id: number; remaining: number; unit: string | null; value: number; expiry: string; days: number }
interface Props { rows: Paginated<Row>; filters: TableFilters & { status?: Status }; stats: Record<'expired' | '30' | '90' | 'later', { count: number; value: number }>; currency: string }

export default function ExpiryReport({ rows, filters, stats, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const go = (status: Status) => router.get(route('reports.expiry'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });
    const columns: Column<Row>[] = [
        { key: 'product', label: 'Product', render: (r) => <><Link href={route('products.show', r.product_id)} className="fw-semibold text-reset">{r.product}</Link><small className="d-block text-muted">Batch <Link href={route('purchases.show', r.purchase_id)}>{r.batch ?? '—'}</Link></small></> },
        { key: 'expiry', label: 'Expiry', render: (r) => <span className="expiry-chip" style={{ ['--accent' as string]: expiryAccent(r.days) }}>{r.expiry} · {r.days <= 0 ? `expired ${Math.abs(r.days)}d ago` : `${r.days}d left`}</span> },
        { key: 'remaining', label: 'Left', align: 'end', className: 'tabular', render: (r) => `${formatNumber(r.remaining, 0)} ${r.unit ?? ''}` },
        { key: 'value', label: 'Value at cost', align: 'end', className: 'tabular fw-semibold', render: (r) => money(r.value) },
    ];

    return (
        <>
            <Head title="Expiry report" />
            <PageHeader icon="calendar-x" title="Expiry report" help="lot-selection" description="Batches that still have stock, by expiry date. Expired batches are never sold; return them to the supplier or write them off." />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All with stock', value: stats.expired.count + stats['30'].count + stats['90'].count + stats.later.count, accent: 'var(--chart-1)', onClick: () => go(null), selected: !filters.status },
                { key: 'expired', icon: 'calendar-x', label: `Expired · ${money(stats.expired.value)}`, value: stats.expired.count, accent: 'var(--app-danger)', onClick: () => go('expired'), selected: filters.status === 'expired' },
                { key: '30', icon: 'exclamation-triangle', label: `≤ 30 days · ${money(stats['30'].value)}`, value: stats['30'].count, accent: 'var(--chart-5)', onClick: () => go('30'), selected: filters.status === '30' },
                { key: '90', icon: 'hourglass-split', label: `31–90 days · ${money(stats['90'].value)}`, value: stats['90'].count, accent: 'var(--app-warning)', onClick: () => go('90'), selected: filters.status === '90' },
                { key: 'later', icon: 'check2-circle', label: 'Later', value: stats.later.count, accent: 'var(--app-success)', onClick: () => go('later'), selected: filters.status === 'later' },
            ]} />
            <SectionCard icon="table" title="Batches" subtitle="Soonest expiry first" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search product or batch…"
                    empty={filters.q || filters.status ? undefined : <EmptyState icon="calendar-check" title="No batches with an expiry date in stock" />} />
            </SectionCard>
        </>
    );
}
