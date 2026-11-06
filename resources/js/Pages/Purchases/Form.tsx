import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { LookupPicker, toRef, type Option } from '@/Components/AsyncSelect';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

interface Unit { value: number; label: string; base_units: number; unit_cost_price: number; paid_unit_cost_price: number; unit_sale_price: number }
interface Info { id: number; name: string; base_unit: string | null; units: Unit[]; last: { category_id: number; supplier: string | null; date: string | null } | null }
interface EditLine {
    product: Option; category_id: number; quantity: number; unit_cost_price: number; paid_unit_cost_price: number; unit_sale_price: number;
    batch_no: string | null; manufacturing_date: string | null; expiry_date: string | null; purchase_taxes: Option[]; sale_taxes: Option[];
}
interface Props {
    purchase: null | { id: number; supplier: Option | null; invoice_no: string | null; line: EditLine; sold: number; locked: boolean };
    preset: Option | null;
    currency: string;
}
interface Line {
    key: string; product: Option[]; category_id: number | null; quantity: string; unit_cost_price: string; paid_unit_cost_price: string;
    unit_sale_price: string; batch_no: string; manufacturing_date: string; expiry_date: string; purchase_taxes: Option[]; sale_taxes: Option[];
}

let seq = 0;
const blank = (product: Option | null = null): Line => ({
    key: `l${++seq}`, product: product ? [product] : [], category_id: null, quantity: '1', unit_cost_price: '', paid_unit_cost_price: '',
    unit_sale_price: '', batch_no: '', manufacturing_date: '', expiry_date: '', purchase_taxes: [], sale_taxes: [],
});
const fromEdit = (l: EditLine): Line => ({
    key: `l${++seq}`, product: [l.product], category_id: l.category_id, quantity: String(l.quantity), unit_cost_price: String(l.unit_cost_price),
    paid_unit_cost_price: l.paid_unit_cost_price && l.paid_unit_cost_price !== l.unit_cost_price ? String(l.paid_unit_cost_price) : '',
    unit_sale_price: String(l.unit_sale_price), batch_no: l.batch_no ?? '', manufacturing_date: l.manufacturing_date ?? '', expiry_date: l.expiry_date ?? '',
    purchase_taxes: l.purchase_taxes, sale_taxes: l.sale_taxes,
});
const num = (v: string) => (v.trim() === '' ? NaN : Number(v));
const rateSum = (o: Option[]) => o.reduce((s, t) => s + Number(t.meta?.rate ?? 0), 0);
const daysUntil = (iso: string) => Math.round((new Date(`${iso}T00:00:00`).getTime() - new Date(new Date().toDateString()).getTime()) / 86400000);

/** Live preview of one line, mirroring PurchaseService (the server recomputes everything on save). */
function preview(l: Line, unit: Unit | undefined) {
    const qty = num(l.quantity), cost = num(l.unit_cost_price), sale = num(l.unit_sale_price);
    const paid = l.paid_unit_cost_price.trim() === '' ? cost : num(l.paid_unit_cost_price);
    const ok = qty > 0 && cost > 0;
    return {
        baseQty: unit && qty > 0 ? qty * unit.base_units : null,
        totalPaid: ok && paid >= 0 ? paid * qty : 0,
        gstPercent: cost > 0 && paid > cost ? ((paid - cost) / cost) * 100 : 0,
        purchaseTax: ok ? cost * qty * rateSum(l.purchase_taxes) / 100 : 0,
        saleTaxUnit: sale > 0 ? sale * rateSum(l.sale_taxes) / 100 : 0,
        margin: paid > 0 && sale > 0 ? ((sale - paid) / paid) * 100 : null,
    };
}

export default function PurchaseForm({ purchase, preset, currency }: Props) {
    const { errors, wording } = usePage().props;
    const isEdit = purchase !== null;
    const [supplier, setSupplier] = useState<Option[]>(purchase?.supplier ? [purchase.supplier] : []);
    const [invoiceNo, setInvoiceNo] = useState(purchase?.invoice_no ?? '');
    const [lines, setLines] = useState<Line[]>(() => (purchase ? [fromEdit(purchase.line)] : [blank(preset)]));
    const [infos, setInfos] = useState<Record<number, Info>>({});
    const loading = useRef<Set<number>>(new Set());
    const [busy, setBusy] = useState(false);
    const dirty = useRef(false);
    const money = (v: number) => formatMoney(v, currency);

    const loadInfo = useCallback((productId: number, onLoaded?: (info: Info) => void) => {
        if (infos[productId]) { onLoaded?.(infos[productId]!); return; }
        if (loading.current.has(productId)) return;
        loading.current.add(productId);
        axios.get<Info>(route('purchases.product-info', productId))
            .then(({ data }) => { setInfos((m) => ({ ...m, [productId]: data })); onLoaded?.(data); })
            .catch((e) => toast('error', e?.response?.status === 404 ? 'This product isn’t ready for purchases (finish its packaging first).' : 'Couldn’t load the product’s units. Check your connection and pick it again.'))
            .finally(() => loading.current.delete(productId));
    }, [infos]);

    // Units for products already on the form (edit, ?product_id=).
    useEffect(() => { lines.forEach((l) => l.product[0] && loadInfo(l.product[0].value, (info) => !l.category_id && applyUnit(l.key, info, info.last?.category_id ?? info.units[0]?.value))); }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Warn before leaving with unsaved lines.
    useEffect(() => {
        const handler = (e: BeforeUnloadEvent) => { if (dirty.current && !busy) e.preventDefault(); };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [busy]);

    const set = (key: string, patch: Partial<Line>) => { dirty.current = true; setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l))); };

    /**
     * Choosing a unit: prices already typed are per the OLD unit, so they are scaled by the unit size
     * (Box of 14 at 800 → Strip of 7 at 400); empty prices get the suggestion for the new unit.
     */
    const applyUnit = (key: string, info: Info, categoryId: number | undefined) => {
        const unit = info.units.find((u) => u.value === categoryId);
        setLines((ls) => ls.map((l) => {
            if (l.key !== key) return l;
            const prev = info.units.find((u) => u.value === l.category_id);
            const ratio = prev && unit && prev.base_units > 0 ? unit.base_units / prev.base_units : null;
            const next = (v: string, suggestion: number | undefined) => {
                if (v.trim() !== '' && ratio !== null && Number.isFinite(Number(v))) return String(Math.round(Number(v) * ratio * 100) / 100);
                return v.trim() === '' && suggestion ? String(suggestion) : v;
            };
            const paidSuggestion = unit && unit.paid_unit_cost_price > unit.unit_cost_price ? unit.paid_unit_cost_price : undefined;
            return { ...l, category_id: categoryId ?? null, unit_cost_price: next(l.unit_cost_price, unit?.unit_cost_price),
                unit_sale_price: next(l.unit_sale_price, unit?.unit_sale_price), paid_unit_cost_price: next(l.paid_unit_cost_price, paidSuggestion) };
        }));
    };

    const pickProduct = (key: string, v: Option[]) => {
        set(key, { product: v, category_id: null, unit_cost_price: '', paid_unit_cost_price: '', unit_sale_price: '' });
        if (v[0]) loadInfo(v[0].value, (info) => applyUnit(key, info, info.last?.category_id ?? info.units[0]?.value));
    };

    const totals = useMemo(() => lines.reduce((t, l) => {
        const p = preview(l, l.product[0] ? infos[l.product[0].value]?.units.find((u) => u.value === l.category_id) : undefined);
        return { paid: t.paid + p.totalPaid, tax: t.tax + p.purchaseTax };
    }, { paid: 0, tax: 0 }), [lines, infos]);

    const payload = (l: Line) => ({
        product_id: l.product[0]?.value ?? null, category_id: l.category_id, quantity: l.quantity, unit_cost_price: l.unit_cost_price,
        paid_unit_cost_price: l.paid_unit_cost_price || null, unit_sale_price: l.unit_sale_price, batch_no: l.batch_no || null,
        manufacturing_date: l.manufacturing_date || null, expiry_date: l.expiry_date || null,
        purchase_tax_ids: l.purchase_taxes.map((t) => t.value), sale_tax_ids: l.sale_taxes.map((t) => t.value),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const head = { supplier: supplier[0] ? toRef(supplier[0]) : null, invoice_no: invoiceNo || null };
        const opts = {
            preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false),
            onSuccess: () => { dirty.current = false; },
            onError: () => toast('error', 'Some items need attention: see the red messages.'),
        };
        if (isEdit) router.put(route('purchases.update', purchase.id), { ...head, line: payload(lines[0]!) }, opts);
        else router.post(route('purchases.store'), { ...head, lines: lines.map(payload) }, opts);
    };

    const err = (i: number, f: string) => errors[isEdit ? `line.${f}` : `lines.${i}.${f}`];
    const feedback = (m?: string) => m && <div className="invalid-feedback d-block">{m}</div>;
    const label = (id: string, text: ReactNode, hint?: string) => <label className="form-label fw-semibold mb-1 small" htmlFor={id}>{text}{hint && <span className="text-muted fw-normal ms-1">{hint}</span>}</label>;

    return (
        <>
            <Head title={isEdit ? 'Edit purchase' : 'Add purchase'} />
            <div className="mb-2"><Link href={isEdit ? route('purchases.show', purchase.id) : route('purchases.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />{isEdit ? 'Back to the purchase' : 'All purchases'}</Link></div>
            <PageHeader icon="box-arrow-in-down" title={isEdit ? 'Edit purchase' : 'Add purchase'}
                description={isEdit ? 'Correct what was entered. Amounts are recalculated on save.' : 'One supplier bill: add every item on it. Each item becomes a batch you can sell from.'} />

            {isEdit && purchase.locked && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}><i className="bi bi-lock" /><div>
                    <strong>{formatNumber(purchase.sold, 2)} already sold from this batch</strong>
                    <p>The product and unit can’t change, and the quantity can’t go below what was sold. Prices, dates, batch and taxes can.</p>
                </div></div>
            )}
            {errors.lines && <div className="alert alert-danger">{errors.lines}</div>}

            <form onSubmit={submit} noValidate>
                <SectionCard icon="truck" title="Supplier bill" accent="var(--chart-6)">
                    <div className="row g-3">
                        <div className="col-md-7">
                            {label('pf-supplier', wording.supplier ?? 'Supplier')}
                            <LookupPicker entity="suppliers" inputId="pf-supplier" creatable value={supplier} onChange={(v) => { dirty.current = true; setSupplier(v); }} invalid={!!errors.supplier} placeholder="Search or type a new supplier…" />
                            {feedback(errors.supplier)}
                        </div>
                        <div className="col-md-5">
                            {label('pf-bill', 'Bill / invoice no', 'Optional')}
                            <input id="pf-bill" className={`form-control ${errors.invoice_no ? 'is-invalid' : ''}`} maxLength={100} value={invoiceNo} onChange={(e) => { dirty.current = true; setInvoiceNo(e.target.value); }} placeholder="As printed on the supplier’s bill" />
                            {feedback(errors.invoice_no)}
                        </div>
                    </div>
                </SectionCard>

                {lines.map((l, i) => {
                    const info = l.product[0] ? infos[l.product[0].value] : undefined;
                    const unit = info?.units.find((u) => u.value === l.category_id);
                    const p = preview(l, unit);
                    const locked = isEdit && purchase.locked;
                    const days = l.expiry_date ? daysUntil(l.expiry_date) : null;
                    const id = (f: string) => `pf-${l.key}-${f}`;
                    return (
                        <SectionCard key={l.key} icon="capsule" accent="var(--chart-1)" title={isEdit ? 'Item' : `Item ${i + 1}${l.product[0] ? `: ${l.product[0].label}` : ''}`}
                            actions={!isEdit && (
                                <div className="d-flex gap-1">
                                    <button type="button" className="btn btn-icon btn-soft" title="Copy this item" aria-label={`Copy item ${i + 1}`} onClick={() => setLines((ls) => [...ls.slice(0, i + 1), { ...l, key: `l${++seq}`, batch_no: '', quantity: '1' }, ...ls.slice(i + 1)])}><i className="bi bi-copy" /></button>
                                    {lines.length > 1 && <button type="button" className="btn btn-icon btn-outline-danger" title="Remove" aria-label={`Remove item ${i + 1}`} onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))}><i className="bi bi-x-lg" /></button>}
                                </div>
                            )}>
                            <div className="row g-3">
                                <div className="col-lg-6">
                                    {label(id('product'), wording.product ?? 'Product')}
                                    <LookupPicker entity="stock-products" inputId={id('product')} value={l.product} onChange={(v) => pickProduct(l.key, v)} invalid={!!err(i, 'product_id')} isDisabled={locked} placeholder="Search by name or scan a barcode…" />
                                    {feedback(err(i, 'product_id'))}
                                    {info?.last && <small className="text-muted d-block mt-1">Last bought {info.last.date}{info.last.supplier && ` from ${info.last.supplier}`}</small>}
                                </div>
                                <div className="col-6 col-lg-3">
                                    {label(id('unit'), 'Unit')}
                                    <select id={id('unit')} className={`form-select ${err(i, 'category_id') ? 'is-invalid' : ''}`} value={l.category_id ?? ''} disabled={!info || locked}
                                        onChange={(e) => info && applyUnit(l.key, info, Number(e.target.value))}>
                                        {!info && <option value="">{l.product[0] ? 'Loading…' : 'Pick a product'}</option>}
                                        {info?.units.map((u) => <option key={u.value} value={u.value}>{u.label}{u.base_units > 1 && info.base_unit ? ` (${formatNumber(u.base_units, 0)} ${info.base_unit})` : ''}</option>)}
                                    </select>
                                    {feedback(err(i, 'category_id'))}
                                </div>
                                <div className="col-6 col-lg-3">
                                    {label(id('qty'), 'Quantity')}
                                    <input id={id('qty')} type="number" min={isEdit && purchase.locked ? purchase.sold : 0} step="any" className={`form-control ${err(i, 'quantity') ? 'is-invalid' : ''}`} value={l.quantity} onChange={(e) => set(l.key, { quantity: e.target.value })} />
                                    {feedback(err(i, 'quantity'))}
                                    {p.baseQty !== null && info?.base_unit && unit && unit.base_units !== 1 && <small className="text-muted">= {formatNumber(p.baseQty, 2)} {info.base_unit}</small>}
                                </div>

                                <div className="col-sm-4">
                                    {label(id('cost'), `Unit cost (${currency})`)}
                                    <input id={id('cost')} type="number" min={0} step="0.01" className={`form-control ${err(i, 'unit_cost_price') ? 'is-invalid' : ''}`} value={l.unit_cost_price} onChange={(e) => set(l.key, { unit_cost_price: e.target.value })} />
                                    {feedback(err(i, 'unit_cost_price'))}
                                </div>
                                <div className="col-sm-4">
                                    {label(id('paid'), 'Paid per unit', 'incl. sales tax paid')}
                                    <input id={id('paid')} type="number" min={0} step="0.01" className={`form-control ${err(i, 'paid_unit_cost_price') ? 'is-invalid' : ''}`} value={l.paid_unit_cost_price} placeholder={l.unit_cost_price || 'Same as unit cost'} onChange={(e) => set(l.key, { paid_unit_cost_price: e.target.value })} />
                                    {feedback(err(i, 'paid_unit_cost_price'))}
                                    {p.gstPercent > 0 && <small className="text-muted">GST {formatNumber(p.gstPercent, 2)}%: printed on receipts, not added to the price</small>}
                                </div>
                                <div className="col-sm-4">
                                    {label(id('sale'), `Sale price per unit (${currency})`)}
                                    <input id={id('sale')} type="number" min={0} step="0.01" className={`form-control ${err(i, 'unit_sale_price') ? 'is-invalid' : ''}`} value={l.unit_sale_price} onChange={(e) => set(l.key, { unit_sale_price: e.target.value })} />
                                    {feedback(err(i, 'unit_sale_price'))}
                                    {p.margin !== null && <small className={p.margin <= 0 ? 'text-danger' : 'text-muted'}>Margin {formatNumber(p.margin, 1)}% over what you paid</small>}
                                </div>

                                <div className="col-sm-4">
                                    {label(id('batch'), wording.batch ?? 'Batch', 'Optional')}
                                    <input id={id('batch')} className={`form-control ${err(i, 'batch_no') ? 'is-invalid' : ''}`} maxLength={100} value={l.batch_no} onChange={(e) => set(l.key, { batch_no: e.target.value })} />
                                    {feedback(err(i, 'batch_no'))}
                                </div>
                                <div className="col-6 col-sm-4">
                                    {label(id('mfg'), 'Manufactured', 'Optional')}
                                    <input id={id('mfg')} type="date" className="form-control" value={l.manufacturing_date} onChange={(e) => set(l.key, { manufacturing_date: e.target.value })} />
                                </div>
                                <div className="col-6 col-sm-4">
                                    {label(id('exp'), wording.expiry ?? 'Expiry')}
                                    <input id={id('exp')} type="date" className={`form-control ${err(i, 'expiry_date') ? 'is-invalid' : ''}`} value={l.expiry_date} onChange={(e) => set(l.key, { expiry_date: e.target.value })} />
                                    {feedback(err(i, 'expiry_date'))}
                                    {days !== null && days <= 0 && <small className="text-danger">Already expired: this stock can’t be sold.</small>}
                                    {days !== null && days > 0 && days <= 90 && <small className="text-warning">Short-dated: {days} days left.</small>}
                                </div>

                                <div className="col-md-6">
                                    {label(id('ptax'), 'Purchase taxes', 'Optional, on the unit cost')}
                                    <LookupPicker entity="taxes" inputId={id('ptax')} isMulti value={l.purchase_taxes} onChange={(v) => set(l.key, { purchase_taxes: v })} invalid={!!err(i, 'purchase_tax_ids')} placeholder="None" />
                                    {feedback(err(i, 'purchase_tax_ids'))}
                                </div>
                                <div className="col-md-6">
                                    {label(id('stax'), 'Sale taxes', 'Charged on top when sold, unless prices include tax')}
                                    <LookupPicker entity="taxes" inputId={id('stax')} isMulti value={l.sale_taxes} onChange={(v) => set(l.key, { sale_taxes: v })} invalid={!!err(i, 'sale_tax_ids')} placeholder="None" />
                                    {feedback(err(i, 'sale_tax_ids'))}
                                </div>
                            </div>
                            <div className="d-flex flex-wrap gap-3 small mt-3 pt-3 border-top text-muted">
                                <span>Paid for this item: <strong className="text-body tabular">{money(p.totalPaid)}</strong></span>
                                {p.purchaseTax > 0 && <span>Purchase tax: <strong className="text-body tabular">{money(p.purchaseTax)}</strong></span>}
                                {p.saleTaxUnit > 0 && <span>Sale tax per unit: <strong className="text-body tabular">{money(p.saleTaxUnit)}</strong></span>}
                            </div>
                        </SectionCard>
                    );
                })}

                {!isEdit && (
                    <button type="button" className="btn btn-light w-100 mb-4 py-3 border-dashed" onClick={() => { dirty.current = true; setLines((ls) => [...ls, blank()]); }} disabled={lines.length >= 100}>
                        <i className="bi bi-plus-lg me-1" />Add another item from this bill
                    </button>
                )}

                <div className="save-bar d-flex flex-wrap align-items-center gap-3">
                    <div className="me-auto small">
                        <span className="text-muted">{lines.length} item{lines.length === 1 ? '' : 's'} · </span>
                        <strong className="tabular">{money(totals.paid)}</strong><span className="text-muted"> paid</span>
                        {totals.tax > 0 && <span className="text-muted"> · {money(totals.tax)} purchase tax</span>}
                        <span className="d-block text-muted" style={{ fontSize: '0.75rem' }}>Preview: the server recalculates every amount on save.</span>
                    </div>
                    <Link href={isEdit ? route('purchases.show', purchase.id) : route('purchases.index')} className="btn btn-light">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}{isEdit ? 'Save changes' : `Save ${lines.length > 1 ? `${lines.length} items` : 'purchase'}`}</button>
                </div>
            </form>
        </>
    );
}
