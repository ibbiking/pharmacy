import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import Chart from '@/Components/Chart';
import EmptyState from '@/Components/EmptyState';
import RangeFilter, { type RangeFilters } from '@/Components/Reports/RangeFilter';
import { formatMoney, formatNumber } from '@/lib/format';

interface Props {
    filters: RangeFilters;
    report: { kpis: { invoices: number; net_sales: number; discounts: number; sale_tax: number; average: number; returns: number; refunds: number }; series: { label: string; sales: number; refunds: number }[] };
    best: Paginated<{ product_id: number; name: string; revenue: number; base_qty: number; lines: number }>;
    currency: string;
}

export default function SalesReport({ filters, report, best, currency }: Props) {
    const k = report.kpis;
    const money = (v: number) => formatMoney(v, currency);
    const chart = useMemo(() => ({
        grid: { left: 8, right: 12, top: 28, bottom: 8, containLabel: true },
        legend: { top: 0 },
        tooltip: { trigger: 'axis' as const, valueFormatter: (v: unknown) => money(Number(v)) },
        xAxis: { type: 'category' as const, data: report.series.map((d) => d.label) },
        yAxis: { type: 'value' as const },
        series: [
            { name: 'Sales', type: 'bar' as const, data: report.series.map((d) => d.sales), barMaxWidth: 22, itemStyle: { borderRadius: [5, 5, 0, 0] } },
            { name: 'Refunds', type: 'line' as const, data: report.series.map((d) => d.refunds), smooth: true },
        ],
    }), [report.series]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title="Sales report" />
            <PageHeader icon="graph-up-arrow" title="Sales report" description="Sales after discounts and returns, the sale tax collected and the refunds paid in the period." />
            <RangeFilter routeName="reports.sales" filters={filters} />
            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Net sales" value={money(k.net_sales)} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="receipt" label={`Invoices · average ${money(k.average)}`} value={formatNumber(k.invoices, 0)} accent="var(--chart-3)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="percent" label={`Discounts · sale tax ${money(k.sale_tax)}`} value={money(k.discounts)} accent="var(--app-warning)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="arrow-return-left" label={`Refunds · ${k.returns} return(s)`} value={money(k.refunds)} accent="var(--app-danger)" /></div>
            </div>
            <div className="row g-3">
                <div className="col-xl-7">
                    <SectionCard icon="bar-chart" title="Day by day" accent="var(--chart-1)">
                        {report.series.some((d) => d.sales || d.refunds) ? <Chart option={chart} height={300} ariaLabel="Sales and refunds per day" /> : <EmptyState icon="bar-chart" title="No sales in this period" />}
                    </SectionCard>
                </div>
                <div className="col-xl-5">
                    <SectionCard icon="trophy" title="Best sellers" subtitle="By revenue after line discounts" flush>
                        {best.data.length === 0 ? <EmptyState icon="trophy" title="Nothing sold yet" /> : <>
                            <ul className="list-unstyled mb-0">
                                {best.data.map((b, i) => (
                                    <li key={b.product_id} className="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                                        <span className="chain-n m-0" style={{ width: 24, height: 24 }}>{(best.current_page - 1) * best.per_page + i + 1}</span>
                                        <Link href={route('products.show', b.product_id)} className="flex-grow-1 text-truncate text-reset">{b.name}</Link>
                                        <small className="text-muted">{b.lines} sale(s)</small>
                                        <strong className="tabular">{money(b.revenue)}</strong>
                                    </li>
                                ))}
                            </ul>
                            {best.last_page > 1 && <div className="d-flex justify-content-end gap-2 p-2">
                                {best.prev_page_url && <Link href={best.prev_page_url} preserveScroll only={['best']} className="btn btn-sm btn-light">Previous</Link>}
                                {best.next_page_url && <Link href={best.next_page_url} preserveScroll only={['best']} className="btn btn-sm btn-light">Next</Link>}
                            </div>}
                        </>}
                    </SectionCard>
                    <Link href={route('invoices.index', { from: filters.from, to: filters.to })} className="btn btn-light w-100"><i className="bi bi-receipt me-1" />See the invoices of this period</Link>
                </div>
            </div>
        </>
    );
}
