import { Head, Link, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { formatMoney } from '@/lib/format';

type Status = 'today' | 'week' | 'returns' | 'returned' | null;
interface Row { id: number; invoice_no: string; date: string | null; items: number; grand_total: number; cash: number; status: 'sold' | 'partial' | 'returned' }
interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { status?: Status; from?: string | null; to?: string | null };
    stats: { total: number; today: number; today_sales: number; week: number; returns: number; returned: number };
    currency: string;
}

export const STATUS_PILL: Record<Row['status'], [string, string]> = {
    sold: ['Sold', 'var(--app-success)'], partial: ['Partly returned', 'var(--app-warning)'], returned: ['Returned', 'var(--chart-8)'],
};

export default function InvoicesIndex({ rows, filters, stats, currency }: Props) {
    const go = (patch: Partial<Props['filters']>) => router.get(route('invoices.index'), { ...filters, ...patch, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<Row>[] = [
        { key: 'invoice_no', label: 'Invoice', render: (r) => <Link href={route('invoices.show', r.invoice_no)} className="fw-semibold">{r.invoice_no}</Link> },
        { key: 'created_at', label: 'Date', sortable: true, render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        { key: 'items', label: 'Lines', align: 'end', className: 'tabular', render: (r) => r.items },
        { key: 'status', label: '', render: (r) => <span className="pill" style={{ ['--accent' as string]: STATUS_PILL[r.status][1] }}>{STATUS_PILL[r.status][0]}</span> },
        { key: 'grand_total', label: 'Total', sortable: true, align: 'end', className: 'tabular fw-semibold', render: (r) => formatMoney(r.grand_total, currency) },
        { key: 'actions', label: '', align: 'end', render: (r) => (
            <div className="d-inline-flex gap-1">
                <Link href={route('invoices.show', r.invoice_no)} className="btn btn-icon btn-soft" aria-label={`Open ${r.invoice_no}`} title="Open"><i className="bi bi-eye" /></Link>
                <a href={route('invoices.print', r.invoice_no)} target="_blank" rel="noreferrer" className="btn btn-icon btn-soft" aria-label={`Reprint ${r.invoice_no}`} title="Reprint"><i className="bi bi-printer" /></a>
            </div>
        ) },
    ];

    return (
        <>
            <Head title="Invoices" />
            <PageHeader icon="receipt" title="Invoices" description="Every sale from the POS. Open one to see its lines and batches, reprint it or take a return."
                actions={<Link href={route('pos.index')} className="btn btn-primary"><i className="bi bi-cart-check me-1" />Open POS</Link>} />
            <StatStrip stats={[
                { key: 'all', icon: 'receipt', label: 'All invoices', value: stats.total, accent: 'var(--chart-1)', onClick: () => go({ status: null }), selected: !filters.status },
                { key: 'today', icon: 'calendar-day', label: `Today · ${formatMoney(stats.today_sales, currency)}`, value: stats.today, accent: 'var(--app-success)', onClick: () => go({ status: 'today' }), selected: filters.status === 'today' },
                { key: 'week', icon: 'calendar-week', label: 'Last 7 days', value: stats.week, accent: 'var(--chart-3)', onClick: () => go({ status: 'week' }), selected: filters.status === 'week' },
                { key: 'returns', icon: 'arrow-return-left', label: 'With returns', value: stats.returns, accent: 'var(--app-warning)', onClick: () => go({ status: 'returns' }), selected: filters.status === 'returns' },
                { key: 'returned', icon: 'x-circle', label: 'Fully returned', value: stats.returned, accent: 'var(--chart-8)', onClick: () => go({ status: 'returned' }), selected: filters.status === 'returned' },
            ]} />
            <SectionCard icon="table" title="Invoices" subtitle="Newest first" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search invoice number…"
                    toolbar={
                        <div className="d-flex align-items-center gap-2 flex-wrap">
                            <input type="date" className="form-control form-control-sm" style={{ width: 150 }} aria-label="From date" value={filters.from ?? ''} onChange={(e) => go({ from: e.target.value || null })} />
                            <span className="text-muted small">to</span>
                            <input type="date" className="form-control form-control-sm" style={{ width: 150 }} aria-label="To date" value={filters.to ?? ''} onChange={(e) => go({ to: e.target.value || null })} />
                            {(filters.from || filters.to) && <button type="button" className="btn btn-sm btn-light" onClick={() => go({ from: null, to: null })}>Clear dates</button>}
                        </div>
                    }
                    empty={filters.q || filters.status || filters.from || filters.to ? undefined : <EmptyState icon="receipt" title="No sales yet" hint="Sales made at the POS appear here." />} />
            </SectionCard>
        </>
    );
}
