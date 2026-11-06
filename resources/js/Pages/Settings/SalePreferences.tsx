import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { ChoiceCards, LimitRow, LOT_MODES, PRICE_MODES, type BusinessSettings } from '@/Components/SalePreferences/options';

interface Props { settings: BusinessSettings; overrides: { price: number; lot: number; pos: number } }

const str = (v: number | null) => (v === null ? null : String(v));

/** Business-wide sale rules. Products can override them on their own Sale preferences page. */
export default function SalePreferences({ settings, overrides }: Props) {
    const { errors, wording } = usePage().props;
    const products = (wording.products ?? 'Products').toLowerCase();
    const initial = { ...settings, min_price_percent: str(settings.min_price_percent), max_line_discount_percent: str(settings.max_line_discount_percent), max_invoice_discount_percent: str(settings.max_invoice_discount_percent) };
    const [data, setData] = useState(initial);
    const [busy, setBusy] = useState(false);
    const dirty = JSON.stringify(data) !== JSON.stringify(initial);
    const set = <K extends keyof typeof data>(k: K, v: (typeof data)[K]) => setData((d) => ({ ...d, [k]: v }));
    const own = (n: number) => n > 0 && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>{n} {products} use their own</span>;

    const save = (e: FormEvent) => {
        e.preventDefault();
        router.post(route('global-sale-price-preferences.store'), data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    return (
        <>
            <Head title="Sale preferences" />
            <PageHeader icon="sliders" title="Sale preferences" help="sale-price-preferences"
                description={`How the POS prices and sells for the whole business. Any of the ${products} can override these on its own page.`} />

            <form onSubmit={save} noValidate>
                <SectionCard icon="tag" title={<>Sale price{own(overrides.price)}</>} subtitle="Which price the POS uses for each unit" accent="var(--chart-1)">
                    <ChoiceCards items={PRICE_MODES} value={data.price_mode} onChange={(v) => set('price_mode', v)} />
                    {errors.price_mode && <div className="invalid-feedback d-block">{errors.price_mode}</div>}
                    <div className="form-check form-switch mt-4">
                        <input id="sp-tax" type="checkbox" role="switch" className="form-check-input" checked={data.include_tax} onChange={(e) => set('include_tax', e.target.checked)} />
                        <label htmlFor="sp-tax" className="form-check-label fw-semibold">Sale prices already include the sale tax</label>
                        <div className="small text-muted">
                            On: the customer pays the shown price; the tax inside it (price × rate ÷ (100 + rate)) is shown to owners only. Off: the sale tax of the batch is added on top.
                            <br />Example at 17%: price 117 → On: customer pays 117 (17 is tax) · Off: customer pays 117 + 19.89.
                        </div>
                    </div>
                </SectionCard>

                <SectionCard icon="stack" title={<>Which batch sells first{own(overrides.lot)}</>} subtitle="Expired batches are never sold, whatever you choose" accent="var(--app-success)"
                    actions={<Link href={route('help.show', 'lot-selection')} className="btn btn-sm btn-light"><i className="bi bi-question-circle me-1" />FEFO or FIFO?</Link>}>
                    <ChoiceCards items={LOT_MODES} value={data.lot_mode} onChange={(v) => set('lot_mode', v)} cols={2} />
                </SectionCard>

                <SectionCard icon="cart-check" title={<>At the POS{own(overrides.pos)}</>} subtitle="Limits the server enforces at checkout. A cashier’s role can be stricter, never looser." accent="var(--app-warning)">
                    <div className="form-check form-switch pb-3">
                        <input id="sp-change" type="checkbox" role="switch" className="form-check-input" checked={data.allow_price_change} onChange={(e) => set('allow_price_change', e.target.checked)} />
                        <label htmlFor="sp-change" className="form-check-label fw-semibold">Cashiers may change the sale price</label>
                        <div className="small text-muted">Each change is saved with the system price next to it and shows in the audit log. The cashier also needs the “change price” permission.</div>
                    </div>
                    {data.allow_price_change && (
                        <LimitRow id="sp-min" title="Lowest allowed price" hint="Changed prices can’t go below this % of the system price. Example: 90% of 100 → at least 90."
                            value={data.min_price_percent} onChange={(v) => set('min_price_percent', v)} error={errors.min_price_percent} />
                    )}
                    <LimitRow id="sp-line" title="Maximum discount per line" hint="Per item line, as a % of the line total."
                        value={data.max_line_discount_percent} onChange={(v) => set('max_line_discount_percent', v)} error={errors.max_line_discount_percent} />
                    <LimitRow id="sp-inv" title="Maximum discount per invoice" hint="On the whole bill, as a % of the subtotal."
                        value={data.max_invoice_discount_percent} onChange={(v) => set('max_invoice_discount_percent', v)} error={errors.max_invoice_discount_percent} />
                </SectionCard>

                <div className="save-bar d-flex align-items-center gap-2 justify-content-end">
                    {dirty && <span className="small text-muted me-auto"><i className="bi bi-dot text-warning fs-4 align-middle" />Unsaved changes</span>}
                    <button type="button" className="btn btn-light" disabled={!dirty || busy} onClick={() => setData(initial)}>Undo</button>
                    <button type="submit" className="btn btn-primary" disabled={!dirty || busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}Save preferences</button>
                </div>
            </form>
        </>
    );
}
