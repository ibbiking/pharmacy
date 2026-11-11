import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import LineEditor, { blankLine, lineAmounts, linePayload, type EditorLine } from '@/Components/LineEditor';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';
import { formatMoney } from '@/lib/format';
import type { DocMeta } from './Index';

interface DocLine extends Omit<EditorLine, 'key'> { source_line_id: number | null }
interface Props {
    type: string;
    meta: DocMeta;
    document: null | { id: number; number: string; date: string; due_date: string | null; reference: string | null; notes: string | null; terms: string | null; source_id: number | null; source: string | null; party: Option | null; lines: DocLine[] };
    party: Option | null;
    warehouse: Option | null;
    inventoryPro: boolean;
    canChangePrice: boolean;
    today: string;
    currency: string;
}

/** Draft of any B2B document. Totals here are a preview: the server prices, taxes and totals every line on save. */
export default function DocumentForm({ type, meta, document: doc, party: presetParty, warehouse: presetWarehouse, inventoryPro, canChangePrice, today, currency }: Props) {
    const { errors } = usePage().props;
    const sales = meta.side === 'sales';
    const fromSource = !!doc?.source_id;
    const fixed = type === 'credit_note' || type === 'vendor_credit';
    const [party, setParty] = useState<Option | null>(doc?.party ?? presetParty);
    const [warehouse, setWarehouse] = useState<Option | null>(presetWarehouse);
    const [date, setDate] = useState(doc?.date ?? today);
    const [due, setDue] = useState(doc?.due_date ?? '');
    const [reference, setReference] = useState(doc?.reference ?? '');
    const [notes, setNotes] = useState(doc?.notes ?? '');
    const [terms, setTerms] = useState(doc?.terms ?? '');
    const [lines, setLines] = useState<(EditorLine & { source_line_id?: number | null })[]>(() => doc?.lines.length ? doc.lines.map((l) => ({ ...blankLine(), ...l })) : [blankLine()]);
    const [busy, setBusy] = useState(false);
    const totals = useMemo(() => lines.reduce((t, l) => { const a = lineAmounts(l); return { net: t.net + a.net, tax: t.tax + a.tax, discount: t.discount + a.discount, total: t.total + a.total }; }, { net: 0, tax: 0, discount: 0, total: 0 }), [lines]);
    const money = (v: number) => formatMoney(v, currency);
    const showWarehouse = inventoryPro && ['sales_invoice', 'purchase_order', 'vendor_credit', 'sales_order', 'quotation'].includes(type);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = {
            [sales ? 'customer_id' : 'supplier_id']: party?.value ?? null, warehouse_id: warehouse?.value ?? null, date, due_date: due || null,
            reference: reference || null, notes: notes || null, terms: terms || null, source_id: doc?.source_id ?? null,
            lines: lines.map((l) => ({ ...linePayload(l), source_line_id: l.source_line_id ?? null })),
        };
        const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Some fields need attention: see the red messages.') };
        if (doc) router.put(route(`${meta.side_route}.docs.update`, doc.id), payload, opts);
        else router.post(route(`${meta.side_route}.${meta.slug}.store`), payload, opts);
    };
    const fb = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;

    return (
        <>
            <Head title={doc ? `${meta.label} ${doc.number}` : `New ${meta.label.toLowerCase()}`} />
            <BackLink href={route(`${meta.side_route}.${meta.slug}.index`)}>All {meta.plural.toLowerCase()}</BackLink>
            <PageHeader icon={meta.icon} title={doc ? `${meta.label} ${doc.number}` : `New ${meta.label.toLowerCase()}`} help={sales ? 'b2b-sales' : 'b2b-purchasing'}
                description={doc?.source ? `From ${doc.source}. Change quantities if needed, then save the draft.` : 'Saved as a draft. Issue it from the document when it is ready.'} />
            {errors.document && <div className="alert alert-danger">{errors.document}</div>}
            <form onSubmit={submit} noValidate>
                <SectionCard icon={sales ? 'person-vcard' : 'truck'} title="Details" accent={meta.accent}>
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label fw-semibold small" htmlFor="doc-party">{sales ? 'Customer' : 'Supplier'}</label>
                            <AsyncSelect entity={sales ? 'customers' : 'suppliers'} inputId="doc-party" value={party} onChange={setParty} isDisabled={fromSource} invalid={!!(errors.customer_id || errors.supplier_id)} />
                            {fb(sales ? 'customer_id' : 'supplier_id')}
                        </div>
                        {showWarehouse && (
                            <div className="col-md-6">
                                <label className="form-label fw-semibold small" htmlFor="doc-wh">{sales ? 'Ship from warehouse' : 'Deliver to warehouse'}</label>
                                <AsyncSelect entity="warehouses" inputId="doc-wh" value={warehouse} onChange={setWarehouse} isClearable={false} invalid={!!errors.warehouse_id} />
                                {fb('warehouse_id')}
                            </div>
                        )}
                        <div className="col-6 col-md-3">
                            <label className="form-label fw-semibold small" htmlFor="doc-date">Date</label>
                            <input id="doc-date" type="date" className={`form-control ${errors.date ? 'is-invalid' : ''}`} value={date} onChange={(e) => setDate(e.target.value)} />
                            {fb('date')}
                        </div>
                        <div className="col-6 col-md-3">
                            <label className="form-label fw-semibold small" htmlFor="doc-due">{type === 'quotation' ? 'Valid until' : 'Due date'} <span className="text-muted fw-normal">Optional</span></label>
                            <input id="doc-due" type="date" className={`form-control ${errors.due_date ? 'is-invalid' : ''}`} value={due} onChange={(e) => setDue(e.target.value)} />
                            {fb('due_date')}
                            {!due && ['sales_invoice', 'bill'].includes(type) && <small className="text-muted">From the payment terms</small>}
                        </div>
                        <div className="col-md-6">
                            <label className="form-label fw-semibold small" htmlFor="doc-ref">Reference <span className="text-muted fw-normal">Optional</span></label>
                            <input id="doc-ref" className="form-control" maxLength={100} value={reference} onChange={(e) => setReference(e.target.value)} placeholder={sales ? 'Customer PO number' : 'Supplier quote / invoice number'} />
                        </div>
                    </div>
                </SectionCard>
                <SectionCard icon="list-ul" title="Lines" accent="var(--chart-1)">
                    {errors.lines && <div className="alert alert-danger py-2">{errors.lines}</div>}
                    {fixed && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-info-circle" /><div><p className="mb-0">Prices come from {doc?.source}. Lower a quantity or remove a line to credit only part of it.</p></div></div>}
                    <LineEditor lines={lines} onChange={setLines} mode={sales ? 'sale' : 'purchase'} errors={errors} currency={currency} warehouseId={warehouse?.value ?? null}
                        allowPrice={canChangePrice && !fixed} showStock={sales} lockItems={fromSource} />
                    <div className="doc-totals mt-3">
                        <div><span>Before tax</span><span className="tabular">{money(totals.net)}</span></div>
                        {totals.discount > 0 && <div className="text-muted"><span>incl. discounts</span><span className="tabular">−{money(totals.discount)}</span></div>}
                        <div><span>Tax</span><span className="tabular">{money(totals.tax)}</span></div>
                        <div className="grand"><span>Total</span><span className="tabular">{money(totals.total)}</span></div>
                    </div>
                </SectionCard>
                <SectionCard icon="chat-left-text" title="Notes & terms" accent="var(--chart-6)">
                    <div className="row g-3">
                        <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="doc-notes">Notes (printed)</label><textarea id="doc-notes" className="form-control" rows={3} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} /></div>
                        <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="doc-terms">Terms (printed)</label><textarea id="doc-terms" className="form-control" rows={3} maxLength={1000} value={terms} onChange={(e) => setTerms(e.target.value)} placeholder="e.g. Goods once sold are returnable within 7 days." /></div>
                    </div>
                </SectionCard>
                <div className="d-flex justify-content-end gap-2">
                    <CancelLink href={doc ? route(`${meta.side_route}.docs.show`, doc.id) : route(`${meta.side_route}.${meta.slug}.index`)} />
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save draft'}</button>
                </div>
            </form>
        </>
    );
}
