import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import { formatMoney, formatNumber } from '@/lib/format';

export interface DetailUnit { id: number; name: string; base_quantity: number; price: number }
/** The product the cashier is pointing at (search result or cart row). */
export interface DetailFocus {
    productId: number; name: string; type: string | null; formula: string | null;
    units: DetailUnit[]; unitId: number | null; price: number | null; outOfStock?: boolean;
}
interface Qty { category: string; quantity: number | string }
export interface Availability {
    product_name: string;
    summary: { available: Qty[]; expired: Qty[] };
    held_by: { name: string; qty: number }[];
    warehouse: string | null;
    other_warehouses: { id: number; name: string; available: Qty[] }[];
    preference: {
        slug: string; name: string; source: 'product' | 'global' | 'default'; including_tax: boolean; lot_mode: string;
        allow_price_change: boolean; min_price_percent: number | null; max_line_discount_percent: number | null;
    };
    discount: { amount: number; percent: number; locked: boolean };
}

const SOURCE: Record<string, string> = { product: 'set on this product', global: 'business-wide setting', default: 'system default' };

/**
 * Frozen product-detail bar at the bottom of the POS (as in the Blade POS): hover or arrow to a search result or hover a
 * cart row to see price mode, unit prices, discount rules and live stock. Stock is fetched fresh every time (other tills'
 * carts change it), names/units/prices come from what the screen already has.
 */
export default function ProductDetailPanel({ focus, inMyCart, refreshKey, currency, onStock }: {
    focus: DetailFocus | null; inMyCart: Record<string, number>; refreshKey: string; currency: string; onStock: (productId: number) => void;
}) {
    const [data, setData] = useState<Availability | null>(null);
    const [state, setState] = useState<'idle' | 'loading' | 'error'>('idle');
    const seq = useRef(0);
    const productId = focus?.productId ?? null;

    useEffect(() => {
        if (!productId) { setData(null); return; }
        const id = ++seq.current;
        setState('loading');
        const t = window.setTimeout(() => {
            axios.get<Availability>(route('products.pos.availability', productId))
                .then(({ data: d }) => { if (id === seq.current) { setData(d); setState('idle'); } })
                .catch(() => { if (id === seq.current) setState('error'); });
        }, 80);
        return () => window.clearTimeout(t);
    }, [productId, refreshKey]);

    if (!focus) {
        return (
            <div className="pos-detail pos-detail--empty">
                <i className="bi bi-hand-index" />
                <span>Point at a product in the search results or the cart (or use ↑ ↓ in the search box) to see its price mode, unit prices, discount and live stock here.</span>
            </div>
        );
    }

    const money = (v: number) => formatMoney(v, currency);
    const fresh = data && data.product_name && productId === focus.productId ? data : null;
    const pref = fresh?.preference;
    const disc = fresh?.discount;
    const unit = focus.units.find((u) => u.id === focus.unitId);
    const leftover = { ...inMyCart };

    return (
        <div className="pos-detail" aria-live="polite">
            <div className="pos-detail-id">
                <strong className="text-truncate">{focus.name}</strong>
                {focus.type && <span className="pd-tag">{focus.type}</span>}
                {focus.formula && <span className="pd-tag pd-tag--formula" title="Formula">{focus.formula}</span>}
                <button type="button" className="btn btn-sm btn-light ms-auto" onClick={() => onStock(focus.productId)}><i className="bi bi-boxes me-1" />Stock summary</button>
            </div>
            <div className="pos-detail-grid">
                <div className="pd-block">
                    <span className="pd-label"><i className="bi bi-sliders" /> Price mode</span>
                    {pref ? <>
                        <span className="pd-chip pd-chip--primary" title={`Sale price preference (${SOURCE[pref.source] ?? pref.source})`}>{pref.name}</span>
                        <span className={`pd-chip ${pref.including_tax ? 'pd-chip--ok' : ''}`}>{pref.including_tax ? 'Tax included' : 'Tax excluded'}</span>
                        <small className="d-block text-muted mt-1">{SOURCE[pref.source] ?? pref.source} · sells {pref.lot_mode === 'fefo' ? 'earliest expiry first (FEFO)' : 'oldest purchase first (FIFO)'}</small>
                    </> : <Loading state={state} />}
                </div>
                <div className="pd-block">
                    <span className="pd-label"><i className="bi bi-tag" /> Price{unit ? ` per ${unit.name}` : ''}</span>
                    <strong className="tabular fs-6">{focus.outOfStock ? <span className="text-danger">Out of stock</span> : focus.price ? money(focus.price) : '—'}</strong>
                    {pref && <small className="d-block text-muted mt-1">{pref.allow_price_change ? (pref.min_price_percent != null ? `Price can be changed (not below ${formatNumber(pref.min_price_percent, 0)}%)` : 'Price can be changed') : 'Price is fixed'}</small>}
                </div>
                <div className="pd-block">
                    <span className="pd-label"><i className="bi bi-layers" /> Unit prices</span>
                    {focus.units.length ? focus.units.map((u) => (
                        <span key={u.id} className={`pd-chip ${u.id === focus.unitId ? 'pd-chip--primary' : ''}`} title={u.base_quantity > 1 ? `1 ${u.name} = ${formatNumber(u.base_quantity, 0)} base units` : 'Base unit'}>
                            {u.name}: <strong className="tabular">{money(u.price)}</strong>
                        </span>
                    )) : <span className="text-muted small">No packaging set up</span>}
                </div>
                <div className="pd-block">
                    <span className="pd-label"><i className="bi bi-percent" /> Discount</span>
                    {disc ? <>
                        <span className="pd-chip">{disc.percent > 0 ? `${formatNumber(disc.percent, 2)}%` : disc.amount > 0 ? money(disc.amount) : 'No discount'}</span>
                        {disc.locked && (disc.percent > 0 || disc.amount > 0) && <span className="pd-chip pd-chip--warn">max locked</span>}
                        {pref?.max_line_discount_percent != null && <small className="d-block text-muted mt-1">Line discount up to {formatNumber(pref.max_line_discount_percent, 2)}%</small>}
                    </> : <Loading state={state} />}
                </div>
                <div className="pd-block pd-block--wide">
                    <span className="pd-label"><i className="bi bi-shop" /> Stock {fresh?.warehouse ? `in ${fresh.warehouse} (this till)` : 'you can sell'}</span>
                    {fresh ? <>
                        {fresh.summary.available.map((a) => {
                            const mine = leftover[a.category] ?? 0; delete leftover[a.category];
                            return <span key={a.category} className="pd-chip pd-chip--ok">{a.category}: <strong className="tabular">{fmtQty(a.quantity)}</strong>{mine > 0 && <small> ({fmtQty(mine)} in your cart)</small>}</span>;
                        })}
                        {Object.entries(leftover).filter(([, v]) => v > 0).map(([k, v]) => <span key={k} className="pd-chip pd-chip--ok">{k}: <small>{fmtQty(v)} in your cart</small></span>)}
                        {fresh.summary.available.length === 0 && <span className="pd-chip pd-chip--danger">None available here</span>}
                        {fresh.summary.expired.map((a) => <span key={`x${a.category}`} className="pd-chip pd-chip--danger">{a.category}: {fmtQty(a.quantity)} expired</span>)}
                        {fresh.held_by.length > 0 && <span className="pd-chip pd-chip--held" title="Held in other cashiers' carts"><i className="bi bi-person-clock me-1" />{fresh.held_by.map((h) => `${h.name}: ${fmtQty(h.qty)}`).join(', ')}</span>}
                        {fresh.other_warehouses.map((w) => <span key={w.id} className="pd-chip" title="Not sellable at this till: transfer it first">{w.name}: {w.available.map((a) => `${fmtQty(a.quantity)} ${a.category}`).join(', ')}</span>)}
                    </> : <Loading state={state} />}
                </div>
            </div>
        </div>
    );
}

const fmtQty = (v: number | string) => { const n = Number(v); return Number.isFinite(n) ? formatNumber(n, Number.isInteger(n) ? 0 : 2) : String(v); };
function Loading({ state }: { state: string }) {
    return state === 'error' ? <span className="text-danger small">Couldn’t load</span> : <span className="text-muted small"><span className="spinner-border spinner-border-sm me-1" />Loading…</span>;
}
