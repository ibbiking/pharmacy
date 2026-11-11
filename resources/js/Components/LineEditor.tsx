import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

export interface UnitInfo { value: number; label: string; base_units: number; sale_price: number; cost: number }
export interface ItemInfo { id: number; name: string; base_unit: string | null; units: UnitInfo[]; tax_rate: number; stock: { warehouse_id: number | null; warehouse: string; base_qty: number }[]; total_base: number }
export interface EditorLine {
    key: string; product: Option | null; category_id: number | null; quantity: string; unit_price: string; discount_percent: string; tax: Option | null; description: string;
}

let seq = 0;
export const blankLine = (patch: Partial<EditorLine> = {}): EditorLine => ({
    key: `ln${++seq}`, product: null, category_id: null, quantity: '1', unit_price: '', discount_percent: '', tax: null, description: '', ...patch,
});

/** Amounts as the server computes them (DocumentService): net = qty × price × (1 − discount%), tax = net × rate. */
export function lineAmounts(l: EditorLine) {
    const qty = Number(l.quantity) || 0;
    const price = Number(l.unit_price) || 0;
    const disc = Math.min(100, Math.max(0, Number(l.discount_percent) || 0));
    const gross = qty * price;
    const net = Math.round(gross * (1 - disc / 100) * 100) / 100;
    const rate = Number(l.tax?.meta?.rate ?? 0);
    const tax = Math.round(net * rate) / 100;
    return { gross, discount: gross - net, net, tax, total: net + tax };
}

interface Props {
    lines: EditorLine[];
    onChange: (lines: EditorLine[]) => void;
    /** 'qty' = item, unit, quantity (stock moves). 'sale' / 'purchase' = priced lines (documents). */
    mode: 'qty' | 'sale' | 'purchase';
    errors: Record<string, string>;
    currency?: string;
    warehouseId?: number | null;
    allowPrice?: boolean;
    showStock?: boolean;
    lockItems?: boolean;
}

/** Editable item lines with paged item search, the item's units and live amounts. The server recomputes everything. */
export default function LineEditor({ lines, onChange, mode, errors, currency = '', warehouseId, allowPrice = true, showStock = true, lockItems = false }: Props) {
    const { wording } = usePage().props;
    const [infos, setInfos] = useState<Record<number, ItemInfo>>({});
    const loading = useRef(new Set<number>());
    const priced = mode !== 'qty';
    const money = (v: number) => formatMoney(v, currency);

    const load = (productId: number, then?: (info: ItemInfo) => void) => {
        if (infos[productId]) { then?.(infos[productId]!); return; }
        if (loading.current.has(productId)) return;
        loading.current.add(productId);
        axios.get<ItemInfo>(route('items.units', productId))
            .then(({ data }) => { setInfos((m) => ({ ...m, [productId]: data })); then?.(data); })
            .catch(() => toast('error', 'Couldn’t load this item’s units. Finish its packaging setup, or try again.'))
            .finally(() => loading.current.delete(productId));
    };
    useEffect(() => { lines.forEach((l) => l.product && load(l.product.value)); }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const update = (key: string, patch: Partial<EditorLine>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    const priceFor = (info: ItemInfo, unitId: number | null) => {
        const u = info.units.find((x) => x.value === unitId);
        if (!u) return '';
        const v = mode === 'purchase' ? u.cost : u.sale_price;
        return v ? String(v) : '';
    };
    const pick = (key: string, product: Option | null) => {
        update(key, { product, category_id: null, unit_price: '' });
        if (product) load(product.value, (info) => {
            const unitId = info.units[0]?.value ?? null;
            onChange(lines.map((l) => (l.key === key ? { ...l, product, category_id: unitId, unit_price: priced ? priceFor(info, unitId) : '' } : l)));
        });
    };
    const err = (i: number, f: string) => errors[`lines.${i}.${f}`];

    return (
        <div className="d-grid gap-2">
            {lines.map((l, i) => {
                const info = l.product ? infos[l.product.value] : undefined;
                const unit = info?.units.find((u) => u.value === l.category_id);
                const a = lineAmounts(l);
                const stockBase = info ? (warehouseId ? info.stock.filter((s) => s.warehouse_id === warehouseId).reduce((t, s) => t + s.base_qty, 0) : info.total_base) : null;
                const stockInUnit = stockBase !== null && unit ? stockBase / unit.base_units : null;
                const id = (f: string) => `ln-${l.key}-${f}`;
                return (
                    <div key={l.key} className="line-row">
                        <div className="row g-2 align-items-start">
                            <div className={priced ? 'col-lg-4' : 'col-lg-6'}>
                                <label className="form-label small fw-semibold mb-1" htmlFor={id('item')}>{wording.product ?? 'Item'} {i + 1}</label>
                                <AsyncSelect entity="stock-products" inputId={id('item')} value={l.product} onChange={(v) => pick(l.key, v)} invalid={!!err(i, 'product_id')} isDisabled={lockItems} placeholder="Search or scan…" />
                                {err(i, 'product_id') && <div className="invalid-feedback d-block">{err(i, 'product_id')}</div>}
                                {showStock && stockInUnit !== null && <small className={`d-block mt-1 ${stockInUnit > 0 ? 'text-muted' : 'text-danger'}`}>In stock{warehouseId ? ' here' : ''}: {formatNumber(stockInUnit, 2)} {unit?.label}</small>}
                            </div>
                            <div className="col-6 col-lg-2">
                                <label className="form-label small fw-semibold mb-1" htmlFor={id('unit')}>Unit</label>
                                <select id={id('unit')} className={`form-select ${err(i, 'category_id') ? 'is-invalid' : ''}`} value={l.category_id ?? ''} disabled={!info || lockItems}
                                    onChange={(e) => { const c = Number(e.target.value); update(l.key, { category_id: c, unit_price: priced && info ? priceFor(info, c) : l.unit_price }); }}>
                                    {!info && <option value="">—</option>}
                                    {info?.units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                                </select>
                            </div>
                            <div className="col-6 col-lg-2">
                                <label className="form-label small fw-semibold mb-1" htmlFor={id('qty')}>Quantity</label>
                                <input id={id('qty')} type="number" min="0" step="any" className={`form-control tabular ${err(i, 'quantity') ? 'is-invalid' : ''}`} value={l.quantity} onChange={(e) => update(l.key, { quantity: e.target.value })} />
                                {err(i, 'quantity') && <div className="invalid-feedback d-block">{err(i, 'quantity')}</div>}
                            </div>
                            {priced && (
                                <>
                                    <div className="col-6 col-lg-2">
                                        <label className="form-label small fw-semibold mb-1" htmlFor={id('price')}>{mode === 'purchase' ? 'Unit cost' : 'Unit price'}</label>
                                        <input id={id('price')} type="number" min="0" step="0.01" className={`form-control tabular ${err(i, 'unit_price') ? 'is-invalid' : ''}`} value={l.unit_price} disabled={!allowPrice}
                                            onChange={(e) => update(l.key, { unit_price: e.target.value })} />
                                        {err(i, 'unit_price') && <div className="invalid-feedback d-block">{err(i, 'unit_price')}</div>}
                                    </div>
                                    <div className="col-6 col-lg-2">
                                        <label className="form-label small fw-semibold mb-1" htmlFor={id('disc')}>Discount %</label>
                                        <input id={id('disc')} type="number" min="0" max="100" step="0.01" className={`form-control tabular ${err(i, 'discount_percent') ? 'is-invalid' : ''}`} value={l.discount_percent} onChange={(e) => update(l.key, { discount_percent: e.target.value })} />
                                    </div>
                                    <div className="col-lg-4">
                                        <label className="form-label small fw-semibold mb-1" htmlFor={id('tax')}>Tax</label>
                                        <AsyncSelect entity="taxes" inputId={id('tax')} value={l.tax} onChange={(v) => update(l.key, { tax: v })} placeholder="No tax" />
                                    </div>
                                    <div className="col-lg-6">
                                        <label className="form-label small fw-semibold mb-1" htmlFor={id('desc')}>Description <span className="text-muted fw-normal">Optional</span></label>
                                        <input id={id('desc')} className="form-control" maxLength={255} value={l.description} onChange={(e) => update(l.key, { description: e.target.value })} />
                                    </div>
                                    <div className="col-lg-2 text-lg-end">
                                        <div className="small text-muted">Line total</div>
                                        <div className="fw-bold tabular">{money(a.total)}</div>
                                        {a.tax > 0 && <small className="text-muted tabular">incl. tax {money(a.tax)}</small>}
                                    </div>
                                </>
                            )}
                            {!lockItems && (
                                <div className={`col-12 ${priced ? '' : 'col-lg-2'} d-flex justify-content-end align-items-end`} style={priced ? undefined : { minHeight: 62 }}>
                                    {lines.length > 1 && <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => onChange(lines.filter((x) => x.key !== l.key))} aria-label={`Remove line ${i + 1}`}><i className="bi bi-x-lg me-1" />Remove</button>}
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
            {!lockItems && <div><button type="button" className="btn btn-soft btn-sm" onClick={() => onChange([...lines, blankLine()])}><i className="bi bi-plus-lg me-1" />Add a line</button></div>}
        </div>
    );
}

export const linePayload = (l: EditorLine) => ({
    product_id: l.product?.value ?? null, category_id: l.category_id, quantity: l.quantity, unit_price: l.unit_price === '' ? null : l.unit_price,
    discount_percent: l.discount_percent === '' ? null : l.discount_percent, tax_id: l.tax?.value ?? null, description: l.description || null,
});
