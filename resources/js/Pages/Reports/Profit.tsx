import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import Chart from '@/Components/Chart';
import EmptyState from '@/Components/EmptyState';
import RangeFilter, { type RangeFilters } from '@/Components/Reports/RangeFilter';
import { formatMoney } from '@/lib/format';

interface Row { product_id: number; name: string; revenue: number; cost: number; profit: number; margin: number | null; estimated_lines: number }
interface Props {
    filters: RangeFilters;
    report: { kpis: { revenue: number; cost: number; profit: number; margin: number | null; estimated_lines: number }; series: { label: string; revenue: number; profit: number }[] };
    products: Paginated<Row>;
    currency: string;
}

export default function ProfitReport({ filters, report, products, currency }: Props) {
    const k = report.kpis;
    const money = (v: number) => formatMoney(v, currency);
    const chart = useMemo(() => ({
        grid: { left: 8, right: 12, top: 28, bottom: 8, containLabel: true },
        legend: { top: 0 },
        tooltip: { trigger: 'axis' as const, valueFormatter: (v: unknown) => money(Number(v)) },
        xAxis: { type: 'category' as const, data: report.series.map((d) => d.label) },
        yAxis: { type: 'value' as const },
        series: [
            { name: 'Revenue', type: 'bar' as const, data: report.series.map((d) => d.revenue), barMaxWidth: 22, itemStyle: { borderRadius: [5, 5, 0, 0] } },
            { name: 'Profit', type: 'line' as const, data: report.series.map((d) => d.profit), smooth: true },
        ],
    }), [report.series]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title="Profit & loss" />
            <PageHeader icon="calculator" title="Profit & loss" description="Revenue after discounts and returns against what the batches you sold actually cost you." />
            <RangeFilter routeName="reports.profit_loss" filters={filters} />
            {k.estimated_lines > 0 && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-info-circle" /><div><strong>{k.estimated_lines} older sale line(s) use an estimated cost</strong><p>They were sold before the app recorded which batches each sale came from, so their cost uses the previous estimate (fixed or latest purchase price).</p></div></div>}
            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Revenue" value={money(k.revenue)} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="box-seam" label="Cost of goods sold" value={money(k.cost)} accent="var(--chart-5)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="graph-up-arrow" label="Gross profit" value={money(k.profit)} accent={k.profit >= 0 ? 'var(--app-success)' : 'var(--app-danger)'} /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="percent" label="Margin" value={k.margin === null ? '—' : `${k.margin}%`} accent="var(--chart-3)" /></div>
            </div>
            <SectionCard icon="bar-chart" title="Day by day" accent="var(--chart-1)">
                {report.series.some((d) => d.revenue) ? <Chart option={chart} height={280} ariaLabel="Revenue and profit per day" /> : <EmptyState icon="bar-chart" title="No sales in this period" />}
            </SectionCard>
            <SectionCard icon="table" title="By product" subtitle="Highest profit first" flush>
                {products.data.length === 0 ? <EmptyState icon="capsule" title="Nothing sold in this period" /> : <>
                    <div className="table-responsive">
                        <table className="table dt-table align-middle mb-0">
                            <thead><tr><th>Product</th><th className="text-end">Revenue</th><th className="text-end">Cost</th><th className="text-end">Profit</th><th className="text-end">Margin</th></tr></thead>
                            <tbody>{products.data.map((r) => (
                                <tr key={r.product_id}>
                                    <td><Link href={route('products.show', r.product_id)} className="text-reset">{r.name}</Link>{r.estimated_lines > 0 && <small className="ms-2 text-muted" title="Part of the cost is estimated">est.</small>}</td>
                                    <td className="text-end tabular">{money(r.revenue)}</td>
                                    <td className="text-end tabular">{money(r.cost)}</td>
                                    <td className={`text-end tabular fw-semibold ${r.profit < 0 ? 'text-danger' : ''}`}>{money(r.profit)}</td>
                                    <td className="text-end tabular">{r.margin === null ? '—' : `${r.margin}%`}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                    {products.last_page > 1 && <div className="d-flex justify-content-end gap-2 p-2">
                        {products.prev_page_url && <Link href={products.prev_page_url} preserveScroll className="btn btn-sm btn-light">Previous</Link>}
                        <span className="small text-muted align-self-center">Page {products.current_page} of {products.last_page}</span>
                        {products.next_page_url && <Link href={products.next_page_url} preserveScroll className="btn btn-sm btn-light">Next</Link>}
                    </div>}
                </>}
            </SectionCard>
        </>
    );
}
