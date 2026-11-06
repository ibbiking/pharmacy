import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import Chart from '@/Components/Chart';
import EmptyState from '@/Components/EmptyState';
import RangeFilter, { type RangeFilters } from '@/Components/Reports/RangeFilter';
import { formatMoney, formatNumber } from '@/lib/format';

interface Kpi { value: number; previous: number | null; change: number | null }
interface Kpis { net_sales: Kpi; invoices: Kpi; average: Kpi; profit: Kpi; refunds: Kpi; stock_value: Kpi; previous_label: string }
interface Charts { series: { label: string; sales: number; refunds: number }[]; heat: [number, number, number, number][]; top: { product_id: number; name: string; revenue: number }[]; expiry: { label: string; value: number }[] }
interface Panels {
    low: { id: number; name: string; stock: number; alert: number; unit: string | null }[];
    expiring: { product_id: number; product: string; batch: string | null; remaining: number; days: number; value: number }[];
    invoices: { invoice_no: string; total: number; at: string; returned: boolean }[];
    returns: { return_no: string; refund: number; at: string }[];
}
interface Suggestions {
    reorder: { id: number; name: string; unit: string | null; suggested: number }[]; short_dated: number; below_cost: number;
    overrides: { name: string; count: number }[]; no_alert: number; unused_taxes: number;
}
interface Props { filters: RangeFilters; currency: string; kpis?: Kpis; charts?: Charts; panels?: Panels; suggestions?: Suggestions }

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const skeleton = (h: number) => <div className="skeleton rounded" style={{ height: h }} />;

/** Arrow + % against the previous period; `goodWhenUp=false` for refunds. */
function Trend({ k, goodWhenUp = true }: { k: Kpi; goodWhenUp?: boolean }) {
    if (k.change === null) return <small className="d-block text-muted fw-normal" style={{ fontSize: '0.72rem' }}>no earlier data</small>;
    const up = k.change >= 0;
    const good = up === goodWhenUp;
    return <small className={`d-block fw-semibold ${good ? 'text-success' : 'text-danger'}`} style={{ fontSize: '0.72rem' }}><i className={`bi bi-arrow-${up ? 'up' : 'down'}-right`} /> {Math.abs(k.change)}% vs before</small>;
}

export default function Dashboard({ filters, currency, kpis, charts, panels, suggestions }: Props) {
    const { auth, business } = usePage().props;
    const money = (v: number) => formatMoney(v, currency);
    const hour = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    const salesChart = useMemo(() => charts && ({
        grid: { left: 8, right: 12, top: 28, bottom: 8, containLabel: true }, legend: { top: 0 },
        tooltip: { trigger: 'axis' as const, valueFormatter: (v: unknown) => money(Number(v)) },
        xAxis: { type: 'category' as const, data: charts.series.map((d) => d.label) }, yAxis: { type: 'value' as const },
        series: [
            { name: 'Sales', type: 'line' as const, smooth: true, areaStyle: { opacity: 0.15 }, data: charts.series.map((d) => d.sales) },
            { name: 'Refunds', type: 'bar' as const, barMaxWidth: 14, data: charts.series.map((d) => d.refunds) },
        ],
    }), [charts]); // eslint-disable-line react-hooks/exhaustive-deps
    const heatChart = useMemo(() => charts && ({
        grid: { left: 8, right: 12, top: 8, bottom: 40, containLabel: true },
        tooltip: { formatter: (p: unknown) => { const v = (p as { value: [number, number, number, number] }).value; return `${DAYS[v[1]]} ${v[0]}:00 · ${money(v[2])} · ${v[3]} sale(s)`; } },
        xAxis: { type: 'category' as const, data: Array.from({ length: 24 }, (_, h) => `${h}`), splitArea: { show: true } },
        yAxis: { type: 'category' as const, data: DAYS, splitArea: { show: true } },
        visualMap: { min: 0, max: Math.max(1, ...charts.heat.map((h) => h[2])), calculable: false, orient: 'horizontal' as const, left: 'center', bottom: 0, itemHeight: 120 },
        series: [{ type: 'heatmap' as const, data: charts.heat, emphasis: { itemStyle: { shadowBlur: 6 } } }],
    }), [charts]); // eslint-disable-line react-hooks/exhaustive-deps
    const expiryChart = useMemo(() => charts && ({
        tooltip: { trigger: 'item' as const, valueFormatter: (v: unknown) => money(Number(v)) },
        legend: { bottom: 0 },
        series: [{ type: 'pie' as const, radius: ['42%', '68%'], center: ['50%', '44%'], label: { show: false },
            data: charts.expiry.filter((e) => e.value > 0).map((e) => ({ name: e.label, value: e.value })) }],
    }), [charts]); // eslint-disable-line react-hooks/exhaustive-deps

    const openDay = (index: number) => {
        // Clicking a day opens that day's invoices (only when the chart is per day).
        if (!charts || charts.series.length > 400 || filters.range === 'today') return;
        const start = new Date(`${filters.from}T00:00:00`);
        start.setDate(start.getDate() + index);
        const d = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;
        router.get(route('invoices.index'), { from: d, to: d });
    };

    const suggestion = (show: boolean, icon: string, accent: string, title: ReactNode, text: string, action: ReactNode) => show && (
        <li className="d-flex gap-3 align-items-start py-2 border-bottom">
            <span className="tile flex-shrink-0" style={{ ['--accent' as string]: accent, width: 36, height: 36, borderRadius: 10, display: 'grid', placeItems: 'center', background: `color-mix(in srgb, ${accent} 14%, transparent)`, color: accent }}><i className={`bi bi-${icon}`} /></span>
            <div className="flex-grow-1 min-w-0"><strong className="d-block">{title}</strong><small className="text-muted">{text}</small></div>
            {action}
        </li>
    );

    return (
        <>
            <Head title="Dashboard" />
            <PageHeader icon="speedometer2" title={`${greet}, ${auth.user?.name?.split(' ')[0] ?? ''}`} description={`${business?.name ?? ''}: how the business is doing and what needs attention.`}
                actions={<Link href={route('pos.index')} className="btn btn-primary"><i className="bi bi-cart-check me-1" />Open POS</Link>} />
            <RangeFilter routeName="dashboard" filters={filters} />

            <Deferred data="kpis" fallback={<div className="row g-3 mb-3">{Array.from({ length: 6 }, (_, i) => <div key={i} className="col-sm-6 col-lg-4">{skeleton(92)}</div>)}</div>}>
                {kpis && <div className="row g-3 mb-3">
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="cash-stack" label="Net sales" value={<>{money(kpis.net_sales.value)}<Trend k={kpis.net_sales} /></>} accent="var(--chart-1)" /></div>
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="graph-up-arrow" label="Gross profit" value={<>{money(kpis.profit.value)}<Trend k={kpis.profit} /></>} accent="var(--app-success)" /></div>
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="receipt" label="Invoices" value={<>{formatNumber(kpis.invoices.value, 0)}<Trend k={kpis.invoices} /></>} accent="var(--chart-3)" /></div>
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="basket" label="Average basket" value={<>{money(kpis.average.value)}<Trend k={kpis.average} /></>} accent="var(--chart-2)" /></div>
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="arrow-return-left" label="Refunds" value={<>{money(kpis.refunds.value)}<Trend k={kpis.refunds} goodWhenUp={false} /></>} accent="var(--app-danger)" /></div>
                    <div className="col-sm-6 col-lg-4"><KpiCard icon="boxes" label="Stock value (cost)" value={money(kpis.stock_value.value)} accent="var(--chart-6)" /></div>
                    <small className="text-muted">Compared with {kpis.previous_label}.</small>
                </div>}
            </Deferred>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="activity" title="Sales over time" subtitle="Click a day to see its invoices" accent="var(--chart-1)">
                        <Deferred data="charts" fallback={skeleton(280)}>
                            {charts?.series.some((d) => d.sales || d.refunds) ? <Chart option={salesChart!} height={280} ariaLabel="Sales and refunds over time" onEvents={{ click: (p: { dataIndex: number }) => openDay(p.dataIndex) }} />
                                : <EmptyState icon="activity" title="No sales in this period" action={<Link href={route('pos.index')} className="btn btn-primary btn-sm">Make a sale</Link>} />}
                        </Deferred>
                    </SectionCard>
                    <SectionCard icon="grid-3x3" title="When you sell" subtitle="Sales by hour and weekday" accent="var(--chart-3)">
                        <Deferred data="charts" fallback={skeleton(240)}>
                            {charts && charts.heat.length > 0 ? <Chart option={heatChart!} height={260} ariaLabel="Heat map of sales by hour and weekday" /> : <EmptyState icon="grid-3x3" title="Not enough sales yet" />}
                        </Deferred>
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="lightbulb" title="What to do next" accent="var(--app-warning)">
                        <Deferred data="suggestions" fallback={skeleton(220)}>
                            {suggestions && <ul className="list-unstyled mb-0">
                                {suggestion(suggestions.reorder.length > 0, 'cart-plus', 'var(--app-danger)', `Reorder ${suggestions.reorder.length} product(s)`,
                                    suggestions.reorder.slice(0, 3).map((r) => `${r.name}: ~${formatNumber(r.suggested, 0)} ${r.unit ?? ''}`).join(' · ') + ' (from the last 30 days’ sales)',
                                    <Link href={route('purchases.create', { product_id: suggestions.reorder[0]?.id })} className="btn btn-sm btn-light">Buy</Link>)}
                                {suggestion(suggestions.short_dated > 0, 'hourglass-split', 'var(--app-warning)', `${suggestions.short_dated} batch(es) expire within 60 days`, 'Sell them first or offer a discount before they expire.',
                                    <Link href={route('reports.expiry', { status: '30' })} className="btn btn-sm btn-light">View</Link>)}
                                {suggestion(suggestions.below_cost > 0, 'graph-down-arrow', 'var(--app-danger)', `${suggestions.below_cost} sale line(s) below cost`, 'In the last 30 days. Check the prices or the discounts given.',
                                    <Link href={route('reports.profit_loss', { range: '30d' })} className="btn btn-sm btn-light">Check</Link>)}
                                {suggestion(suggestions.overrides.length > 0, 'pencil-square', 'var(--chart-5)', 'Prices changed at the till', suggestions.overrides.map((o) => `${o.name}: ${o.count}`).join(' · ') + ' (last 30 days)',
                                    <Link href={route('invoices.index', { status: 'week' })} className="btn btn-sm btn-light">Invoices</Link>)}
                                {suggestion(suggestions.no_alert > 0, 'bell-slash', 'var(--chart-8)', `${suggestions.no_alert} product(s) have no stock alert`, 'They will never warn you before running out.',
                                    <Link href={route('reports.stock_unaligned')} className="btn btn-sm btn-light">Set</Link>)}
                                {suggestion(suggestions.unused_taxes > 0, 'percent', 'var(--chart-6)', `${suggestions.unused_taxes} tax(es) never used`, 'Archive the ones you don’t need to keep the lists short.',
                                    <Link href={route('taxes.index', { usage: 'unused' })} className="btn btn-sm btn-light">Review</Link>)}
                                {suggestions.reorder.length + suggestions.short_dated + suggestions.below_cost + suggestions.overrides.length + suggestions.no_alert + suggestions.unused_taxes === 0
                                    && <li className="text-muted small py-2"><i className="bi bi-check2-circle text-success me-1" />Nothing needs attention right now.</li>}
                            </ul>}
                        </Deferred>
                    </SectionCard>
                    <SectionCard icon="trophy" title="Top products" accent="var(--chart-1)">
                        <Deferred data="charts" fallback={skeleton(160)}>
                            {charts && charts.top.length ? <ol className="mb-0 ps-3 d-grid gap-1">{charts.top.map((t) => <li key={t.product_id} className="d-flex"><Link href={route('products.show', t.product_id)} className="flex-grow-1 text-truncate text-reset">{t.name}</Link><strong className="tabular ms-2">{money(t.revenue)}</strong></li>)}</ol>
                                : <small className="text-muted">No sales in this period.</small>}
                        </Deferred>
                    </SectionCard>
                    <SectionCard icon="pie-chart" title="Stock by expiry" subtitle="Value at cost" accent="var(--chart-6)">
                        <Deferred data="charts" fallback={skeleton(200)}>
                            {charts && charts.expiry.some((e) => e.value > 0) ? <Chart option={expiryChart!} height={220} ariaLabel="Stock value by expiry" onEvents={{ click: () => router.get(route('reports.expiry')) }} /> : <small className="text-muted">No stock with an expiry date.</small>}
                        </Deferred>
                    </SectionCard>
                </div>
            </div>

            <Deferred data="panels" fallback={<div className="row g-3">{[0, 1, 2, 3].map((i) => <div key={i} className="col-md-6 col-xl-3">{skeleton(200)}</div>)}</div>}>
                {panels && <div className="row g-3">
                    <div className="col-md-6 col-xl-3"><SectionCard icon="bell" title="Low stock" accent="var(--app-danger)" actions={<Link href={route('reports.stock_indication')} className="small">All</Link>}>
                        {panels.low.length ? <ul className="list-unstyled small mb-0 d-grid gap-1">{panels.low.map((l) => <li key={l.id} className="d-flex"><Link href={route('products.show', l.id)} className="flex-grow-1 text-truncate text-reset">{l.name}</Link><span className="tabular text-danger ms-2">{formatNumber(l.stock, 1)}/{formatNumber(l.alert, 0)} {l.unit}</span></li>)}</ul> : <small className="text-muted">Nothing is running low.</small>}
                    </SectionCard></div>
                    <div className="col-md-6 col-xl-3"><SectionCard icon="hourglass-split" title="Expiring ≤ 30 days" accent="var(--app-warning)" actions={<Link href={route('reports.expiry', { status: '30' })} className="small">All</Link>}>
                        {panels.expiring.length ? <ul className="list-unstyled small mb-0 d-grid gap-1">{panels.expiring.map((e, i) => <li key={i} className="d-flex"><Link href={route('products.show', e.product_id)} className="flex-grow-1 text-truncate text-reset">{e.product}</Link><span className="tabular ms-2 text-warning">{e.days}d</span></li>)}</ul> : <small className="text-muted">No batch expires in the next 30 days.</small>}
                    </SectionCard></div>
                    <div className="col-md-6 col-xl-3"><SectionCard icon="receipt" title="Latest invoices" accent="var(--chart-1)" actions={<Link href={route('invoices.index')} className="small">All</Link>}>
                        {panels.invoices.length ? <ul className="list-unstyled small mb-0 d-grid gap-1">{panels.invoices.map((i) => <li key={i.invoice_no} className="d-flex"><Link href={route('invoices.show', i.invoice_no)} className="flex-grow-1">{i.invoice_no}</Link><span className="text-muted me-2">{i.at}</span><span className="tabular">{money(i.total)}</span></li>)}</ul> : <small className="text-muted">No sales yet.</small>}
                    </SectionCard></div>
                    <div className="col-md-6 col-xl-3"><SectionCard icon="arrow-return-left" title="Latest returns" accent="var(--chart-5)" actions={<Link href={route('reports.returns')} className="small">All</Link>}>
                        {panels.returns.length ? <ul className="list-unstyled small mb-0 d-grid gap-1">{panels.returns.map((r) => <li key={r.return_no} className="d-flex"><Link href={route('returns.show', r.return_no)} className="flex-grow-1">{r.return_no}</Link><span className="text-muted me-2">{r.at}</span><span className="tabular">{money(r.refund)}</span></li>)}</ul> : <small className="text-muted">No returns.</small>}
                    </SectionCard></div>
                </div>}
            </Deferred>
        </>
    );
}
