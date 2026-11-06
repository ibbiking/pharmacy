import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Settings {
    name: string;
    address: string | null;
    phone: string | null;
    note: string | null;
    currency: { id: number; label: string } | null;
    global_min_indicated_qty: number | null;
    invoice_source: 'local' | 'fbr';
}

export default function BusinessSettings({ settings }: { settings: Settings }) {
    const [currency, setCurrency] = useState<Option | null>(settings.currency ? { value: settings.currency.id, label: settings.currency.label } : null);
    const form = useForm({
        name: settings.name,
        phone: settings.phone ?? '',
        address: settings.address ?? '',
        note: settings.note ?? '',
        currency_id: settings.currency?.id ?? (null as number | null),
        global_min_indicated_qty: settings.global_min_indicated_qty === null ? '' : String(settings.global_min_indicated_qty),
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('business.settings.update'), { preserveScroll: true });
    };
    const err = (k: keyof typeof form.data) => (form.errors[k] ? 'is-invalid' : '');

    return (
        <>
            <Head title="Business settings" />
            <PageHeader icon="gear" title="Business settings" description="How this business appears on receipts and reports, and its shop-wide defaults." />
            <form onSubmit={submit} noValidate>
                <div className="row g-4">
                    <div className="col-lg-7">
                        <SectionCard icon="shop" title="Business details" subtitle="Printed on receipts">
                            <div className="row g-3">
                                <div className="col-md-6">
                                    <label className="form-label fw-semibold" htmlFor="name">Business name</label>
                                    <input id="name" className={`form-control ${err('name')}`} value={form.data.name} maxLength={255} onChange={(e) => form.setData('name', e.target.value)} />
                                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                                </div>
                                <div className="col-md-6">
                                    <label className="form-label fw-semibold" htmlFor="phone">Phone</label>
                                    <input id="phone" className={`form-control ${err('phone')}`} value={form.data.phone} maxLength={50} autoComplete="tel" onChange={(e) => form.setData('phone', e.target.value)} />
                                    {form.errors.phone && <div className="invalid-feedback">{form.errors.phone}</div>}
                                </div>
                                <div className="col-12">
                                    <label className="form-label fw-semibold" htmlFor="address">Address</label>
                                    <textarea id="address" rows={2} className={`form-control ${err('address')}`} value={form.data.address} maxLength={500} onChange={(e) => form.setData('address', e.target.value)} />
                                    {form.errors.address && <div className="invalid-feedback">{form.errors.address}</div>}
                                </div>
                                <div className="col-12">
                                    <label className="form-label fw-semibold" htmlFor="note">Receipt note <small className="text-muted fw-normal">printed at the bottom of every receipt</small></label>
                                    <textarea id="note" rows={2} className={`form-control ${err('note')}`} value={form.data.note} maxLength={500} placeholder="e.g. Thank you! Medicines are not returnable after 7 days." onChange={(e) => form.setData('note', e.target.value)} />
                                    {form.errors.note && <div className="invalid-feedback">{form.errors.note}</div>}
                                </div>
                            </div>
                        </SectionCard>
                    </div>

                    <div className="col-lg-5">
                        <SectionCard icon="cash-coin" title="Currency" subtitle="Shown next to amounts on screens and reports">
                            <label className="form-label fw-semibold" htmlFor="currency">Currency</label>
                            <AsyncSelect entity="business-currencies" url={route('business.settings.currencies')} inputId="currency" value={currency} invalid={!!form.errors.currency_id}
                                placeholder="Search by code or name…" onChange={(o) => { setCurrency(o); form.setData('currency_id', o?.value ?? null); }} />
                            {form.errors.currency_id && <div className="invalid-feedback d-block">{form.errors.currency_id}</div>}
                            <div className="form-text">Receipts print amounts without a symbol.</div>
                        </SectionCard>

                        <SectionCard icon="bell" title="Stock alert level" subtitle="For products without their own alert">
                            <label className="form-label fw-semibold" htmlFor="alert">Alert when stock falls to</label>
                            <input id="alert" type="number" min={0} step="any" inputMode="decimal" className={`form-control ${err('global_min_indicated_qty')}`} placeholder="Off"
                                value={form.data.global_min_indicated_qty} onChange={(e) => form.setData('global_min_indicated_qty', e.target.value)} />
                            {form.errors.global_min_indicated_qty && <div className="invalid-feedback">{form.errors.global_min_indicated_qty}</div>}
                            <div className="form-text">
                                Counted in each product’s <strong>largest unit</strong>: 5 means 5 boxes for a product sold in boxes and strips. A product with its own
                                alert level uses that instead. Leave empty to switch it off.
                            </div>
                        </SectionCard>

                        <SectionCard icon="receipt" title="Invoice numbers">
                            <div className="d-flex gap-2 align-items-start">
                                <i className="bi bi-check-circle-fill mt-1" style={{ color: 'var(--app-success)' }} />
                                <div className="small">
                                    <strong>Local numbers</strong> (INV-000001, …). Change the prefix and next number under <a href={route('settings.numbering')}>Numbering</a>.
                                </div>
                            </div>
                            <div className="d-flex gap-2 align-items-start mt-2 text-muted">
                                <i className="bi bi-hourglass-split mt-1" />
                                <div className="small">FBR invoicing comes with the FBR integration (roadmap step 6).</div>
                            </div>
                        </SectionCard>
                    </div>
                </div>
                <div className="d-flex mt-2">
                    <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing || !form.isDirty}>Save settings</button>
                </div>
            </form>
        </>
    );
}
