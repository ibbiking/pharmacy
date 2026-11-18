import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import KpiCard from '@/Components/KpiCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import Modal from '@/Components/Modal';
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
        /** Made in the background by a POS sale / return / purchase. */
        origin: { label: string; href: string } | null;
    };
    allocations: { amount: number; payment: string | null; payment_id: number | null; date: string | null; credit: string | null; credit_id: number | null }[];
    actions: Action[];
    /** Shipping of a sales order / invoice (Wholesale); null = not shippable. */
    shipments: Shipment[] | null;
    canShip: boolean;
    side: 'selling' | 'buying';
    currency: string;
}

interface Shipment { id: number; number: string; carrier: string | null; tracking_no: string | null; packages: number; weight_kg: number | null; status: 'packed' | 'shipped' | 'delivered'; notes: string | null; shipped_at: string | null; delivered_at: string | null }
const SHIP_NEXT: Record<string, [string, string] | undefined> = { packed: ['shipped', 'Mark shipped'], shipped: ['delivered', 'Mark delivered'] };
const DONE_LABEL: Record<string, string> = { sales_order: 'Invoiced', purchase_order: 'Received', sales_invoice: 'Credited', bill: 'Returned' };

/** One B2B document: lines, totals, what settled it, related documents and the next steps. */
export default function DocumentShow({ document: d, allocations, actions, shipments, canShip, side, currency }: Props) {
    const [ship, setShip] = useState<{ open: boolean; carrier: string; tracking_no: string; packages: string; weight_kg: string; notes: string; shipped: boolean }>({ open: false, carrier: '', tracking_no: '', packages: '1', weight_kg: '', notes: '', shipped: false });
    const saveShipment = () => router.post(route('selling.shipments.store', d.id), {
        carrier: ship.carrier || null, tracking_no: ship.tracking_no || null, packages: Number(ship.packages) || 1, weight_kg: ship.weight_kg || null, notes: ship.notes || null, shipped: ship.shipped,
    }, { preserveScroll: true, onSuccess: () => setShip((s) => ({ ...s, open: false, carrier: '', tracking_no: '', packages: '1', weight_kg: '', notes: '', shipped: false })) });
    const [confirm, setConfirm] = useState<Action | null>(null);
    const [busy, setBusy] = useState(false);
    const money = (v: number) => formatMoney(v, currency);
    const sales = side === 'selling';
    const payable = d.type === 'sales_invoice' || d.type === 'bill';
    const doneLabel = DONE_LABEL[d.type];

    // Issuing an invoice / bill: was anything paid there and then? (none / in full / part)
    const [paid, setPaid] = useState<{ mode: 'none' | 'full' | 'part'; amount: string; method: string }>({ mode: 'none', amount: '', method: 'cash' });
    const issuing = confirm?.key === 'issue' && payable;
    const run = (a: Action) => {
        if (a.key === 'edit') return;
        const body = a.key === 'issue' && payable && paid.mode !== 'none'
            ? { paid_now: paid.mode === 'full' ? d.total : Number(paid.amount) || 0, method: paid.method } : {};
        router.post(route(`${side}.docs.action`, [d.id, a.key]), body, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setConfirm(null); } });
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

            {d.origin && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--chart-2)' }}><i className="bi bi-link-45deg" /><div>
                    <strong>Made from a {d.origin.label.toLowerCase()}</strong>
                    <p>It keeps track of what is owed. Change the <a href={d.origin.href}>{d.origin.label.toLowerCase()}</a> itself; here you can only record payments.</p>
                </div></div>
            )}
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
                    {shipments !== null && (
                        <SectionCard icon="truck" title="Shipping" accent="var(--chart-6)">
                            {shipments.length === 0 && <p className="small text-muted mb-2">Nothing shipped yet.</p>}
                            <ul className="list-unstyled mb-2">{shipments.map((s) => (
                                <li key={s.id} className="border-bottom py-2" style={{ borderColor: 'var(--app-border)' }}>
                                    <div className="d-flex align-items-center gap-2">
                                        <strong>{s.number}</strong>{statusPill(s.status)}
                                        <span className="flex-grow-1" />
                                        <a href={route('selling.shipments.print', s.id)} target="_blank" rel="noreferrer" className="btn btn-sm btn-light" title="Delivery note"><i className="bi bi-printer" /></a>
                                        {canShip && SHIP_NEXT[s.status] && <button type="button" className="btn btn-sm btn-primary" onClick={() => router.post(route('selling.shipments.advance', [s.id, SHIP_NEXT[s.status]![0]]), {}, { preserveScroll: true })}>{SHIP_NEXT[s.status]![1]}</button>}
                                        {canShip && s.status !== 'delivered' && <button type="button" className="btn btn-sm btn-outline-danger" aria-label={`Delete ${s.number}`} onClick={() => router.delete(route('selling.shipments.destroy', s.id), { preserveScroll: true })}><i className="bi bi-trash" /></button>}
                                    </div>
                                    <small className="text-muted d-block">{[s.carrier, s.tracking_no && `tracking ${s.tracking_no}`, `${s.packages} package${s.packages === 1 ? '' : 's'}`, s.weight_kg && `${s.weight_kg} kg`, s.shipped_at && `shipped ${s.shipped_at}`, s.delivered_at && `delivered ${s.delivered_at}`].filter(Boolean).join(' · ')}</small>
                                </li>
                            ))}</ul>
                            {canShip && !ship.open && <button type="button" className="btn btn-sm btn-light" onClick={() => setShip((s) => ({ ...s, open: true }))}><i className="bi bi-plus-lg me-1" />Add shipment</button>}
                            {canShip && ship.open && (
                                <div className="d-grid gap-2">
                                    <input className="form-control form-control-sm" placeholder="Carrier (e.g. TCS, own van)" aria-label="Carrier" value={ship.carrier} onChange={(e) => setShip((s) => ({ ...s, carrier: e.target.value }))} />
                                    <input className="form-control form-control-sm" placeholder="Tracking number" aria-label="Tracking number" value={ship.tracking_no} onChange={(e) => setShip((s) => ({ ...s, tracking_no: e.target.value }))} />
                                    <div className="d-flex gap-2">
                                        <input type="number" min={1} className="form-control form-control-sm" placeholder="Packages" aria-label="Packages" value={ship.packages} onChange={(e) => setShip((s) => ({ ...s, packages: e.target.value }))} />
                                        <input type="number" min={0} step="0.001" className="form-control form-control-sm" placeholder="Weight kg" aria-label="Weight in kg" value={ship.weight_kg} onChange={(e) => setShip((s) => ({ ...s, weight_kg: e.target.value }))} />
                                    </div>
                                    <textarea className="form-control form-control-sm" rows={2} placeholder="Notes for the driver / customer" aria-label="Notes" value={ship.notes} onChange={(e) => setShip((s) => ({ ...s, notes: e.target.value }))} />
                                    <div className="form-check"><input className="form-check-input" type="checkbox" id="ship-now" checked={ship.shipped} onChange={(e) => setShip((s) => ({ ...s, shipped: e.target.checked }))} /><label className="form-check-label small" htmlFor="ship-now">Already handed to the carrier (shipped)</label></div>
                                    <div className="d-flex gap-2 justify-content-end"><button type="button" className="btn btn-sm btn-light" onClick={() => setShip((s) => ({ ...s, open: false }))}>Cancel</button><button type="button" className="btn btn-sm btn-primary" onClick={saveShipment}>Save shipment</button></div>
                                </div>
                            )}
                        </SectionCard>
                    )}
                    {d.by && <p className="small text-muted">Created by {d.by}</p>}
                </div>
            </div>
            <Modal open={issuing} onClose={() => !busy && setConfirm(null)} icon="send-check" width={460} title={confirm?.label ?? ''}
                footer={<>
                    <button type="button" className="btn btn-light" onClick={() => setConfirm(null)} disabled={busy}>Back</button>
                    <button type="button" className="btn btn-primary" disabled={busy || (paid.mode === 'part' && !(Number(paid.amount) > 0 && Number(paid.amount) < d.total))} onClick={() => confirm && run(confirm)}>
                        {busy && <span className="spinner-border spinner-border-sm me-2" />}{confirm?.label}
                    </button>
                </>}>
                <p className="mb-3">{confirm?.confirm}</p>
                <fieldset>
                    <legend className="form-label fw-semibold small mb-1">{sales ? 'Money received now' : 'Paid to the supplier now'}</legend>
                    {([['none', sales ? 'Nothing yet (on credit)' : 'Nothing yet (on credit)'], ['full', `In full (${money(d.total)})`], ['part', 'Part of it']] as const).map(([v, t]) => (
                        <div key={v} className="form-check">
                            <input className="form-check-input" type="radio" name="paid-now" id={`paid-${v}`} checked={paid.mode === v} onChange={() => setPaid((p) => ({ ...p, mode: v }))} />
                            <label className="form-check-label" htmlFor={`paid-${v}`}>{t}</label>
                        </div>
                    ))}
                    {paid.mode !== 'none' && (
                        <div className="d-flex gap-2 mt-2">
                            {paid.mode === 'part' && <input type="number" min={0} step="0.01" className="form-control form-control-sm" placeholder="Amount" aria-label="Amount paid now" value={paid.amount} onChange={(e) => setPaid((p) => ({ ...p, amount: e.target.value }))} />}
                            <select className="form-select form-select-sm" aria-label="How it was paid" value={paid.method} onChange={(e) => setPaid((p) => ({ ...p, method: e.target.value }))}>
                                <option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option>
                            </select>
                        </div>
                    )}
                </fieldset>
            </Modal>
            <ConfirmDialog open={confirm !== null && !issuing} title={confirm?.label ?? ''} message={confirm?.confirm ?? ''} confirmLabel={confirm?.label} busy={busy}
                danger={confirm?.kind.includes('danger')} onClose={() => setConfirm(null)} onConfirm={() => confirm && run(confirm)} />
        </>
    );
}
