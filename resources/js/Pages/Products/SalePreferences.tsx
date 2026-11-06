import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { ChoiceCards, labelOf, LOT_MODES, pct, PRICE_MODES, type BusinessSettings, type LotMode, type PriceMode } from '@/Components/SalePreferences/options';

interface Overrides {
    price_mode: PriceMode | null; include_tax: boolean | null; lot_mode: LotMode | null; allow_price_change: boolean | null;
    min_price_percent: number | null; min_indicated_qty: number | null; min_qty_category_id: number | null;
}
interface Effective { priceMode: PriceMode; includeTax: boolean; lotMode: LotMode; allowPriceChange: boolean; minPricePercent: number | null; priceModeSource: string }
interface Props {
    product: { id: number; name: string; draft: boolean };
    overrides: Overrides;
    defaults: BusinessSettings;
    effective: Effective;
    units: { value: number; label: string }[];
}

/** "Use the business setting" card shown before a product's own choices. */
function Inherit({ selected, onClick, current, cols = 3 }: { selected: boolean; onClick: () => void; current: string; cols?: 2 | 3 | 4 }) {
    return (
        <div className={cols === 4 ? 'col-md-6 col-xl-3' : cols === 3 ? 'col-md-4' : 'col-md-6'}>
            <button type="button" role="radio" aria-checked={selected} className={`choice-card inherit ${selected ? 'selected' : ''}`} style={{ ['--accent' as string]: 'var(--chart-8)' }} onClick={onClick}>
                {selected && <i className="bi bi-check-circle-fill check" />}
                <span className="c-icon"><i className="bi bi-building" /></span>
                <strong>Business setting</strong>
                <p>Follows Settings → Sale preferences, also when it changes later.</p>
                <p className="small mt-auto">Now: <strong>{current}</strong></p>
            </button>
        </div>
    );
}

export default function ProductSalePreferences({ product, overrides, defaults: business, effective, units }: Props) {
    const { errors } = usePage().props;
    const [d, setD] = useState({
        price_mode: overrides.price_mode, include_tax: overrides.include_tax ?? business.include_tax, lot_mode: overrides.lot_mode,
        allow_price_change: overrides.allow_price_change, min_price_percent: overrides.min_price_percent === null ? '' : String(overrides.min_price_percent),
        min_indicated_qty: overrides.min_indicated_qty === null ? '' : String(overrides.min_indicated_qty), min_qty_category_id: overrides.min_qty_category_id ?? units[units.length - 1]?.value ?? null,
    });
    const [busy, setBusy] = useState(false);
    const set = <K extends keyof typeof d>(k: K, v: (typeof d)[K]) => setD((x) => ({ ...x, [k]: v }));
    const err = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;

    const save = (e: FormEvent) => {
        e.preventDefault();
        router.post(route('products.sale-price-preferences.store', product.id), {
            price_mode: d.price_mode, include_tax: d.price_mode ? d.include_tax : null, lot_mode: d.lot_mode,
            allow_price_change: d.allow_price_change, min_price_percent: d.min_price_percent || null,
            min_indicated_qty: d.min_indicated_qty || null, min_qty_category_id: d.min_indicated_qty ? d.min_qty_category_id : null,
        }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    const tri = (v: boolean | null, on: (x: boolean | null) => void) => (
        <div className="btn-group" role="radiogroup" aria-label="Cashiers may change the price">
            {([[null, `Business setting (${business.allow_price_change ? 'yes' : 'no'})`], [true, 'Yes'], [false, 'No']] as const).map(([val, label]) => (
                <button key={String(val)} type="button" role="radio" aria-checked={v === val} className={`btn ${v === val ? 'btn-primary' : 'btn-light'}`} onClick={() => on(val)}>{label}</button>
            ))}
        </div>
    );

    return (
        <>
            <Head title={`Sale preferences: ${product.name}`} />
            <div className="mb-2"><Link href={route('products.show', product.id)} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />{product.name}</Link></div>
            <PageHeader icon="sliders" title={`Sale preferences: ${product.name}`} help="sale-price-preferences"
                description="Leave a setting on “Business setting” unless this product needs its own rule." />

            <div className="callout mb-4" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-info-circle" /><div>
                <strong>In effect now</strong>
                <p>{labelOf(PRICE_MODES, effective.priceMode)}{effective.includeTax ? ', tax included' : ', tax added on top'} ({effective.priceModeSource === 'product' ? 'own setting' : 'business setting'}) · {labelOf(LOT_MODES, effective.lotMode)} · price changes {effective.allowPriceChange ? `allowed${effective.minPricePercent !== null ? `, not below ${effective.minPricePercent}%` : ''}` : 'not allowed'}</p>
            </div></div>

            <form onSubmit={save} noValidate>
                <SectionCard icon="tag" title="Sale price" accent="var(--chart-1)">
                    <ChoiceCards items={PRICE_MODES} value={d.price_mode} onChange={(v) => set('price_mode', v)} cols={4}
                        extra={<Inherit cols={4} selected={d.price_mode === null} onClick={() => set('price_mode', null)} current={`${labelOf(PRICE_MODES, business.price_mode)}${business.include_tax ? ', tax included' : ''}`} />} />
                    {err('price_mode')}
                    {d.price_mode && (
                        <div className="form-check form-switch mt-4">
                            <input id="pp-tax" type="checkbox" role="switch" className="form-check-input" checked={d.include_tax} onChange={(e) => set('include_tax', e.target.checked)} />
                            <label htmlFor="pp-tax" className="form-check-label fw-semibold">This product’s sale prices already include the sale tax</label>
                            <div className="small text-muted">Goes with the product’s own price setting. Off: the batch’s sale tax is added on top.</div>
                        </div>
                    )}
                </SectionCard>

                <SectionCard icon="stack" title="Which batch sells first" subtitle="Expired batches are never sold" accent="var(--app-success)">
                    <ChoiceCards items={LOT_MODES} value={d.lot_mode} onChange={(v) => set('lot_mode', v)}
                        extra={<Inherit selected={d.lot_mode === null} onClick={() => set('lot_mode', null)} current={labelOf(LOT_MODES, business.lot_mode)} />} />
                </SectionCard>

                <div className="row g-4">
                    <div className="col-lg-6">
                        <SectionCard icon="cart-check" title="Price changes at the POS" accent="var(--app-warning)">
                            <label className="form-label fw-semibold d-block">Cashiers may change the price</label>
                            {tri(d.allow_price_change, (v) => set('allow_price_change', v))}
                            {d.allow_price_change !== false && (
                                <div className="mt-3">
                                    <label className="form-label fw-semibold mb-1" htmlFor="pp-min">Lowest allowed price <small className="text-muted fw-normal">empty = business setting ({pct(business.min_price_percent)})</small></label>
                                    <div className="input-group" style={{ maxWidth: 200 }}>
                                        <input id="pp-min" type="number" min={0} max={100} step="0.01" className={`form-control ${errors.min_price_percent ? 'is-invalid' : ''}`} value={d.min_price_percent} onChange={(e) => set('min_price_percent', e.target.value)} />
                                        <span className="input-group-text">%</span>
                                    </div>
                                    {err('min_price_percent')}
                                </div>
                            )}
                            <p className="small text-muted mt-3 mb-0">The discount limit for this product is on <Link href={route('products.edit', product.id)}>Edit details</Link>.</p>
                        </SectionCard>
                    </div>
                    <div className="col-lg-6">
                        <SectionCard icon="bell" title="Low-stock alert" subtitle="Shown in Reports → Stock alerts" accent="var(--app-danger)">
                            {units.length === 0 ? (
                                <p className="text-muted small mb-0">Set up the packaging first. <Link href={route('products.setup-wizard', product.id)}>Packaging & prices</Link></p>
                            ) : (
                                <div className="row g-2">
                                    <div className="col-6">
                                        <label className="form-label fw-semibold mb-1" htmlFor="pp-alert">Alert at or below <small className="text-muted fw-normal">empty = off</small></label>
                                        <input id="pp-alert" type="number" min={0} step="0.01" className={`form-control ${errors.min_indicated_qty ? 'is-invalid' : ''}`} value={d.min_indicated_qty} onChange={(e) => set('min_indicated_qty', e.target.value)} />
                                        {err('min_indicated_qty')}
                                    </div>
                                    <div className="col-6">
                                        <label className="form-label fw-semibold mb-1" htmlFor="pp-unit">Unit</label>
                                        <select id="pp-unit" className={`form-select ${errors.min_qty_category_id ? 'is-invalid' : ''}`} value={d.min_qty_category_id ?? ''} onChange={(e) => set('min_qty_category_id', Number(e.target.value))} disabled={!d.min_indicated_qty}>
                                            {units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                                        </select>
                                        {err('min_qty_category_id')}
                                    </div>
                                </div>
                            )}
                        </SectionCard>
                    </div>
                </div>

                <div className="save-bar d-flex align-items-center gap-2 justify-content-end">
                    <Link href={route('products.show', product.id)} className="btn btn-light">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Save</button>
                </div>
            </form>
        </>
    );
}
