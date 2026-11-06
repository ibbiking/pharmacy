import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { formatMoney, formatNumber } from '@/lib/format';

interface Props {
    return_no: string;
    invoice: { invoice_no: string | null; id: number | null; date: string | null };
    date: string | null;
    by: string | null;
    reason: string | null;
    lines: { name: string; type: string | null; unit: string | null; qty: number; price: number; gross: number; line_discount: number; invoice_discount: number; refund: number }[];
    taxes: { name: string; rate: number; amount: number; kind: 'gst' | 'additive' | 'included' }[];
    refund: number;
    legacy: boolean;
    currency: string;
}

/** One return, exactly as it was saved (snapshot), with a reprint. */
export default function ReturnShow({ return_no, invoice, date, by, reason, lines, taxes, refund, legacy, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);

    return (
        <>
            <Head title={`Return ${return_no}`} />
            {invoice.invoice_no && <div className="mb-2"><Link href={route('invoices.show', invoice.invoice_no)} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />Invoice {invoice.invoice_no}</Link></div>}
            <PageHeader icon="arrow-return-left" title={`Return ${return_no}`} description={[date, by && `by ${by}`].filter(Boolean).join(' · ') || undefined}
                actions={<a href={route('returns.print', return_no)} target="_blank" rel="noreferrer" className="btn btn-primary"><i className="bi bi-printer me-1" />Reprint</a>} />
            {legacy && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-info-circle" /><div><strong>Older return</strong><p>Made before returns kept their own tax record, so the refunded tax isn’t listed separately.</p></div></div>}
            <SectionCard icon="list-ul" title="Returned" subtitle={reason ? `Reason: ${reason}` : undefined} flush>
                <div className="table-responsive">
                    <table className="table dt-table align-middle mb-0">
                        <thead><tr><th>Item</th><th className="text-end">Qty</th><th className="text-end">Price</th><th className="text-end">Discounts back</th><th className="text-end">Refund</th></tr></thead>
                        <tbody>{lines.map((l, i) => (
                            <tr key={i}>
                                <td><span className="fw-semibold">{l.name}</span><small className="d-block text-muted">{[l.unit, l.type].filter(Boolean).join(' · ')}</small></td>
                                <td className="text-end tabular">{formatNumber(l.qty, 2)}</td>
                                <td className="text-end tabular">{money(l.price)}</td>
                                <td className="text-end tabular">{l.line_discount + l.invoice_discount > 0 ? `−${money(l.line_discount + l.invoice_discount)}` : '—'}</td>
                                <td className="text-end tabular fw-semibold">{money(l.refund)}</td>
                            </tr>
                        ))}</tbody>
                    </table>
                </div>
                <div className="p-3 border-top d-flex flex-wrap gap-4 justify-content-end small">
                    {taxes.filter((t) => t.kind === 'additive').map((t) => <span key={t.name}>{t.name} refunded: <strong className="tabular">{money(t.amount)}</strong></span>)}
                    {taxes.filter((t) => t.kind === 'gst').map((t) => <span key={t.name} className="text-muted">{t.name} inside the price: {money(t.amount)}</span>)}
                    <span className="fs-6">Cash refund: <strong className="tabular">{money(refund)}</strong></span>
                </div>
            </SectionCard>
        </>
    );
}
