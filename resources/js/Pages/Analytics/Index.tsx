import { Deferred, Head, router } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import Chart from '@/Components/Chart';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Kpi { value: number; previous: number; change: number | null }
interface Summary {
    period: { from: string; to: string; previous_from: string; previous_to: string };
    kpis: Record<string, Kpi>;
    balances: { receivables: number; receivables_overdue: number; payables: number; payables_overdue: number; stock_value: number; cash: number | null };
    series: { labels: string[]; pos: number[]; b2b: number[]; expenses: number[] };
    top_products: { id: number; name: string; revenue: number }[];
    top_customers: { id: number; name: string; revenue: number; open: number }[];
}
interface Props { filters: { from: string; to: string }; summary?: Summary; weekly: boolean; currency: string }

const KPIS: [string, string, string, string][] = [
    ['revenue', 'Revenue', 'graph-up-arrow', 'var(--chart-1)'], ['gross_profit', 'Gross profit', 'box-seam', 'var(--app-success)'], ['expenses', 'Expenses', 'wallet2', 'var(--chart-5)'],
    ['net_profit', 'Net profit', 'trophy', 'var(--chart-3)'], ['purchases', 'Purchases', 'cart-plus', 'var(--chart-2)'], ['payments_received', 'Payments received', 'cash-coin', 'var(--chart-6)'],
];
const PRESETS: [string, number][] = [['7 days', 6], ['30 days', 29], ['90 days', 89], ['12 months', 364]];

/** Cross-module dashboard with the previous period for comparison. */
export default function Analytics({ filters, summary, weekly, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const go = (patch: Record<string, unknown>) => router.get(route('analytics.index'), { ...filters, ...patch }, { preserveState: true, replace: true });
    const preset = (days: number) => { const to = new Date(); const from = new Date(); from.setDate(to.getDate() - days); go({ from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) }); };
    const exp = (report: string) => route('analytics.export', { report, from: filters.from, to: filters.to });
    return (
        <>
            <Head title="Analytics" />
            <PageHeader icon="bar-chart-line" title="Analytics" help="analytics" description="POS and B2B sales, purchases and expenses together, compared with the period before." />
            <div className="app-card d-flex flex-wrap align-items-center gap-2 p-3 mb-3">
                {PRESETS.map(([label, days]) => <button key={label} type="button" className="btn btn-sm btn-light" onClick={() => preset(days)}>{label}</button>)}
                <input type="date" aria-label="From" className="form-control form-control-sm" style={{ width: 150 }} value={filters.from} onChange={(e) => go({ from: e.target.value })} />
                <input type="date" aria-label="To" className="form-control form-control-sm" style={{ width: 150 }} value={filters.to} onChange={(e) => go({ to: e.target.value })} />
                <span className="flex-grow-1" />
                <div className="dropdown">
                    <button type="button" className="btn btn-sm btn-soft dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i className="bi bi-download me-1" />Export CSV</button>
                    <ul className="dropdown-menu dropdown-menu-end">
                        {[['pos_sales', 'POS sales'], ['b2b_documents', 'B2B documents'], ['purchases', 'Purchases'], ['expenses', 'Expenses'], ['payments', 'Payments']].map(([k, l]) => <li key={k}><a className="dropdown-item" href={exp(k!)}>{l}</a></li>)}
                    </ul>
                </div>
                <div className="form-check form-switch mb-0 ms-2">
                    <input id="an-weekly" className="form-check-input" type="checkbox" checked={weekly} onChange={(e) => router.post(route('analytics.weekly'), { weekly: e.target.checked }, { preserveScroll: true })} />
                    <label className="form-check-label small" htmlFor="an-weekly">Weekly email</label>
                </div>
            </div>
            <Deferred data="summary" fallback={<div className="row g-3">{Array.from({ length: 6 }, (_, i) => <div key={i} className="col-sm-6 col-xl-4"><div className="skeleton" style={{ height: 96 }} /></div>)}</div>}>
                {summary ? <>
                    <div className="row g-3 mb-3">{KPIS.map(([k, label, icon, accent]) => {
                        const v = summary.kpis[k]!;
                        return <div key={k} className="col-sm-6 col-xl-4"><KpiCard icon={icon} label={label} accent={accent} value={<>{money(v.value)}
                            {v.change !== null && <small className={`d-block fs-6 fw-normal ${v.change >= 0 === (k !== 'expenses' && k !== 'purchases') ? 'text-success' : 'text-danger'}`}><i className={`bi bi-arrow-${v.change >= 0 ? 'up' : 'down'}-short`} />{Math.abs(v.change)}% vs {money(v.previous)}</small>}</>} /></div>;
                    })}</div>
                    <div className="row g-3 mb-3">
                        <div className="col-sm-6 col-xl-3"><KpiCard icon="person-lines-fill" label="Customers owe you" value={<>{money(summary.balances.receivables)}{summary.balances.receivables_overdue > 0 && <small className="d-block fs-6 text-danger fw-normal">{money(summary.balances.receivables_overdue)} overdue</small>}</>} accent="var(--app-warning)" /></div>
                        <div className="col-sm-6 col-xl-3"><KpiCard icon="truck" label="You owe suppliers" value={<>{money(summary.balances.payables)}{summary.balances.payables_overdue > 0 && <small className="d-block fs-6 text-danger fw-normal">{money(summary.balances.payables_overdue)} overdue</small>}</>} accent="var(--chart-7)" /></div>
                        <div className="col-sm-6 col-xl-3"><KpiCard icon="boxes" label="Stock value (cost)" value={money(summary.balances.stock_value)} accent="var(--chart-6)" /></div>
                        <div className="col-sm-6 col-xl-3"><KpiCard icon="bank" label="Cash & bank (books)" value={summary.balances.cash === null ? 'Accounting off' : money(summary.balances.cash)} accent="var(--chart-1)" /></div>
                    </div>
                    <SectionCard icon="graph-up" title="Sales and expenses" accent="var(--chart-1)">
                        <Chart ariaLabel="Sales and expenses over time" height={300} option={{
                            tooltip: { trigger: 'axis' }, legend: { top: 0 }, grid: { left: 50, right: 16, top: 36, bottom: 30 },
                            xAxis: { type: 'category', data: summary.series.labels }, yAxis: { type: 'value' },
                            series: [{ name: 'POS', type: 'bar', stack: 's', data: summary.series.pos }, { name: 'B2B', type: 'bar', stack: 's', data: summary.series.b2b }, { name: 'Expenses', type: 'line', smooth: true, data: summary.series.expenses }],
                        }} />
                    </SectionCard>
                    <div className="row g-3">
                        <div className="col-lg-6"><SectionCard icon="trophy" title="Top items" accent="var(--chart-3)">
                            {summary.top_products.length === 0 ? <small className="text-muted">No sales in this period.</small> : <ul className="list-unstyled mb-0 d-grid gap-2">{summary.top_products.map((p) => <li key={p.id} className="d-flex"><DrawerLink href={route('products.show', p.id)} className="flex-grow-1" title={p.name}>{p.name}</DrawerLink><span className="tabular">{money(p.revenue)}</span></li>)}</ul>}
                        </SectionCard></div>
                        <div className="col-lg-6"><SectionCard icon="people" title="Top customers (B2B)" accent="var(--chart-4)">
                            {summary.top_customers.length === 0 ? <small className="text-muted">No B2B invoices in this period.</small> : <ul className="list-unstyled mb-0 d-grid gap-2">{summary.top_customers.map((c) => <li key={c.id} className="d-flex gap-2"><DrawerLink href={route('customers.show', c.id)} className="flex-grow-1" title={c.name}>{c.name}</DrawerLink>{c.open > 0 && <small className="text-warning">{money(c.open)} open</small>}<span className="tabular">{money(c.revenue)}</span></li>)}</ul>}
                        </SectionCard></div>
                    </div>
                </> : null}
            </Deferred>
        </>
    );
}
