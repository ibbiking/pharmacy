import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function Login({ email, status }: { email: string; status: string | null }) {
    const form = useForm({ email, password: '', remember: true });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('login'), { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Sign in" />
            <h1 className="auth-title">Welcome back</h1>
            <p className="auth-sub">Sign in to your account.</p>
            {status && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-success)' }}><i className="bi bi-check-circle" /><div><strong>{status}</strong></div></div>}
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <div>
                    <label className="form-label fw-semibold mb-1" htmlFor="email">Email</label>
                    <div className="input-icon-group">
                        <i className="bi bi-envelope lead-icon" />
                        <input id="email" name="email" type="email" className={`form-control form-control-lg ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)} autoComplete="username" autoFocus={!email} required placeholder="you@example.com" />
                    </div>
                    {form.errors.email && <div className="invalid-feedback d-block" role="alert">{form.errors.email}</div>}
                </div>
                <div>
                    <PasswordInput label="Password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} autoFocus={!!email} />
                    <div className="d-flex justify-content-between align-items-center mt-2">
                        <label className="form-check m-0 small">
                            <input type="checkbox" className="form-check-input" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} /> Keep me signed in
                        </label>
                        <Link href={route('password.request', form.data.email ? { email: form.data.email } : {})} className="small fw-semibold">Forgot password?</Link>
                    </div>
                </div>
                <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Sign in
                </button>
            </form>
            <p className="auth-alt">New here? <Link href={route('signup')} className="fw-semibold">Create your account</Link></p>
        </>
    );
}

Login.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
