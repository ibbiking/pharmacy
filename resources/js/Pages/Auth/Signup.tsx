import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, type FormEvent, type ReactNode } from 'react';
import AuthLayout, { SignupJourney } from '@/Layouts/AuthLayout';
import BusinessTypePicker, { type BusinessType } from '@/Components/BusinessTypePicker';

export default function Signup({ types, preselectType, free }: { types: BusinessType[]; preselectType: string | null; free: boolean }) {
    const form = useForm({ business_type: preselectType ?? '', name: '', email: '' });
    const detailsRef = useRef<HTMLDivElement>(null);
    const chosen = types.find((t) => t.key === form.data.business_type);

    const choose = (key: string) => {
        form.setData('business_type', key);
        form.clearErrors('business_type');
        window.setTimeout(() => detailsRef.current?.querySelector('input')?.focus({ preventScroll: false }), 50);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('signup'));
    };

    return (
        <>
            <Head title="Create your account" />
            <SignupJourney step={0} />
            <h1 className="auth-title">Create your account</h1>
            <p className="auth-sub">{free ? 'Free during launch. No card needed.' : 'Start in minutes. You choose a plan after setting up.'}</p>

            <form onSubmit={submit} noValidate>
                <h2 className="auth-q"><span>1</span>What kind of business do you run?</h2>
                <BusinessTypePicker types={types} value={form.data.business_type || null} onChange={choose} compact />
                {form.errors.business_type && <div className="text-danger small mt-2" role="alert">{form.errors.business_type}</div>}
                {chosen && (
                    <div className="type-note mt-3" style={{ ['--accent' as string]: chosen.accent }}>
                        <i className={`bi bi-${chosen.icon}`} />
                        <span><strong>{chosen.name}:</strong> {chosen.description}{chosen.bundle_names.length > 0 && <> Recommended: {chosen.bundle_names.join(', ')}. You can change modules in the next steps.</>}</span>
                    </div>
                )}

                <div ref={detailsRef} className={`mt-4 d-grid gap-3 reveal ${form.data.business_type ? 'shown' : ''}`}>
                    <h2 className="auth-q mb-0"><span>2</span>About you</h2>
                    <div>
                        <label className="form-label fw-semibold mb-1" htmlFor="name">Your name</label>
                        <div className="input-icon-group">
                            <i className="bi bi-person lead-icon" />
                            <input id="name" className={`form-control form-control-lg ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoComplete="name" maxLength={255} placeholder="e.g. Ahmed Rana" />
                        </div>
                        {form.errors.name && <div className="invalid-feedback d-block">{form.errors.name}</div>}
                    </div>
                    <div>
                        <label className="form-label fw-semibold mb-1" htmlFor="email">Work email</label>
                        <div className="input-icon-group">
                            <i className="bi bi-envelope lead-icon" />
                            <input id="email" type="email" className={`form-control form-control-lg ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} autoComplete="email" maxLength={255} placeholder="you@example.com" />
                        </div>
                        {form.errors.email && <div className="invalid-feedback d-block">{form.errors.email}{form.errors.email.includes('already exists') && <> <Link href={route('login', { email: form.data.email })}>Sign in</Link></>}</div>}
                        <small className="text-muted">We send a link to confirm it’s you.</small>
                    </div>
                    <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing || !form.data.business_type}>
                        {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Continue<i className="bi bi-arrow-right ms-2" />
                    </button>
                </div>
            </form>
            <p className="auth-alt">Already have an account? <Link href={route('login')} className="fw-semibold">Sign in</Link></p>
        </>
    );
}

Signup.layout = (page: ReactNode) => <AuthLayout wide>{page}</AuthLayout>;
