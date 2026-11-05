import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ForgotPassword({ email }: { email: string }) {
    const form = useForm({ email });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('password.email')); };

    return (
        <>
            <Head title="Forgot password" />
            <div className="mail-art sm" aria-hidden="true"><i className="bi bi-key" /></div>
            <h1 className="auth-title">Forgot your password?</h1>
            <p className="auth-sub">Enter your email and we’ll send you a 6-digit code to choose a new one.</p>
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <div>
                    <label className="form-label fw-semibold mb-1" htmlFor="email">Email</label>
                    <div className="input-icon-group">
                        <i className="bi bi-envelope lead-icon" />
                        <input id="email" type="email" className={`form-control form-control-lg ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} autoComplete="email" autoFocus />
                    </div>
                    {form.errors.email && <div className="invalid-feedback d-block">{form.errors.email}</div>}
                </div>
                <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Send code
                </button>
            </form>
            <p className="auth-alt"><Link href={route('login')}><i className="bi bi-arrow-left me-1" />Back to sign in</Link></p>
        </>
    );
}

ForgotPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
