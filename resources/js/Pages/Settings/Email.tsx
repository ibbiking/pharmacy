import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Settings {
    driver: 'platform' | 'smtp' | 'mailgun';
    from_name: string | null; from_address: string | null; reply_to: string | null;
    smtp_host: string | null; smtp_port: number | null; smtp_encryption: 'tls' | 'ssl' | 'none'; smtp_username: string | null; has_smtp_password: boolean;
    mailgun_domain: string | null; mailgun_endpoint: string; has_mailgun_secret: boolean;
    last_tested_at: string | null; last_test_ok: boolean | null; last_test_error: string | null;
}
interface Preset { key: string; label: string; icon: string; host: string; port: number; encryption: 'tls' | 'ssl'; hint: string }
interface Brand { name: string; logoUrl: string | null; color: string; initials: string }

export default function EmailSettings({ settings, brand, presets, platformFrom, userEmail }: { settings: Settings; brand: Brand; presets: Preset[]; platformFrom: string; userEmail: string }) {
    const form = useForm({
        driver: settings.driver, from_name: settings.from_name ?? '', from_address: settings.from_address ?? '', reply_to: settings.reply_to ?? '',
        smtp_host: settings.smtp_host ?? '', smtp_port: settings.smtp_port ?? 587, smtp_encryption: settings.smtp_encryption, smtp_username: settings.smtp_username ?? '', smtp_password: '',
        mailgun_domain: settings.mailgun_domain ?? '', mailgun_endpoint: settings.mailgun_endpoint, mailgun_secret: '',
    });
    const [hint, setHint] = useState<string | null>(null);
    const [previewKey, setPreviewKey] = useState(0);

    const save = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('settings.email.update'), { preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, smtp_password: '', mailgun_secret: '' })) });
    };
    const applyPreset = (p: Preset) => {
        form.setData((d) => ({ ...d, driver: 'smtp', smtp_host: p.host, smtp_port: p.port, smtp_encryption: p.encryption }));
        setHint(p.hint);
    };

    const status = settings.driver === 'platform'
        ? { tone: 'info', icon: 'info-circle-fill', text: `Emails go through the platform mailer from ${platformFrom}, with “${settings.from_name ?? brand.name}” as the sender name. Connect your own account so replies reach you.` }
        : settings.last_test_ok === true
            ? { tone: 'success', icon: 'check-circle-fill', text: `Emails are sent through ${settings.driver === 'smtp' ? `SMTP (${settings.smtp_host})` : `Mailgun (${settings.mailgun_domain})`}. Last test delivered ${settings.last_tested_at}.` }
            : settings.last_test_ok === false
                ? { tone: 'danger', icon: 'exclamation-octagon-fill', text: `Last test failed (${settings.last_tested_at}): ${settings.last_test_error}` }
                : { tone: 'warning', icon: 'exclamation-triangle-fill', text: 'Saved but not tested yet. Send a test email to make sure it works.' };

    return (
        <>
            <Head title="Email & branding" />
            <PageHeader icon="envelope-paper" title="Email & branding" description="How this business sends invoices, receipts and orders to customers and suppliers, and how those emails look." />

            <div className="row g-4">
                <div className="col-xl-7">
                    <SectionCard icon="envelope" title="Sending account" subtitle="Your own SMTP or Mailgun, or the platform mailer" accent="var(--chart-7)">
                        <div className={`alert alert-${status.tone} d-flex gap-2 align-items-start py-2`} role="status">
                            <i className={`bi bi-${status.icon} mt-1`} />
                            <div className="small">{status.text}</div>
                        </div>

                        <form onSubmit={save} noValidate>
                            <div className="btn-group w-100 mb-3" role="group" aria-label="Sending method">
                                {(['platform', 'smtp', 'mailgun'] as const).map((d) => (
                                    <button key={d} type="button" className={`btn ${form.data.driver === d ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => form.setData('driver', d)} aria-pressed={form.data.driver === d}>
                                        {d === 'platform' ? 'Platform mailer' : d === 'smtp' ? 'SMTP' : 'Mailgun'}
                                    </button>
                                ))}
                            </div>

                            <div className="row g-3">
                                <F label="Sender name" hint="Shown as who the email is from" error={form.errors.from_name} className="col-md-6">
                                    <input className={`form-control ${form.errors.from_name ? 'is-invalid' : ''}`} value={form.data.from_name} onChange={(e) => form.setData('from_name', e.target.value)} />
                                </F>
                                {form.data.driver !== 'platform' && (
                                    <F label="Sender email" hint="Must be allowed by your mailbox/domain" error={form.errors.from_address} className="col-md-6">
                                        <input type="email" className={`form-control ${form.errors.from_address ? 'is-invalid' : ''}`} value={form.data.from_address} onChange={(e) => form.setData('from_address', e.target.value)} />
                                    </F>
                                )}
                                <F label="Reply-to" hint="Optional, where replies go" error={form.errors.reply_to} className="col-md-6">
                                    <input type="email" className={`form-control ${form.errors.reply_to ? 'is-invalid' : ''}`} value={form.data.reply_to} onChange={(e) => form.setData('reply_to', e.target.value)} />
                                </F>
                            </div>

                            {form.data.driver === 'smtp' && (
                                <>
                                    <div className="d-flex flex-wrap gap-2 my-3">
                                        {presets.map((p) => (
                                            <button key={p.key} type="button" className="btn btn-sm btn-light" onClick={() => applyPreset(p)}>
                                                <i className={`bi bi-${p.icon} me-1`} />{p.label}
                                            </button>
                                        ))}
                                    </div>
                                    {hint && <div className="form-text mb-2"><i className="bi bi-lightbulb me-1" />{hint}</div>}
                                    <div className="row g-3">
                                        <F label="SMTP host" error={form.errors.smtp_host} className="col-md-6">
                                            <input className={`form-control ${form.errors.smtp_host ? 'is-invalid' : ''}`} value={form.data.smtp_host} onChange={(e) => form.setData('smtp_host', e.target.value)} placeholder="smtp.example.com" />
                                        </F>
                                        <F label="Port" error={form.errors.smtp_port} className="col-md-3">
                                            <input type="number" className={`form-control ${form.errors.smtp_port ? 'is-invalid' : ''}`} value={form.data.smtp_port} onChange={(e) => form.setData('smtp_port', Number(e.target.value))} />
                                        </F>
                                        <F label="Security" error={form.errors.smtp_encryption} className="col-md-3">
                                            <select className="form-select" value={form.data.smtp_encryption} onChange={(e) => form.setData('smtp_encryption', e.target.value as Settings['smtp_encryption'])}>
                                                <option value="tls">TLS (587)</option><option value="ssl">SSL (465)</option><option value="none">None</option>
                                            </select>
                                        </F>
                                        <F label="Username" error={form.errors.smtp_username} className="col-md-6">
                                            <input className="form-control" autoComplete="off" value={form.data.smtp_username} onChange={(e) => form.setData('smtp_username', e.target.value)} />
                                        </F>
                                        <F label="Password" hint={settings.has_smtp_password ? 'Saved: leave empty to keep it' : undefined} error={form.errors.smtp_password} className="col-md-6">
                                            <input type="password" className="form-control" autoComplete="new-password" value={form.data.smtp_password} onChange={(e) => form.setData('smtp_password', e.target.value)} placeholder={settings.has_smtp_password ? '••••••••' : ''} />
                                        </F>
                                    </div>
                                </>
                            )}

                            {form.data.driver === 'mailgun' && (
                                <div className="row g-3 mt-1">
                                    <F label="Mailgun domain" error={form.errors.mailgun_domain} className="col-md-6">
                                        <input className={`form-control ${form.errors.mailgun_domain ? 'is-invalid' : ''}`} value={form.data.mailgun_domain} onChange={(e) => form.setData('mailgun_domain', e.target.value)} placeholder="mg.example.com" />
                                    </F>
                                    <F label="Region" className="col-md-6">
                                        <select className="form-select" value={form.data.mailgun_endpoint} onChange={(e) => form.setData('mailgun_endpoint', e.target.value)}>
                                            <option value="api.mailgun.net">US (api.mailgun.net)</option><option value="api.eu.mailgun.net">EU (api.eu.mailgun.net)</option>
                                        </select>
                                    </F>
                                    <F label="API key" hint={settings.has_mailgun_secret ? 'Saved: leave empty to keep it' : undefined} error={form.errors.mailgun_secret} className="col-12">
                                        <input type="password" className={`form-control ${form.errors.mailgun_secret ? 'is-invalid' : ''}`} autoComplete="new-password" value={form.data.mailgun_secret} onChange={(e) => form.setData('mailgun_secret', e.target.value)} placeholder={settings.has_mailgun_secret ? '••••••••' : 'key-…'} />
                                    </F>
                                </div>
                            )}

                            <div className="d-flex mt-4">
                                <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing}>
                                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Save email settings
                                </button>
                            </div>
                        </form>
                    </SectionCard>
                </div>

                <div className="col-xl-5">
                    <Branding brand={brand} onSaved={() => setPreviewKey((k) => k + 1)} />
                    <TestEmail defaultTo={userEmail} />
                </div>

                <div className="col-12">
                    <SectionCard icon="eye" title="Preview" subtitle="How your customers see your emails" accent="var(--chart-2)">
                        <iframe key={previewKey} title="Email preview" src={route('settings.email.preview')} style={{ width: '100%', height: 560, border: 0, borderRadius: 12, background: '#f4f5fa' }} />
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

function Branding({ brand, onSaved }: { brand: Brand; onSaved: () => void }) {
    const form = useForm<{ logo: File | null; brand_color: string; remove_logo: boolean }>({ logo: null, brand_color: brand.color, remove_logo: false });
    const [preview, setPreview] = useState<string | null>(brand.logoUrl);
    const fileRef = useRef<HTMLInputElement>(null);
    useEffect(() => setPreview(brand.logoUrl), [brand.logoUrl]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('settings.email.branding'), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset('logo', 'remove_logo'); if (fileRef.current) fileRef.current.value = ''; onSaved(); } });
    };

    return (
        <SectionCard icon="palette" title="Branding" subtitle="Logo and colour on every email" accent={form.data.brand_color}>
            <form onSubmit={submit} noValidate>
                <div className="d-flex align-items-center gap-3 mb-3">
                    {preview && !form.data.remove_logo ? (
                        <img src={preview} alt="Logo" style={{ height: 56, maxWidth: 160, objectFit: 'contain', borderRadius: 10, background: '#fff', padding: 4, border: '1px solid var(--app-border)' }} />
                    ) : (
                        <span className="avatar-initials" style={{ width: 56, height: 56, borderRadius: 14, fontSize: '1.1rem', background: form.data.brand_color }}>{brand.initials}</span>
                    )}
                    <div className="small text-muted">{preview && !form.data.remove_logo ? 'Your logo appears in the email header.' : 'No logo yet: your initials on your brand colour are used.'}</div>
                </div>
                <div className="mb-3">
                    <label className="form-label fw-semibold" htmlFor="logo">Logo <small className="text-muted fw-normal">PNG / JPG / WebP, max 1 MB</small></label>
                    <input ref={fileRef} id="logo" type="file" accept="image/png,image/jpeg,image/webp" className={`form-control ${form.errors.logo ? 'is-invalid' : ''}`}
                        onChange={(e) => { const f = e.target.files?.[0] ?? null; form.setData('logo', f); form.setData('remove_logo', false); if (f) setPreview(URL.createObjectURL(f)); }} />
                    {form.errors.logo && <div className="invalid-feedback">{form.errors.logo}</div>}
                    {brand.logoUrl && (
                        <div className="form-check mt-2">
                            <input className="form-check-input" type="checkbox" id="remove-logo" checked={form.data.remove_logo} onChange={(e) => form.setData('remove_logo', e.target.checked)} />
                            <label className="form-check-label small" htmlFor="remove-logo">Remove logo</label>
                        </div>
                    )}
                </div>
                <div className="d-flex align-items-end gap-3">
                    <div>
                        <label className="form-label fw-semibold" htmlFor="color">Brand colour</label>
                        <input id="color" type="color" className="form-control form-control-color" value={form.data.brand_color} onChange={(e) => form.setData('brand_color', e.target.value)} />
                    </div>
                    <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing || !form.isDirty}>Save branding</button>
                </div>
            </form>
        </SectionCard>
    );
}

function TestEmail({ defaultTo }: { defaultTo: string }) {
    const form = useForm({ to: defaultTo });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('settings.email.test'), { preserveScroll: true, onFinish: () => router.reload({ only: ['settings'] }) }); };

    return (
        <SectionCard icon="send" title="Send a test email" subtitle="Checks your settings right now" accent="var(--chart-3)">
            <form onSubmit={submit} className="d-flex gap-2" noValidate>
                <input type="email" className={`form-control ${form.errors.to ? 'is-invalid' : ''}`} value={form.data.to} onChange={(e) => form.setData('to', e.target.value)} aria-label="Send test to" />
                <button type="submit" className="btn btn-primary text-nowrap" disabled={form.processing}>
                    {form.processing ? <span className="spinner-border spinner-border-sm" /> : <i className="bi bi-send me-1" />} Send test
                </button>
            </form>
            {form.errors.to && <div className="text-danger small mt-1">{form.errors.to}</div>}
        </SectionCard>
    );
}

function F({ label, hint, error, className, children }: { label: string; hint?: string; error?: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <label className="form-label fw-semibold mb-1">{label}</label>
            {children}
            {error ? <div className="invalid-feedback d-block">{error}</div> : hint && <div className="form-text mt-1">{hint}</div>}
        </div>
    );
}
