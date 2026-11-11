import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import KpiCard from '@/Components/KpiCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { BackLink, DrawerLink } from '@/Components/Drawer';
import { statusPill } from '@/Components/Documents/status';
import { formatMoney, formatNumber } from '@/lib/format';

interface Line { id: number; product: string | null; description: string | null; unit: string | null; qty: number; unit_price: number; discount_percent: number; tax: string | null; tax_rate: number; tax_amount: number; net: number; total: number; done_qty: number }
interface Action { key: string; label: string; icon: string; kind: string; confirm: string | null }
interface Props {
    document: {
        id: number; type: string; label: string; number: string; status: string; raw_status: string; date: string; due_date: string | null; reference: string | null;
        party: { id: number; name: string; company: string | null; email: string | null; phone: string | null; address: string | null } | null;
        warehouse: string | null; notes: string | null; terms: string | null; by: string | null; subtotal: number; discount_total: number; tax_total: number; total: number; paid: number; balance: number; cost_total: number;
        source: { id: number; number: string; label: string } | null; children: { id: number; number: string; label: string; status: string }[]; lines: Line[];
    };
    allocations: { amount: number; payment: string | null; payment_id: number | null; date: string | null; credit: string | null; credit_id: number | null }[];
    actions: Action[];
    side: 'selling' | 'buying';
    currency: string;
}

const DONE_LABEL: Record<string, string> = { sales_order: 'Invoiced', purchase_order: 'Received', sales_invoice: 'Credited', bill: 'Returned' };

/** One B2B document: lines, totals, what settled it, related documents and the next steps. */
export default function DocumentShow({ document: d, allocations, actions, side, currency }: Props) {
    const [confirm, setConfirm] = useState<Action | null>(null);
    const [busy, setBusy] = useState(false);
    const money = (v: number) => formatMoney(v, currency);
    const sales = side === 'selling';
    const payable = d.type === 'sales_invoice' || d.type === 'bill';
    const doneLabel = DONE_LABEL[d.type];

    const run = (a: Action) => {
        if (a.key === 'edit') return;
        router.post(route(`${side}.docs.action`, [d.id, a.key]), {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setConfirm(null); } });
    };
    const button = (a: Action) => {
        const cls = `btn btn-${a.kind === 'primary' ? 'primary' : a.kind}`;
        if (a.key === 'edit') return <DrawerLink key={a.key} href={route(`${side}.docs.edit`, d.id)} className={cls} title={`Edit ${d.number}`}><i className={`bi bi-${a.icon} me-1`} />{a.label}</DrawerLink>;
        if (a.key === 'pay') return <DrawerLink key={a.key} href={route(`${side}.payments.create`, { document_id: d.id })} className={cls} title={sales ? 'Record payment' : 'Pay bill'}><i className={`bi bi-${a.icon} me-1`} />{a.label}</DrawerLink>;
        if (a.key === 'receive') return <DrawerLink key={a.key} href={route('buying.docs.receive-form', d.id)} className={cls} title={`Receive ${d.number}`}><i className={`bi bi-${a.icon} me-1`} />{a.label}</DrawerLink>;
        return <button key={a.key} type="button" className={cls} disabled={busy} onClick={() => (a.confirm ? setConfirm(a) : run(a))}><i className={`bi bi-${a.icon} me-1`} />{a.label}</button>;
    };

    return (
        <>
            <Head title={`${d.label} ${d.number}`} />
            <BackLink href={route(sales ? 'selling.invoices.index' : 'buying.orders.index')}>{sales ? 'Sales' : 'Purchasing'}</BackLink>
            <PageHeader icon="file-earmark-text" title={`${d.label} ${d.number}`} help={sales ? 'b2b-sales' : 'b2b-purchasing'}
                description={[d.party?.name, d.date, d.due_date && `${d.type === 'quotation' ? 'valid until' : 'due'} ${d.due_date}`, d.warehouse].filter(Boolean).join(' · ')}
                actions={<a href={route(`${side}.docs.print`, d.id)} target="_blank" rel="noreferrer" className="btn btn-light"><i className="bi bi-printer me-1" />Print / PDF</a>} />

            <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
                {statusPill(d.status)}
                {d.source && <span className="small text-muted">from <DrawerLink href={route(`${side}.docs.show`, d.source.id)} title={`${d.source.label} ${d.source.number}`}>{d.source.label} {d.source.number}</DrawerLink></span>}
                <span className="flex-grow-1" />
                {actions.map(button)}
            </div>

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Total" value={money(d.total)} accent="var(--chart-1)" /></div>
                {payable && <div className="col-sm-6 col-xl-3"><KpiCard icon="check2-circle" label={sales ? 'Received / credited' : 'Paid / credited'} value={money(d.paid)} accent="var(--app-success)" /></div>}
                {(payable || d.type.endsWith('credit') || d.type === 'credit_note') && <div className="col-sm-6 col-xl-3"><KpiCard icon="hourglass-split" label={payable ? 'Still open' : 'Credit left'} value={money(d.balance)} accent={d.balance > 0 ? 'var(--app-warning)' : 'var(--app-success)'} /></div>}
                {d.cost_total > 0 && <div className="col-sm-6 col-xl-3"><KpiCard icon="box-seam" label={d.type === 'bill' ? 'Received value' : 'Stock cost'} value={money(d.cost_total)} accent="var(--chart-6)" /></div>}
            </div>

            <div className="row g-3">
                <div className="col-xl-8">
                    <SectionCard icon="list-ul" title="Lines" flush>
                        <div className="table-responsive"><table className="table dt-table align-middle mb-0">
                            <thead><tr><th>Item</th><th className="text-end">Qty</th>{doneLabel && <th className="text-end">{doneLabel}</th>}<th className="text-end">Price</th><th className="text-end">Disc.</th><th className="text-end">Tax</th><th className="text-end">Amount</th></tr></thead>
                            <tbody>{d.lines.map((l) => (
                                <tr key={l.id}>
                                    <td><span className="fw-semibold">{l.product}</span>{l.description && <small className="d-block text-muted">{l.description}</small>}</td>
                                    <td className="text-end tabular text-nowrap">{formatNumber(l.qty, 2)} {l.unit}</td>
                                    {doneLabel && <td className="text-end tabular">{formatNumber(l.done_qty, 2)}</td>}
                                    <td className="text-end tabular">{money(l.unit_price)}</td>
                                    <td className="text-end tabular">{l.discount_percent > 0 ? `${formatNumber(l.discount_percent, 2)}%` : '—'}</td>
                                    <td className="text-end tabular">{l.tax_amount > 0 ? <>{money(l.tax_amount)}<small className="d-block text-muted">{l.tax}</small></> : '—'}</td>
                                    <td className="text-end tabular fw-semibold">{money(l.total)}</td>
                                </tr>
                            ))}</tbody>
                        </table></div>
                        <div className="doc-totals p-3">
                            <div><span>Subtotal</span><span className="tabular">{money(d.subtotal)}</span></div>
                            {d.discount_total > 0 && <div><span>Discount</span><span className="tabular">−{money(d.discount_total)}</span></div>}
                            <div><span>Tax</span><span className="tabular">{money(d.tax_total)}</span></div>
                            <div className="grand"><span>Total</span><span className="tabular">{money(d.total)}</span></div>
                        </div>
                    </SectionCard>
                    {(d.notes || d.terms) && <SectionCard icon="chat-left-text" title="Notes & terms">{d.notes && <p className="mb-2" style={{ whiteSpace: 'pre-line' }}>{d.notes}</p>}{d.terms && <p className="small text-muted mb-0" style={{ whiteSpace: 'pre-line' }}>{d.terms}</p>}</SectionCard>}
                </div>
                <div className="col-xl-4">
                    {d.party && (
                        <SectionCard icon={sales ? 'person-vcard' : 'truck'} title={sales ? 'Customer' : 'Supplier'} accent="var(--chart-4)">
                            <DrawerLink href={route(sales ? 'customers.show' : 'suppliers.show', d.party.id)} className="fw-semibold" title={d.party.name}>{d.party.name}</DrawerLink>
                            {d.party.company && <div className="small">{d.party.company}</div>}
                            <div className="small text-muted">{[d.party.phone, d.party.email].filter(Boolean).join(' · ')}</div>
                            {d.party.address && <div className="small text-muted">{d.party.address}</div>}
                        </SectionCard>
                    )}
                    {allocations.length > 0 && (
                        <SectionCard icon="cash-coin" title="Settled by" accent="var(--app-success)">
                            <ul className="list-unstyled mb-0 d-grid gap-2 small">{allocations.map((a, i) => (
                                <li key={i} className="d-flex gap-2">
                                    <span className="flex-grow-1">{a.payment_id ? <DrawerLink href={route(`${side}.payments.show`, a.payment_id)} title={`Payment ${a.payment}`}>{a.payment}</DrawerLink>
                                        : a.credit_id ? <DrawerLink href={route(`${side}.docs.show`, a.credit_id)} title={`Credit ${a.credit}`}>{a.credit}</DrawerLink> : '—'}<span className="text-muted ms-1">{a.date}</span></span>
                                    <span className="tabular">{money(a.amount)}</span>
                                </li>
                            ))}</ul>
                        </SectionCard>
                    )}
                    {d.children.length > 0 && (
                        <SectionCard icon="diagram-2" title="Related documents" accent="var(--chart-6)">
                            <ul className="list-unstyled mb-0 d-grid gap-2 small">{d.children.map((c) => (
                                <li key={c.id} className="d-flex align-items-center gap-2"><DrawerLink href={route(`${side}.docs.show`, c.id)} className="flex-grow-1" title={`${c.label} ${c.number}`}>{c.label} {c.number}</DrawerLink>{statusPill(c.status)}</li>
                            ))}</ul>
                        </SectionCard>
                    )}
                    {d.by && <p className="small text-muted">Created by {d.by}</p>}
                </div>
            </div>
            <ConfirmDialog open={confirm !== null} title={confirm?.label ?? ''} message={confirm?.confirm ?? ''} confirmLabel={confirm?.label} busy={busy}
                danger={confirm?.kind.includes('danger')} onClose={() => setConfirm(null)} onConfirm={() => confirm && run(confirm)} />
        </>
    );
}
