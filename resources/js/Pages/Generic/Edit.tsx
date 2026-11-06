import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { LookupPicker, isNewOption, toRef, type Option } from '@/Components/AsyncSelect';
import { toast } from '@/Components/Toaster';
import { formatNumber } from '@/lib/format';

interface Level { unit: Option | null; quantity: number; purchase_price: number | null; sale_price: number | null }
interface Entry { id: number; product_name: string; status: string; company: Option | null; type: Option | null; strengths: Option[]; formulas: Option[]; barcode: string | null; description: string | null; levels: Level[] }
interface Props { entry: Entry | null; admin: boolean }
interface Row { key: number; unit: Option[]; quantity: string; purchase_price: string; sale_price: string }

let seq = 0;
const toRow = (l: Level): Row => ({ key: ++seq, unit: l.unit ? [l.unit] : [], quantity: String(l.quantity), purchase_price: l.purchase_price === null ? '' : String(l.purchase_price), sale_price: l.sale_price === null ? '' : String(l.sale_price) });
const emptyRow = (first = false): Row => ({ key: ++seq, unit: [], quantity: first ? '1' : '', purchase_price: '', sale_price: '' });

/** One editor for "Suggest a product" (business), "Add to catalogue" and edit (super admin). The server checks who may save. */
export default function GenericEdit({ entry, admin }: Props) {
    const { errors, wording } = usePage().props;
    const [name, setName] = useState(entry?.product_name ?? '');
    const [company, setCompany] = useState<Option[]>(entry?.company ? [entry.company] : []);
    const [type, setType] = useState<Option[]>(entry?.type ? [entry.type] : []);
    const [strengths, setStrengths] = useState<Option[]>(entry?.strengths ?? []);
    const [formulas, setFormulas] = useState<Option[]>(entry?.formulas ?? []);
    const [barcode, setBarcode] = useState(entry?.barcode ?? '');
    const [description, setDescription] = useState(entry?.description ?? '');
    const [rows, setRows] = useState<Row[]>(entry?.levels.length ? entry.levels.map(toRow) : [emptyRow(true)]);
    const [busy, setBusy] = useState(false);
    const title = entry ? `Edit ${entry.product_name}` : admin ? 'Add to the catalogue' : 'Suggest a product';

    const setRow = (key: number, patch: Partial<Row>) => setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...patch } : r)));
    const baseUnits = useMemo(() => {
        const out = rows.map(() => 1);
        for (let i = rows.length - 2; i >= 0; i--) out[i] = out[i + 1]! * (Number(rows[i + 1]!.quantity) || 0);
        return out;
    }, [rows]);
    const sentence = rows.length > 1 && rows.every((r) => r.unit[0]) ? rows.map((r, i) => `${formatNumber(baseUnits[0]! / baseUnits[i]!, 0)} ${r.unit[0]!.label}`).join(' = ') : null;
    const newNames = [...company, ...type, ...strengths, ...formulas, ...rows.flatMap((r) => r.unit)].filter(isNewOption).map((o) => o.label);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = {
            product_name: name, company: company[0] ? toRef(company[0]) : null, type: type[0] ? toRef(type[0]) : null,
            strengths: strengths.map(toRef), formulas: formulas.map(toRef), barcode, description,
            levels: rows.filter((r) => r.unit[0] || r.quantity || r.purchase_price || r.sale_price).map((r, i) => ({
                unit: r.unit[0] ? toRef(r.unit[0]) : null, quantity: i === 0 ? 1 : r.quantity, purchase_price: r.purchase_price || null, sale_price: r.sale_price || null,
            })),
        };
        const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Please check the red messages.') };
        if (entry) router.put(route('generic_products.update', entry.id), payload, opts);
        else router.post(route('generic_products.suggest.store'), payload, opts);
    };

    const err = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;
    const label = (id: string, text: ReactNode, hint?: string) => <label className="form-label fw-semibold mb-1" htmlFor={id}>{text}{hint && <small className="text-muted fw-normal ms-1">{hint}</small>}</label>;

    return (
        <>
            <Head title={title} />
            <div className="mb-2"><Link href={route('generic_products.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />{wording.generic_catalog ?? 'Generic catalogue'}</Link></div>
            <PageHeader icon="collection" title={title}
                description={admin ? 'Entries you save here are approved: every business can import them.' : 'A platform admin reviews your suggestion before other businesses see it. You can change it until then.'} />
            {errors.entry && <div className="alert alert-danger">{errors.entry}</div>}

            <form onSubmit={submit} noValidate>
                <div className="row g-3">
                    <div className="col-xl-7">
                        <SectionCard icon="capsule" title="What it is" accent="var(--chart-1)">
                            <div className="row g-3">
                                <div className="col-12">{label('g-name', 'Name')}
                                    <input id="g-name" className={`form-control ${errors.product_name ? 'is-invalid' : ''}`} maxLength={200} value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Panadol" />{err('product_name')}</div>
                                <div className="col-md-6">{label('g-company', wording.company ?? 'Manufacturer')}
                                    <LookupPicker entity="generic-companies" inputId="g-company" creatable value={company} onChange={setCompany} invalid={!!errors.company} />{err('company')}</div>
                                <div className="col-md-6">{label('g-type', wording.product_type ?? 'Type')}
                                    <LookupPicker entity="generic-types" inputId="g-type" creatable value={type} onChange={setType} invalid={!!errors.type} />{err('type')}</div>
                                <div className="col-md-6">{label('g-str', wording.strengths ?? 'Strengths', 'Optional')}
                                    <LookupPicker entity="generic-strengths" inputId="g-str" creatable isMulti value={strengths} onChange={setStrengths} invalid={!!errors.strengths} />{err('strengths')}</div>
                                <div className="col-md-6">{label('g-for', wording.formulas ?? 'Formulas', 'Optional')}
                                    <LookupPicker entity="generic-formulas" inputId="g-for" creatable isMulti value={formulas} onChange={setFormulas} invalid={!!errors.formulas} />{err('formulas')}</div>
                                <div className="col-md-6">{label('g-bar', 'Barcode', 'Optional')}
                                    <input id="g-bar" className="form-control" maxLength={100} value={barcode} onChange={(e) => setBarcode(e.target.value)} />{err('barcode')}</div>
                                <div className="col-12">{label('g-desc', 'Description', 'Optional')}
                                    <textarea id="g-desc" rows={2} className="form-control" maxLength={1000} value={description} onChange={(e) => setDescription(e.target.value)} /></div>
                                {newNames.length > 0 && <div className="col-12"><div className="callout mb-0" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-plus-circle" /><div><strong>New names</strong><p>{newNames.map((n) => `“${n}”`).join(', ')} will be added{admin ? '' : ' for review'}.</p></div></div></div>}
                            </div>
                        </SectionCard>
                    </div>
                    <div className="col-xl-5">
                        <SectionCard icon="boxes" title="Packaging and suggested prices" accent="var(--chart-3)" subtitle="Largest unit first. Businesses get these when they import.">
                            {errors.levels && <div className="alert alert-danger py-2 small">{errors.levels}</div>}
                            <div className="d-grid gap-3">
                                {rows.map((r, i) => (
                                    <div key={r.key} className="border rounded p-3">
                                        <div className="d-flex align-items-center gap-2 mb-2">
                                            <span className="chain-n m-0" style={{ width: 24, height: 24 }}>{i + 1}</span>
                                            <strong className="small flex-grow-1">{i === 0 ? 'Largest unit' : i === rows.length - 1 ? 'Smallest unit (stock is counted in it)' : 'Unit'}</strong>
                                            {i > 0 && i === rows.length - 1 && <button type="button" className="btn btn-icon btn-outline-danger btn-sm" aria-label="Remove this unit" onClick={() => setRows((rs) => rs.slice(0, -1))}><i className="bi bi-x-lg" /></button>}
                                        </div>
                                        <div className="row g-2">
                                            <div className="col-12"><LookupPicker entity="generic-units" inputId={`g-unit-${i}`} creatable value={r.unit} onChange={(v) => setRow(r.key, { unit: v })} invalid={!!errors[`levels.${i}.unit`]} placeholder="e.g. Box, Strip, Tablet" />{err(`levels.${i}.unit`)}</div>
                                            {i > 0 && <div className="col-4"><input aria-label={`How many ${r.unit[0]?.label ?? 'units'} in one ${rows[i - 1]!.unit[0]?.label ?? 'unit above'}`} type="number" min={1} step={1} className={`form-control form-control-sm ${errors[`levels.${i}.quantity`] ? 'is-invalid' : ''}`} placeholder="Qty in above" value={r.quantity} onChange={(e) => setRow(r.key, { quantity: e.target.value })} />{err(`levels.${i}.quantity`)}</div>}
                                            <div className={i > 0 ? 'col-4' : 'col-6'}><input aria-label="Purchase price" type="number" min={0} step="0.01" className={`form-control form-control-sm ${errors[`levels.${i}.purchase_price`] ? 'is-invalid' : ''}`} placeholder="Buy" value={r.purchase_price} onChange={(e) => setRow(r.key, { purchase_price: e.target.value })} /></div>
                                            <div className={i > 0 ? 'col-4' : 'col-6'}><input aria-label="Sale price" type="number" min={0} step="0.01" className={`form-control form-control-sm ${errors[`levels.${i}.sale_price`] ? 'is-invalid' : ''}`} placeholder="Sell" value={r.sale_price} onChange={(e) => setRow(r.key, { sale_price: e.target.value })} />{err(`levels.${i}.sale_price`)}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <button type="button" className="btn btn-light w-100 mt-3 border-dashed" onClick={() => setRows((rs) => [...rs, emptyRow()])} disabled={rows.length >= 6}><i className="bi bi-plus-lg me-1" />Add a smaller unit</button>
                            {sentence && <p className="small text-muted mt-2 mb-0"><i className="bi bi-info-circle me-1" />{sentence}</p>}
                        </SectionCard>
                    </div>
                </div>
                <div className="save-bar d-flex justify-content-end gap-2">
                    <Link href={route('generic_products.index')} className="btn btn-light">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy && <span className="spinner-border spinner-border-sm me-2" />}{entry ? 'Save' : admin ? 'Add to catalogue' : 'Send for review'}</button>
                </div>
            </form>
        </>
    );
}
