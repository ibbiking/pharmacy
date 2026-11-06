import { Head, Link, router } from '@inertiajs/react';
import axios, { type AxiosError } from 'axios';
import { Fragment, useEffect, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import Modal from '@/Components/Modal';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

interface Line {
    id: number; name: string; type: string | null; unit: string | null; qty: number; returned: number; left: number; price: number;
    system_price: number | null; overridden: boolean; discount: number; row_total: number; lot_mode: string | null;
    lots: { batch: string | null; expiry: string | null; qty: number }[];
}
interface Props {
    invoice: { id: number; invoice_no: string; date: string | null; subtotal: number; line_discounts: number; net: number; invoice_discount: number; tax: number; included_tax: number | null; grand_total: number; cash: number; change: number; fully_returned: boolean };
    lines: Line[];
    taxes: { name: string; rate: number; amount: number; kind: 'gst' | 'additive' | 'included' }[];
    returns: { return_no: string; date: string; refund: number }[];
    history: { action: string; description: string; by: string | null; at: string | null }[];
    currency: string;
    canReturn: boolean;
}
interface Preview { lines: { item_id: number; qty: number; refund: number; gross: number; line_discount: number; invoice_discount: number; additive_tax: number }[]; refund: number; errors: Record<string, string> }

export default function InvoiceShow({ invoice, lines, taxes, returns, history, currency, canReturn }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const [dialog, setDialog] = useState(false);
    const [qty, setQty] = useState<Record<number, string>>({});
    const [reason, setReason] = useState('');
    const [preview, setPreview] = useState<Preview | null>(null);
    const [busy, setBusy] = useState(false);
    const open = (only?: Line) => { setQty(Object.fromEntries(lines.map((l) => [l.id, only ? (l.id === only.id ? String(l.left) : '') : String(l.left || '')]))); setReason(''); setPreview(null); setDialog(true); };
    const wanted = Object.fromEntries(Object.entries(qty).filter(([, v]) => Number(v) > 0).map(([k, v]) => [k, Number(v)]));
    const any = Object.keys(wanted).length > 0;

    useEffect(() => {
        if (!dialog || !any) { setPreview(null); return; }
        const t = window.setTimeout(() => axios.post<Preview>(route('invoices.return-preview', invoice.invoice_no), { qty: wanted })
            .then(({ data }) => setPreview(data)).catch(() => toast('error', 'Couldn’t work out the refund. Try again.')), 250);
        return () => window.clearTimeout(t);
    }, [qty, dialog]); // eslint-disable-line react-hooks/exhaustive-deps

    const submit = () => {
        setBusy(true);
        const win = window.open('', 'return-receipt', 'width=420,height=680'); // opened in the click so it isn't blocked
        axios.post(route('invoices.return', invoice.invoice_no), { qty: wanted, reason: reason || null })
            .then(({ data }) => {
                if (win) win.location.href = route('returns.print', data.return_no);
                toast('success', data.message);
                setDialog(false);
                router.reload();
            })
            .catch((e: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
                win?.close();
                const d = e.response?.data;
                toast('error', (d?.errors && Object.values(d.errors)[0]?.[0]) ?? d?.message ?? 'The return could not be saved.');
            })
            .finally(() => setBusy(false));
    };

    const additive = taxes.filter((t) => t.kind === 'additive');
    const gst = taxes.filter((t) => t.kind === 'gst');
    const included = taxes.filter((t) => t.kind === 'included');
    const anyLeft = lines.some((l) => l.left > 0);

    return (
        <>
            <Head title={invoice.invoice_no} />
            <div className="mb-2"><Link href={route('invoices.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />All invoices</Link></div>
            <PageHeader icon="receipt" title={`Invoice ${invoice.invoice_no}`} description={invoice.date ?? undefined}
                actions={<>
                    <a href={route('invoices.print', invoice.invoice_no)} target="_blank" rel="noreferrer" className="btn btn-light"><i className="bi bi-printer me-1" />Reprint</a>
                    {canReturn && anyLeft && <button type="button" className="btn btn-primary" onClick={() => open()}><i className="bi bi-arrow-return-left me-1" />Return items</button>}
                </>} />
            {invoice.fully_returned && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--chart-8)' }}><i className="bi bi-x-circle" /><div><strong>Fully returned</strong><p>Everything on this invoice came back; the stock went back into the batches it was sold from.</p></div></div>}

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Total now" value={money(invoice.grand_total)} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="wallet2" label="Cash received" value={money(invoice.cash)} accent="var(--app-success)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="arrow-return-left" label="Returns" value={returns.length ? `${returns.length} · ${money(returns.reduce((s, r) => s + r.refund, 0))}` : 'None'} accent="var(--app-warning)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="percent" label="Discounts" value={money(invoice.line_discounts + invoice.invoice_discount)} accent="var(--chart-5)" /></div>
            </div>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="list-ul" title="Lines" subtitle="Amounts after any returns" flush>
                        <div className="table-responsive">
                            <table className="table dt-table align-middle mb-0">
                                <thead><tr><th>Item</th><th className="text-end">Qty</th><th className="text-end">Price</th><th className="text-end">Discount</th><th className="text-end">Line total</th>{canReturn && <th />}</tr></thead>
                                <tbody>{lines.map((l) => (
                                    <tr key={l.id}>
                                        <td>
                                            <span className="fw-semibold">{l.name}</span>
                                            <small className="d-block text-muted">{[l.unit, l.type, l.lot_mode?.toUpperCase()].filter(Boolean).join(' · ')}</small>
                                            <div className="d-flex flex-wrap gap-1 mt-1">{l.lots.map((b, k) => <span key={k} className="expiry-chip" style={{ ['--accent' as string]: 'var(--chart-8)' }}>{b.batch ?? 'Batch'}{b.expiry && ` · exp ${b.expiry}`}</span>)}</div>
                                        </td>
                                        <td className="text-end tabular">{formatNumber(l.qty, 2)}{l.returned > 0 && <small className="d-block text-warning">{formatNumber(l.returned, 2)} returned</small>}</td>
                                        <td className="text-end tabular">{money(l.price)}{l.overridden && l.system_price !== null && <small className="d-block text-muted" title="Price changed at the till">system {money(l.system_price)}</small>}</td>
                                        <td className="text-end tabular">{l.discount > 0 ? `−${money(l.discount)}` : '—'}</td>
                                        <td className="text-end tabular fw-semibold">{money(l.row_total)}</td>
                                        {canReturn && <td className="text-end">{l.left > 0 && <button type="button" className="btn btn-sm btn-light" onClick={() => open(l)}>Return</button>}</td>}
                                    </tr>
                                ))}</tbody>
                            </table>
                        </div>
                        <div className="p-3 border-top">
                            <dl className="row small mb-0 ms-auto" style={{ maxWidth: 360 }}>
                                <dt className="col-7 fw-normal text-muted">Subtotal</dt><dd className="col-5 text-end tabular">{money(invoice.subtotal)}</dd>
                                {invoice.line_discounts > 0 && <><dt className="col-7 fw-normal text-muted">Line discounts</dt><dd className="col-5 text-end tabular">−{money(invoice.line_discounts)}</dd></>}
                                {invoice.invoice_discount > 0 && <><dt className="col-7 fw-normal text-muted">Invoice discount</dt><dd className="col-5 text-end tabular">−{money(invoice.invoice_discount)}</dd></>}
                                {additive.map((t) => <Fragment key={`a${t.name}`}><dt className="col-7 fw-normal text-muted">{t.name} ({formatNumber(t.rate, 2)}%)</dt><dd className="col-5 text-end tabular">+{money(t.amount)}</dd></Fragment>)}
                                <dt className="col-7">Total</dt><dd className="col-5 text-end tabular fw-bold">{money(invoice.grand_total)}</dd>
                                {gst.map((t) => <Fragment key={`g${t.name}`}><dt className="col-7 fw-normal text-muted">{t.name} {formatNumber(t.rate, 2)}% (inside the price)</dt><dd className="col-5 text-end tabular text-muted">{money(t.amount)}</dd></Fragment>)}
                                {included.map((t) => <Fragment key={`i${t.name}`}><dt className="col-7 fw-normal text-muted" title="Owner only">{t.name} {formatNumber(t.rate, 2)}% inside prices <i className="bi bi-eye-slash" /></dt><dd className="col-5 text-end tabular text-muted">{money(t.amount)}</dd></Fragment>)}
                            </dl>
                        </div>
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    {returns.length > 0 && (
                        <SectionCard icon="arrow-return-left" title="Returns" accent="var(--app-warning)">
                            <ul className="list-unstyled mb-0 d-grid gap-2">
                                {returns.map((r) => (
                                    <li key={r.return_no} className="d-flex align-items-center gap-2">
                                        <Link href={route('returns.show', r.return_no)} className="fw-semibold">{r.return_no}</Link>
                                        <small className="text-muted flex-grow-1">{r.date}</small>
                                        <span className="tabular small">{money(r.refund)}</span>
                                    </li>
                                ))}
                            </ul>
                        </SectionCard>
                    )}
                    <SectionCard icon="clock-history" title="History" accent="var(--chart-6)">
                        <ul className="list-unstyled small mb-0 d-grid gap-2">
                            {history.map((h, i) => <li key={i}><strong>{h.action}</strong> <span className="text-muted">· {h.at}{h.by && ` · ${h.by}`}</span><div className="text-muted">{h.description}</div></li>)}
                        </ul>
                    </SectionCard>
                </div>
            </div>

            <Modal open={dialog} onClose={() => !busy && setDialog(false)} icon="arrow-return-left" width={640} title={`Return from ${invoice.invoice_no}`}
                footer={<>
                    <button type="button" className="btn btn-light" onClick={() => setDialog(false)} disabled={busy}>Cancel</button>
                    <button type="button" className="btn btn-primary" onClick={submit} disabled={busy || !any || !preview || Object.keys(preview.errors).length > 0}>
                        {busy && <span className="spinner-border spinner-border-sm me-2" />}Return & print{preview && ` · refund ${money(preview.refund)}`}
                    </button>
                </>}>
                <div className="d-grid gap-2">
                    {lines.filter((l) => l.left > 0).map((l) => {
                        const p = preview?.lines.find((x) => x.item_id === l.id);
                        const e = preview?.errors[`qty.${l.id}`];
                        return (
                            <div key={l.id} className="d-flex align-items-center gap-2">
                                <div className="flex-grow-1 min-w-0"><strong className="d-block text-truncate">{l.name}</strong><small className="text-muted">{formatNumber(l.left, 2)} {l.unit ?? ''} left to return</small>{e && <small className="d-block text-danger">{e}</small>}</div>
                                <input type="number" min={0} max={l.left} step="any" className={`form-control form-control-sm ${e ? 'is-invalid' : ''}`} style={{ width: 100 }} aria-label={`Return quantity of ${l.name}`}
                                    value={qty[l.id] ?? ''} onChange={(ev) => setQty((q) => ({ ...q, [l.id]: ev.target.value }))} />
                                <span className="tabular small text-end" style={{ width: 90 }}>{p ? money(p.refund) : '—'}</span>
                            </div>
                        );
                    })}
                    <input className="form-control form-control-sm mt-2" maxLength={255} placeholder="Reason (optional)" value={reason} onChange={(e) => setReason(e.target.value)} aria-label="Reason" />
                    {preview && preview.lines.some((x) => x.invoice_discount > 0) && <small className="text-muted">The refund takes off this return’s share of the invoice discount the customer got.</small>}
                    <small className="text-muted">Stock goes back into the batches it was sold from.</small>
                </div>
            </Modal>
        </>
    );
}
