import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { LookupPicker, isNewOption, toRef, type Option } from '@/Components/AsyncSelect';

interface ProductData {
    id: number; product_name: string; company: Option | null; product_type: Option | null; strengths: Option[]; formulas: Option[];
    barcode: string | null; rack: string | null; description: string | null; lock_max_discount: boolean; discount: number | null; discount_percent: number | null; draft: boolean;
}
interface Props { product: ProductData | null; locked: string | null }

interface State {
    product_name: string; company: Option[]; product_type: Option[]; strengths: Option[]; formulas: Option[];
    barcode: string; rack: string; description: string; lock_max_discount: boolean; discount: string; discount_percent: string;
}

const fromProduct = (p: ProductData | null): State => ({
    product_name: p?.product_name ?? '', company: p?.company ? [p.company] : [], product_type: p?.product_type ? [p.product_type] : [],
    strengths: p?.strengths ?? [], formulas: p?.formulas ?? [], barcode: p?.barcode ?? '', rack: p?.rack ?? '', description: p?.description ?? '',
    lock_max_discount: p?.lock_max_discount ?? false, discount: p?.discount ? String(p.discount) : '', discount_percent: p?.discount_percent ? String(p.discount_percent) : '',
});

/** Add / edit a product's details. Packaging and prices come next, in the setup wizard. The server re-checks everything. */
export default function ProductForm({ product }: Props) {
    const { errors, wording } = usePage().props;
    const [data, setData] = useState<State>(() => fromProduct(product));
    const [busy, setBusy] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const isNew = product === null;
    const singular = wording.product ?? 'Product';
    const set = <K extends keyof State>(k: K, v: State[K]) => setData((d) => ({ ...d, [k]: v }));
    const newNames = [...data.company, ...data.product_type, ...data.strengths, ...data.formulas].filter(isNewOption).map((o) => o.label);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = {
            product_name: data.product_name,
            company: data.company[0] ? toRef(data.company[0]) : null,
            product_type: data.product_type[0] ? toRef(data.product_type[0]) : null,
            strengths: data.strengths.map(toRef),
            formulas: data.formulas.map(toRef),
            barcode: data.barcode, rack: data.rack, description: data.description,
            lock_max_discount: data.lock_max_discount,
            discount: data.lock_max_discount ? data.discount || null : null,
            discount_percent: data.lock_max_discount ? data.discount_percent || null : null,
        };
        const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) };
        if (isNew) router.post(route('products.store'), payload, opts);
        else router.put(route('products.update', product.id), payload, opts);
    };

    const err = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;
    const label = (id: string, text: string, hint?: string): ReactNode => (
        <label className="form-label fw-semibold mb-1" htmlFor={id}>{text}{hint && <small className="text-muted fw-normal ms-1">{hint}</small>}</label>
    );
    const text = (k: 'product_name' | 'barcode' | 'rack', id: string, icon: string, props: { placeholder?: string; max: number }) => (
        <div className="input-icon-group">
            <i className={`bi bi-${icon} lead-icon`} />
            <input id={id} className={`form-control ${errors[k] ? 'is-invalid' : ''}`} value={data[k]} maxLength={props.max} placeholder={props.placeholder}
                onChange={(e) => set(k, e.target.value)} />
        </div>
    );

    return (
        <>
            <Head title={isNew ? `Add ${singular.toLowerCase()}` : `Edit ${product.product_name}`} />
            <div className="mb-2">
                <Link href={isNew ? route('products.index') : route('products.show', product.id)} className="small text-muted text-decoration-none">
                    <i className="bi bi-arrow-left me-1" />{isNew ? `All ${(wording.products ?? 'products').toLowerCase()}` : product.product_name}
                </Link>
            </div>
            <PageHeader icon="capsule" help="products" title={isNew ? `Add ${singular.toLowerCase()}` : `Edit ${product.product_name}`}
                description={isNew ? 'Step 1 of 2: details. Next you set the packaging and prices.' : 'Details only. Packaging and prices are on their own screen.'}
                actions={!isNew && <Link href={route('products.setup-wizard', product.id)} className="btn btn-light"><i className="bi bi-boxes me-1" />Packaging & prices</Link>} />

            <form onSubmit={submit} noValidate>
                <div className="row g-3">
                    <div className="col-xl-8">
                        <SectionCard icon="capsule" title="What it is" accent="var(--chart-1)">
                            <div className="row g-3">
                                <div className="col-12">{label('p-name', 'Name')}{text('product_name', 'p-name', 'capsule', { placeholder: 'e.g. Panadol', max: 200 })}{err('product_name')}</div>
                                <div className="col-md-6">
                                    {label('p-company', wording.company ?? 'Manufacturer')}
                                    <LookupPicker entity="companies" inputId="p-company" creatable value={data.company} onChange={(v) => set('company', v)} invalid={!!errors.company} placeholder="Search or type a new one…" />
                                    {err('company')}
                                </div>
                                <div className="col-md-6">
                                    {label('p-type', wording.product_type ?? 'Type')}
                                    <LookupPicker entity="product-types" inputId="p-type" creatable value={data.product_type} onChange={(v) => set('product_type', v)} invalid={!!errors.product_type} placeholder="e.g. Tablet, Syrup…" />
                                    {err('product_type')}
                                </div>
                                <div className="col-md-6">
                                    {label('p-strengths', wording.strengths ?? 'Strengths', 'Optional, one or more')}
                                    <LookupPicker entity="strengths" inputId="p-strengths" isMulti creatable value={data.strengths} onChange={(v) => set('strengths', v)} invalid={!!errors.strengths} placeholder="e.g. 500mg" />
                                    {err('strengths')}
                                </div>
                                <div className="col-md-6">
                                    {label('p-formulas', wording.formulas ?? 'Formulas', 'Optional, one or more')}
                                    <LookupPicker entity="formulas" inputId="p-formulas" isMulti creatable value={data.formulas} onChange={(v) => set('formulas', v)} invalid={!!errors.formulas} placeholder="e.g. Paracetamol" />
                                    {err('formulas')}
                                </div>
                                {newNames.length > 0 && (
                                    <div className="col-12"><div className="callout mb-0" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-plus-circle" /><div><strong>New entries</strong><p>{newNames.map((n) => `“${n}”`).join(', ')} will be added to your master data when you save.</p></div></div></div>
                                )}
                            </div>
                        </SectionCard>
                        <SectionCard icon="card-text" title="Notes" accent="var(--chart-6)">
                            {label('p-desc', 'Description', 'Optional')}
                            <textarea id="p-desc" rows={3} maxLength={1000} className={`form-control ${errors.description ? 'is-invalid' : ''}`} value={data.description} onChange={(e) => set('description', e.target.value)} />
                            {err('description')}
                        </SectionCard>
                    </div>

                    <div className="col-xl-4">
                        <SectionCard icon="upc-scan" title="Find it fast" accent="var(--chart-3)">
                            <div className="d-grid gap-3">
                                <div>{label('p-barcode', 'Barcode', 'Optional')}{text('barcode', 'p-barcode', 'upc-scan', { placeholder: 'Scan or type', max: 100 })}{err('barcode')}</div>
                                <div>{label('p-rack', 'Rack / shelf', 'Optional')}{text('rack', 'p-rack', 'bookshelf', { placeholder: 'e.g. A-3', max: 100 })}{err('rack')}</div>
                            </div>
                        </SectionCard>
                        <SectionCard icon="percent" title="Discount limit at the POS" accent="var(--app-warning)">
                            <div className="form-check form-switch mb-2">
                                <input id="p-lock" type="checkbox" className="form-check-input" role="switch" checked={data.lock_max_discount} onChange={(e) => set('lock_max_discount', e.target.checked)} />
                                <label htmlFor="p-lock" className="form-check-label fw-semibold">Limit the discount on this {singular.toLowerCase()}</label>
                            </div>
                            <p className="small text-muted mb-2">The strictest limit wins: this one, the business-wide limit and the cashier’s role limit. Leave a box empty for no limit of that kind.</p>
                            {data.lock_max_discount && (
                                <div className="row g-2">
                                    <div className="col-6">
                                        {label('p-dpct', 'Max %')}
                                        <input id="p-dpct" type="number" min={0} max={100} step="0.01" className={`form-control ${errors.discount_percent ? 'is-invalid' : ''}`} value={data.discount_percent} onChange={(e) => set('discount_percent', e.target.value)} />
                                        {err('discount_percent')}
                                    </div>
                                    <div className="col-6">
                                        {label('p-damt', 'Max amount')}
                                        <input id="p-damt" type="number" min={0} step="0.01" className={`form-control ${errors.discount ? 'is-invalid' : ''}`} value={data.discount} onChange={(e) => set('discount', e.target.value)} />
                                        {err('discount')}
                                    </div>
                                </div>
                            )}
                        </SectionCard>
                    </div>
                </div>

                <div className="d-flex flex-wrap gap-2 justify-content-end align-items-center mt-3">
                    {!isNew && <button type="button" className="btn btn-outline-danger me-auto" onClick={() => setArchiving(true)}><i className="bi bi-archive me-1" />Archive</button>}
                    <Link href={isNew ? route('products.index') : route('products.show', product.id)} className="btn btn-light">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={busy}>
                        {busy && <span className="spinner-border spinner-border-sm me-2" />}
                        {isNew ? <>Next: packaging<i className="bi bi-arrow-right ms-1" /></> : 'Save'}
                    </button>
                </div>
            </form>

            {!isNew && (
                <ConfirmDialog open={archiving} title={`Archive ${singular.toLowerCase()}?`} danger confirmLabel="Archive" busy={busy}
                    message={`“${product.product_name}” won’t be offered at the POS or on new purchases. Past invoices and purchases keep it. You can restore it from the Archived filter. A ${singular.toLowerCase()} that still has stock can’t be archived.`}
                    onConfirm={() => router.delete(route('products.destroy', product.id), { onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(false); } })}
                    onClose={() => setArchiving(false)} />
            )}
        </>
    );
}
