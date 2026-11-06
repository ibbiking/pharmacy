import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { formatMoney, formatNumber } from '@/lib/format';

interface Level { category_id: number; name: string; quantity: number; purchase_price: number | null; sale_price: number | null }
interface Props {
    product: { id: number; name: string; draft: boolean };
    chain: Level[];
    locked: string | null;
    currency: string;
}
interface Row { unit: Option | null; quantity: string; purchase_price: string; sale_price: string }

const toRow = (l: Level): Row => ({
    unit: { value: l.category_id, label: l.name },
    quantity: String(l.quantity),
    purchase_price: l.purchase_price === null ? '' : String(l.purchase_price),
    sale_price: l.sale_price === null ? '' : String(l.sale_price),
});

/** Packaging chain (Box → Strip → Tablet) + optional static unit prices. The server re-checks every rule. */
export default function ProductSetup({ product, chain, locked, currency }: Props) {
    const { errors, wording } = usePage().props;
    const [rows, setRows] = useState<Row[]>(chain.length ? chain.map(toRow) : [{ unit: null, quantity: '1', purchase_price: '', sale_price: '' }]);
    const [busy, setBusy] = useState(false);
    const units = wording.categories ?? 'Packaging';

    const set = (i: number, patch: Partial<Row>) => setRows((r) => r.map((x, j) => (j === i ? { ...x, ...patch } : x)));
    const add = () => setRows((r) => [...r, { unit: null, quantity: '', purchase_price: '', sale_price: '' }]);
    const removeLast = () => setRows((r) => (r.length > 1 ? r.slice(0, -1) : r));

    /** Base units in one of each level: product of the quantities below it. */
    const baseUnits = useMemo(() => {
        const out = rows.map(() => 1);
        for (let i = rows.length - 2; i >= 0; i--) out[i] = out[i + 1]! * (Number(rows[i + 1]!.quantity) || 0);
        return out;
    }, [rows]);
    const base = rows[rows.length - 1]?.unit?.label;
    const sentence = rows.every((r) => r.unit) ? rows.map((r, i) => `${i === 0 ? '1' : formatNumber(baseUnits[0]! / baseUnits[i]!, 0)} ${r.unit!.label}`).join(' = ') : null;
    const usedIds = rows.map((r) => r.unit?.value).filter(Boolean);
    const dup = usedIds.length !== new Set(usedIds).size;

    /** Fill empty smaller-unit prices from the largest unit (price ÷ units inside). */
    const fillDown = () => setRows((r) => r.map((x, i) => {
        if (i === 0) return x;
        const top = r[0]!;
        const per = baseUnits[0]! / baseUnits[i]!;
        const div = (v: string) => (v && per > 0 ? (Number(v) / per).toFixed(2) : '');
        return { ...x, purchase_price: x.purchase_price || div(top.purchase_price), sale_price: x.sale_price || div(top.sale_price) };
    }));

    const err = (i: number, f: string) => errors[`levels.${i}.${f}`];
    const save = () => {
        setBusy(true);
        router.post(route('products.setup.save', product.id), {
            levels: rows.map((r, i) => ({
                category_id: r.unit?.value ?? 0,
                quantity: i === 0 ? 1 : r.quantity,
                purchase_price: r.purchase_price === '' ? null : r.purchase_price,
                sale_price: r.sale_price === '' ? null : r.sale_price,
            })),
        }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return (
        <>
            <Head title={`Set up ${product.name}`} />
            <div className="mb-2"><Link href={route('products.show', product.id)} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />{product.name}</Link></div>
            <PageHeader icon="boxes" title={`Packaging: ${product.name}`} help="products"
                description="How the product is packed, from the largest unit down to the smallest one you sell. Stock is counted in the smallest unit." />

            {product.draft && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-magic" /><div><strong>Last step to activate this product</strong><p>Save the packaging and the product leaves Drafts and can be purchased and sold.</p></div></div>
            )}
            {locked && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}><i className="bi bi-lock" /><div><strong>Packaging is locked</strong><p>{locked} Units and quantities can’t change so existing stock stays correct. You can still change the prices.</p></div></div>
            )}
            {errors.levels && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-danger)' }} role="alert"><i className="bi bi-exclamation-octagon" /><div><strong>{errors.levels}</strong></div></div>}

            <SectionCard icon="diagram-3" title="1. Units" subtitle="Largest first, e.g. Box → Strip → Tablet">
                <div className="chain">
                    {rows.map((r, i) => (
                        <div key={i} className="chain-step">
                            {i > 0 && <div className="chain-link"><i className="bi bi-arrow-down" /><span>contains</span></div>}
                            <div className={`chain-card ${i === rows.length - 1 ? 'base' : ''}`}>
                                <span className="chain-n">{i + 1}</span>
                                <div className="row g-2 align-items-end flex-grow-1">
                                    {i > 0 && (
                                        <div className="col-sm-4">
                                            <label className="form-label small fw-semibold mb-1">How many in one {rows[i - 1]!.unit?.label ?? 'unit above'}?</label>
                                            <input type="number" min={0} step="any" inputMode="decimal" className={`form-control ${err(i, 'quantity') ? 'is-invalid' : ''}`} value={r.quantity} disabled={!!locked}
                                                onChange={(e) => set(i, { quantity: e.target.value })} placeholder="e.g. 10" aria-label={`Quantity of level ${i + 1}`} />
                                            {err(i, 'quantity') && <div className="invalid-feedback">{err(i, 'quantity')}</div>}
                                        </div>
                                    )}
                                    <div className={i > 0 ? 'col-sm-8' : 'col-12'}>
                                        <label className="form-label small fw-semibold mb-1">{i === 0 ? 'Largest unit' : 'Unit'}{i === rows.length - 1 && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--app-success)' }}>Stock is counted in this unit</span>}</label>
                                        <AsyncSelect entity="categories" value={r.unit} onChange={(o) => set(i, { unit: o })} placeholder={`Search ${units.toLowerCase()}…`} isDisabled={!!locked} invalid={!!err(i, 'category_id')} />
                                        {err(i, 'category_id') && <div className="invalid-feedback d-block">{err(i, 'category_id')}</div>}
                                    </div>
                                </div>
                                {!locked && i === rows.length - 1 && rows.length > 1 && (
                                    <button type="button" className="btn btn-icon btn-outline-danger ms-2" onClick={removeLast} aria-label="Remove this level"><i className="bi bi-x-lg" /></button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
                {dup && <div className="text-danger small mt-2">Each unit can appear only once.</div>}
                <div className="d-flex flex-wrap align-items-center gap-2 mt-3">
                    {!locked && rows.length < 6 && <button type="button" className="btn btn-soft" onClick={add}><i className="bi bi-plus-lg me-1" />Add a smaller unit</button>}
                    <span className="ms-auto small text-muted"><i className="bi bi-link-45deg me-1" />{sentence ?? 'Pick a unit for every level'}</span>
                </div>
                <p className="small text-muted mt-2 mb-0">Need a unit that isn’t listed? Add it under <Link href={route('categories.index')}>{units}</Link> first.</p>
            </SectionCard>

            <SectionCard icon="tags" title="2. Prices (optional)" subtitle="Used when the sale-price preference is “Static”; purchases set their own prices"
                actions={rows.length > 1 && <button type="button" className="btn btn-sm btn-light" onClick={fillDown}><i className="bi bi-arrow-down-short me-1" />Fill smaller units from the largest</button>}>
                <div className="table-responsive">
                    <table className="table align-middle mb-0">
                        <thead><tr><th>Unit</th><th className="text-end">Holds</th><th style={{ width: 200 }}>Purchase price</th><th style={{ width: 200 }}>Sale price</th><th className="text-end">Margin</th></tr></thead>
                        <tbody>
                            {rows.map((r, i) => {
                                const buy = Number(r.purchase_price) || 0;
                                const sell = Number(r.sale_price) || 0;
                                return (
                                    <tr key={i}>
                                        <td className="fw-semibold">{r.unit?.label ?? <span className="text-muted">—</span>}</td>
                                        <td className="text-end text-muted tabular">{i === rows.length - 1 ? '1' : `${formatNumber(baseUnits[i]!, 0)} ${base ?? ''}`}</td>
                                        <td>
                                            <input type="number" min={0} step="any" inputMode="decimal" className={`form-control form-control-sm ${err(i, 'purchase_price') ? 'is-invalid' : ''}`} value={r.purchase_price} placeholder={currency} aria-label={`Purchase price of ${r.unit?.label ?? `level ${i + 1}`}`}
                                                onChange={(e) => set(i, { purchase_price: e.target.value })} />
                                            {err(i, 'purchase_price') && <div className="invalid-feedback">{err(i, 'purchase_price')}</div>}
                                        </td>
                                        <td>
                                            <input type="number" min={0} step="any" inputMode="decimal" className={`form-control form-control-sm ${err(i, 'sale_price') || (buy > 0 && sell > 0 && buy >= sell) ? 'is-invalid' : ''}`} value={r.sale_price} placeholder={currency} aria-label={`Sale price of ${r.unit?.label ?? `level ${i + 1}`}`}
                                                onChange={(e) => set(i, { sale_price: e.target.value })} />
                                            {(err(i, 'sale_price') || (buy > 0 && sell > 0 && buy >= sell)) && <div className="invalid-feedback">{err(i, 'sale_price') ?? 'Must be higher than the purchase price.'}</div>}
                                        </td>
                                        <td className="text-end tabular">{buy > 0 && sell > buy ? <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>{formatMoney(sell - buy, currency)} · {Math.round(((sell - buy) / sell) * 100)}%</span> : <span className="text-muted">—</span>}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            <div className="d-flex gap-2 justify-content-end mt-3">
                <Link href={route('products.show', product.id)} className="btn btn-light">Cancel</Link>
                <button type="button" className="btn btn-primary px-4" disabled={busy || dup || rows.some((r) => !r.unit)} onClick={save}>
                    {busy && <span className="spinner-border spinner-border-sm me-2" />}{product.draft ? 'Save & activate product' : 'Save packaging'}
                </button>
            </div>
        </>
    );
}
