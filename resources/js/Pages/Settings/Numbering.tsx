import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Series { type: string; label: string; prefix: string; next_number: number; padding: number; preview: string; highest_used: number }

export default function Numbering({ series, fbr }: { series: Series[]; fbr: boolean }) {
    return (
        <>
            <Head title="Numbering" />
            <PageHeader icon="123" title="Numbering" description="How this business numbers its documents. Each business counts on its own: INV-000001, INV-000002, …" />
            {fbr && (
                <div className="alert alert-info d-flex gap-2 align-items-start">
                    <i className="bi bi-info-circle-fill mt-1" />
                    <div className="small">FBR invoicing is on: sale invoices print the FBR number. These local numbers are used when FBR is off.</div>
                </div>
            )}
            <div className="row g-4">
                {series.map((s) => (
                    <div className="col-lg-6" key={s.type}>
                        <SeriesCard series={s} />
                    </div>
                ))}
            </div>
        </>
    );
}

function SeriesCard({ series }: { series: Series }) {
    const form = useForm({ prefix: series.prefix, next_number: series.next_number, padding: series.padding });
    const preview = `${form.data.prefix}${String(form.data.next_number || 1).padStart(Number(form.data.padding) || 1, '0')}`;
    const save = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('settings.numbering.update', series.type), { preserveScroll: true });
    };

    return (
        <SectionCard icon="hash" title={series.label} subtitle={series.highest_used ? `Highest used so far: ${series.highest_used}` : 'Nothing issued yet with this prefix'}>
            <form onSubmit={save} noValidate>
                <div className="row g-3">
                    <div className="col-sm-4">
                        <label className="form-label fw-semibold" htmlFor={`${series.type}-prefix`}>Prefix</label>
                        <input id={`${series.type}-prefix`} className={`form-control ${form.errors.prefix ? 'is-invalid' : ''}`} value={form.data.prefix} maxLength={20} onChange={(e) => form.setData('prefix', e.target.value)} />
                        {form.errors.prefix && <div className="invalid-feedback">{form.errors.prefix}</div>}
                    </div>
                    <div className="col-sm-4">
                        <label className="form-label fw-semibold" htmlFor={`${series.type}-next`}>Next number</label>
                        <input id={`${series.type}-next`} type="number" min={1} className={`form-control ${form.errors.next_number ? 'is-invalid' : ''}`} value={form.data.next_number} onChange={(e) => form.setData('next_number', Number(e.target.value))} />
                        {form.errors.next_number && <div className="invalid-feedback">{form.errors.next_number}</div>}
                    </div>
                    <div className="col-sm-4">
                        <label className="form-label fw-semibold" htmlFor={`${series.type}-pad`}>Digits</label>
                        <input id={`${series.type}-pad`} type="number" min={1} max={12} className={`form-control ${form.errors.padding ? 'is-invalid' : ''}`} value={form.data.padding} onChange={(e) => form.setData('padding', Number(e.target.value))} />
                        {form.errors.padding && <div className="invalid-feedback">{form.errors.padding}</div>}
                    </div>
                </div>
                <div className="d-flex align-items-center mt-3">
                    <span className="text-muted small me-2">Next document:</span>
                    <span className="pill tabular" style={{ fontSize: '0.95rem' }}>{preview}</span>
                    <button type="submit" className="btn btn-primary ms-auto" disabled={!form.isDirty || form.processing}>Save</button>
                </div>
            </form>
        </SectionCard>
    );
}
