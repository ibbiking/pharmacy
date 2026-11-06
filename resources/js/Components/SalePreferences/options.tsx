import type { ReactNode } from 'react';

export type PriceMode = 'static-price' | 'stock-wise-price' | 'previous-inventory-price';
export type LotMode = 'fefo' | 'fifo';

export interface BusinessSettings {
    price_mode: PriceMode; include_tax: boolean; lot_mode: LotMode; allow_price_change: boolean;
    min_price_percent: number | null; max_line_discount_percent: number | null; max_invoice_discount_percent: number | null;
}

interface Choice<T> { value: T; icon: string; title: string; text: string; example: string; accent: string }

/** One wording for the business-wide and the per-product screen (same rules as SalePriceCalculator / LotAllocator). */
export const PRICE_MODES: Choice<PriceMode>[] = [
    {
        value: 'static-price', icon: 'pin-angle', title: 'Fixed price', accent: 'var(--chart-1)',
        text: 'The sale price you set per unit in Packaging & prices. Purchases never change it.',
        example: 'Box 550, Tablet 40, whatever the batch cost.',
    },
    {
        value: 'stock-wise-price', icon: 'stack', title: 'Price of the batch sold', accent: 'var(--chart-3)',
        text: 'Each batch keeps the sale price entered on its purchase. The price follows the batch being sold.',
        example: 'Old batch 39.30 / tablet, new batch 42.00: old stock still sells at 39.30.',
    },
    {
        value: 'previous-inventory-price', icon: 'clock-history', title: 'Latest purchase price', accent: 'var(--chart-5)',
        text: 'Every unit sells at the sale price of your most recent purchase, even if older stock is left.',
        example: 'A new purchase at 42.00 makes all stock sell at 42.00.',
    },
];

export const LOT_MODES: Choice<LotMode>[] = [
    {
        value: 'fefo', icon: 'hourglass-split', title: 'Earliest expiry first (FEFO)', accent: 'var(--app-success)',
        text: 'Sells the batch that expires soonest. Best for medicines: less stock expires on the shelf.',
        example: 'Batch B expires in 3 months, A in 2029 → B is sold first.',
    },
    {
        value: 'fifo', icon: 'arrow-right-circle', title: 'Oldest purchase first (FIFO)', accent: 'var(--chart-2)',
        text: 'Sells the batch you bought first, whatever its expiry date.',
        example: 'A was bought in January, B in March → A is sold first.',
    },
];

export const labelOf = <T,>(list: Choice<T>[], v: T) => list.find((c) => c.value === v)?.title ?? String(v);
export const pct = (v: number | null) => (v === null ? 'No limit' : `${v}%`);

export function ChoiceCards<T extends string>({ items, value, onChange, cols = 3, extra }: { items: Choice<T>[]; value: T | null; onChange: (v: T) => void; cols?: 2 | 3 | 4; extra?: ReactNode }) {
    return (
        <div className="row g-3" role="radiogroup">
            {extra}
            {items.map((c) => (
                <div key={c.value} className={cols === 4 ? 'col-md-6 col-xl-3' : cols === 3 ? 'col-md-4' : 'col-md-6'}>
                    <button type="button" role="radio" aria-checked={value === c.value} className={`choice-card ${value === c.value ? 'selected' : ''}`}
                        style={{ ['--accent' as string]: c.accent }} onClick={() => onChange(c.value)}>
                        {value === c.value && <i className="bi bi-check-circle-fill check" />}
                        <span className="c-icon"><i className={`bi bi-${c.icon}`} /></span>
                        <strong>{c.title}</strong>
                        <p>{c.text}</p>
                        <p className="small fst-italic mt-auto"><i className="bi bi-lightbulb me-1" />{c.example}</p>
                    </button>
                </div>
            ))}
        </div>
    );
}

/** A switch with an optional % box: off = no limit. */
export function LimitRow({ id, title, hint, value, onChange, error }: { id: string; title: string; hint: string; value: string | null; onChange: (v: string | null) => void; error?: string }) {
    const on = value !== null;
    return (
        <div className="d-flex flex-wrap align-items-start gap-3 py-3 border-top">
            <div className="form-check form-switch m-0 flex-grow-1" style={{ minWidth: 220 }}>
                <input id={id} type="checkbox" role="switch" className="form-check-input" checked={on} onChange={(e) => onChange(e.target.checked ? '' : null)} />
                <label htmlFor={id} className="form-check-label fw-semibold">{title}</label>
                <div className="small text-muted">{hint}</div>
            </div>
            {on && (
                <div style={{ width: 140 }}>
                    <div className="input-group">
                        <input aria-label={title} type="number" min={0} max={100} step="0.01" className={`form-control ${error ? 'is-invalid' : ''}`} value={value ?? ''} onChange={(e) => onChange(e.target.value)} autoFocus />
                        <span className="input-group-text">%</span>
                    </div>
                    {error && <div className="invalid-feedback d-block">{error}</div>}
                </div>
            )}
        </div>
    );
}
