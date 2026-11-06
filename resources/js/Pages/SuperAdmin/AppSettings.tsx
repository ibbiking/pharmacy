import { Head, useForm } from '@inertiajs/react';
import { useRef, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Settings { app_name: string; app_currency: string; logo: string | null; favicon: string | null }

export default function AppSettings({ appSettings }: { appSettings: Settings }) {
    const form = useForm<{ app_name: string; app_currency: string; logo: File | null; favicon: File | null; remove_logo: boolean; remove_favicon: boolean }>({
        app_name: appSettings.app_name, app_currency: appSettings.app_currency, logo: null, favicon: null, remove_logo: false, remove_favicon: false,
    });
    const logoRef = useRef<HTMLInputElement>(null);
    const iconRef = useRef<HTMLInputElement>(null);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('superadmin.settings.update'), {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { form.reset('logo', 'favicon', 'remove_logo', 'remove_favicon'); [logoRef, iconRef].forEach((r) => r.current && (r.current.value = '')); },
        });
    };

    const image = (key: 'logo' | 'favicon', label: string, hint: string, ref: React.RefObject<HTMLInputElement | null>) => {
        const removeKey = key === 'logo' ? 'remove_logo' : 'remove_favicon';
        return (
            <div className="mb-3">
                <label className="form-label fw-semibold" htmlFor={key}>{label} <small className="text-muted fw-normal">{hint}</small></label>
                <div className="d-flex align-items-center gap-3">
                    {appSettings[key] && !form.data[removeKey] && <img src={appSettings[key]!} alt="" style={{ height: 40, maxWidth: 120, objectFit: 'contain', background: '#fff', borderRadius: 8, padding: 4, border: '1px solid var(--app-border)' }} />}
                    <input ref={ref} id={key} type="file" accept="image/*" className={`form-control ${form.errors[key] ? 'is-invalid' : ''}`} onChange={(e) => form.setData(key, e.target.files?.[0] ?? null)} />
                </div>
                {form.errors[key] && <div className="invalid-feedback d-block">{form.errors[key]}</div>}
                {appSettings[key] && (
                    <div className="form-check mt-2">
                        <input className="form-check-input" type="checkbox" id={removeKey} checked={form.data[removeKey]} onChange={(e) => form.setData(removeKey, e.target.checked)} />
                        <label className="form-check-label small" htmlFor={removeKey}>Remove</label>
                    </div>
                )}
            </div>
        );
    };

    return (
        <>
            <Head title="App settings" />
            <PageHeader icon="sliders2" title="App settings" description="Platform-wide defaults. Each business sets its own name, currency and branding in its settings." />
            <form onSubmit={submit} noValidate>
                <div className="row g-4">
                    <div className="col-lg-6">
                        <SectionCard icon="type" title="Name and fallback currency">
                            <div className="mb-3">
                                <label className="form-label fw-semibold" htmlFor="app_name">App name</label>
                                <input id="app_name" className={`form-control ${form.errors.app_name ? 'is-invalid' : ''}`} value={form.data.app_name} maxLength={40} onChange={(e) => form.setData('app_name', e.target.value)} />
                                {form.errors.app_name && <div className="invalid-feedback">{form.errors.app_name}</div>}
                                <div className="form-text">Shown in the browser tab of the classic pages.</div>
                            </div>
                            <div>
                                <label className="form-label fw-semibold" htmlFor="app_currency">Fallback currency symbol</label>
                                <input id="app_currency" className={`form-control ${form.errors.app_currency ? 'is-invalid' : ''}`} value={form.data.app_currency} maxLength={10} onChange={(e) => form.setData('app_currency', e.target.value)} />
                                {form.errors.app_currency && <div className="invalid-feedback">{form.errors.app_currency}</div>}
                                <div className="form-text">Used only for a business that hasn’t picked a currency.</div>
                            </div>
                        </SectionCard>
                    </div>
                    <div className="col-lg-6">
                        <SectionCard icon="image" title="Logo and icon">
                            {image('logo', 'Logo', 'max 1 MB', logoRef)}
                            {image('favicon', 'Browser icon', 'max 500 KB, square', iconRef)}
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
