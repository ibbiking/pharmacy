import { Head, Link } from '@inertiajs/react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import EmptyState from '@/Components/EmptyState';
import RangeFilter, { type RangeFilters } from '@/Components/Reports/RangeFilter';
import { formatMoney, formatNumber } from '@/lib/format';

interface Row { return_no: string; invoice_no: string; date: string; lines: number; qty: number; refund: number; by: string | null; reason: string | null }
interface Props { filters: RangeFilters; kpis: { returns: number; refunds: number; qty: number }; rows: Paginated<Row>; currency: string }

export default function ReturnsReport({ filters, kpis, rows, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);

    return (
        <>
            <Head title="Returns report" />
            <PageHeader icon="arrow-return-left" title="Returns report" description="Every return in the period with the cash refunded, exactly as it was saved." />
            <RangeFilter routeName="reports.returns" filters={filters} />
            <div className="row g-3 mb-3">
                <div className="col-sm-4"><KpiCard icon="arrow-return-left" label="Returns" value={formatNumber(kpis.returns, 0)} accent="var(--app-warning)" /></div>
                <div className="col-sm-4"><KpiCard icon="cash" label="Cash refunded" value={money(kpis.refunds)} accent="var(--app-danger)" /></div>
                <div className="col-sm-4"><KpiCard icon="box-arrow-in-left" label="Quantity returned" value={formatNumber(kpis.qty, 2)} accent="var(--chart-3)" /></div>
            </div>
            <SectionCard icon="table" title="Returns" subtitle="Newest first" flush>
                {rows.data.length === 0 ? <EmptyState icon="arrow-return-left" title="No returns in this period" /> : <>
                    <div className="table-responsive">
                        <table className="table dt-table align-middle mb-0">
                            <thead><tr><th>Return</th><th>Invoice</th><th>Date</th><th className="text-end">Lines</th><th className="text-end">Refund</th><th /></tr></thead>
                            <tbody>{rows.data.map((r) => (
                                <tr key={r.return_no}>
                                    <td><Link href={route('returns.show', r.return_no)} className="fw-semibold">{r.return_no}</Link>{r.reason && <small className="d-block text-muted text-truncate" style={{ maxWidth: 260 }}>{r.reason}</small>}</td>
                                    <td><Link href={route('invoices.show', r.invoice_no)}>{r.invoice_no}</Link></td>
                                    <td className="text-muted text-nowrap">{r.date}{r.by && <small className="d-block">{r.by}</small>}</td>
                                    <td className="text-end tabular">{r.lines}</td>
                                    <td className="text-end tabular fw-semibold">{money(r.refund)}</td>
                                    <td className="text-end"><a href={route('returns.print', r.return_no)} target="_blank" rel="noreferrer" className="btn btn-icon btn-soft" aria-label={`Reprint ${r.return_no}`}><i className="bi bi-printer" /></a></td>
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                    {rows.last_page > 1 && <div className="d-flex justify-content-end gap-2 p-2">
                        {rows.prev_page_url && <Link href={rows.prev_page_url} preserveScroll className="btn btn-sm btn-light">Previous</Link>}
                        {rows.next_page_url && <Link href={rows.next_page_url} preserveScroll className="btn btn-sm btn-light">Next</Link>}
                    </div>}
                </>}
            </SectionCard>
        </>
    );
}
