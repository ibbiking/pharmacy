import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

type Row = { id: number | null; code: string; name: string; amount: number };
interface Props { report: 'pl' | 'bs' | 'tb' | 'tax'; filters: { from: string; to: string; report: string }; data: Record<string, unknown>; currency: string }
const TABS = [['pl', 'Profit & loss', 'graph-up-arrow'], ['bs', 'Balance sheet', 'bank'], ['tb', 'Trial balance', 'list-columns'], ['tax', 'Tax summary', 'percent']] as const;

/** Financial statements from the books. */
export default function Reports({ report, filters, data, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const go = (patch: Record<string, unknown>) => router.get(route('accounting.reports'), { ...filters, ...patch }, { preserveState: true, replace: true });
    const section = (title: string, rows: Row[], total: number, accent: string): ReactNode => (
        <SectionCard icon="dot" title={title} accent={accent} flush>
            <table className="table dt-table mb-0"><tbody>
                {rows.map((r, i) => <tr key={i}><td>{r.id ? <DrawerLink href={route('accounting.ledger', { id: r.id, from: filters.from, to: filters.to })} title={r.name}>{r.code} · {r.name}</DrawerLink> : r.name}</td><td className="text-end tabular">{money(r.amount)}</td></tr>)}
                {rows.length === 0 && <tr><td className="text-muted">Nothing</td><td /></tr>}
            </tbody><tfoot><tr className="fw-bold"><td>Total {title.toLowerCase()}</td><td className="text-end tabular">{money(total)}</td></tr></tfoot></table>
        </SectionCard>
    );
    const sum = (rows: Row[]) => rows.reduce((t, r) => t + r.amount, 0);
    const d = data as Record<string, never>;

    return (
        <>
            <Head title="Financial reports" />
            <PageHeader icon="clipboard-data" title="Financial reports" help="accounting" description="Built from the journals: every sale, purchase, payment and expense." />
            <div className="d-flex flex-wrap gap-2 align-items-center mb-3">
                <div className="btn-group" role="tablist">{TABS.map(([k, label, icon]) => <button key={k} type="button" role="tab" aria-selected={report === k} className={`btn btn-sm ${report === k ? 'btn-primary' : 'btn-light'}`} onClick={() => go({ report: k })}><i className={`bi bi-${icon} me-1`} />{label}</button>)}</div>
                <span className="flex-grow-1" />
                {report !== 'bs' && report !== 'tb' && <><label className="small fw-semibold" htmlFor="r-from">From</label><input id="r-from" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.from} onChange={(e) => go({ from: e.target.value })} /></>}
                <label className="small fw-semibold" htmlFor="r-to">{report === 'bs' || report === 'tb' ? 'As of' : 'To'}</label><input id="r-to" type="date" className="form-control form-control-sm" style={{ width: 160 }} value={filters.to} onChange={(e) => go({ to: e.target.value })} />
            </div>
            {report === 'pl' && (
                <>
                    <div className="row g-3 mb-3">
                        <div className="col-sm-4"><KpiCard icon="graph-up" label="Income" value={money(d.income_total)} accent="var(--app-success)" /></div>
                        <div className="col-sm-4"><KpiCard icon="box-seam" label="Gross profit" value={money(d.gross)} accent="var(--chart-1)" /></div>
                        <div className="col-sm-4"><KpiCard icon="trophy" label="Net profit" value={money(d.net)} accent={Number(d.net) >= 0 ? 'var(--app-success)' : 'var(--app-danger)'} /></div>
                    </div>
                    {section('Income', d.income, d.income_total, 'var(--app-success)')}
                    {section('Cost of sales', d.cos, d.cos_total, 'var(--app-warning)')}
                    {section('Expenses', d.expenses, d.expenses_total, 'var(--app-danger)')}
                    {sum(d.otherIncome) !== 0 && section('Other income', d.otherIncome, sum(d.otherIncome), 'var(--chart-3)')}
                    {sum(d.otherExpenses) !== 0 && section('Other expenses', d.otherExpenses, sum(d.otherExpenses), 'var(--chart-5)')}
                </>
            )}
            {report === 'bs' && (
                <>
                    <div className={`callout mb-3`} style={{ ['--accent' as string]: Math.abs(Number(d.assets_total) - Number(d.liabilities_total) - Number(d.equity_total)) < 0.01 ? 'var(--app-success)' : 'var(--app-danger)' }}>
                        <i className="bi bi-scales" /><div><strong>Assets {money(d.assets_total)} = Liabilities {money(d.liabilities_total)} + Equity {money(d.equity_total)}</strong></div></div>
                    <div className="row g-3"><div className="col-lg-6">{section('Assets', d.assets, d.assets_total, 'var(--chart-1)')}</div>
                        <div className="col-lg-6">{section('Liabilities', d.liabilities, d.liabilities_total, 'var(--app-warning)')}{section('Equity', d.equity, d.equity_total, 'var(--chart-6)')}</div></div>
                </>
            )}
            {report === 'tb' && (
                <SectionCard icon="list-columns" title="Trial balance" flush>
                    <table className="table dt-table mb-0"><thead><tr><th>Account</th><th className="text-end">Debit</th><th className="text-end">Credit</th></tr></thead>
                        <tbody>{(d.rows as unknown as { id: number; code: string; name: string; debit: number; credit: number }[]).map((r) => <tr key={r.id}><td><DrawerLink href={route('accounting.ledger', r.id)} title={r.name}>{r.code} · {r.name}</DrawerLink></td><td className="text-end tabular">{r.debit ? money(r.debit) : ''}</td><td className="text-end tabular">{r.credit ? money(r.credit) : ''}</td></tr>)}</tbody>
                        <tfoot><tr className="fw-bold"><td>Total</td><td className="text-end tabular">{money(d.debit)}</td><td className="text-end tabular">{money(d.credit)}</td></tr></tfoot></table>
                </SectionCard>
            )}
            {report === 'tax' && (
                <div className="row g-3">
                    <div className="col-sm-4"><KpiCard icon="arrow-up-right" label="Tax collected on sales" value={money(d.collected)} accent="var(--app-warning)" /></div>
                    <div className="col-sm-4"><KpiCard icon="arrow-down-left" label="Tax paid on purchases & expenses" value={money(d.paid)} accent="var(--chart-1)" /></div>
                    <div className="col-sm-4"><KpiCard icon="bank" label={Number(d.net) >= 0 ? 'Payable to the tax office' : 'Refundable'} value={money(Math.abs(Number(d.net)))} accent="var(--app-danger)" /></div>
                </div>
            )}
        </>
    );
}
