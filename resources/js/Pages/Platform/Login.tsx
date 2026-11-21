import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import PasswordInput from '@/Components/PasswordInput';
import Toaster from '@/Components/Toaster';
import ThemeToggle from '@/Components/ThemeToggle';
import type { PlatformProps } from '@/Layouts/PlatformLayout';

/** Sign-in of the platform portal (separate from the business sign-in at /admin/login). */
export default function PlatformLogin() {
    const form = useForm({ email: '', password: '', remember: false });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('platform.login.attempt'), { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Platform sign in" />
            <div className="text-center mb-4">
                <span className="brand-mark mx-auto mb-3" style={{ width: 56, height: 56, fontSize: 26, background: 'linear-gradient(135deg, var(--chart-5), var(--chart-2))' }}><i className="bi bi-shield-lock" /></span>
                <h1 className="auth-title mb-1">Platform admin</h1>
                <p className="auth-sub mb-0">Every business, every type: sign in to manage the platform.</p>
            </div>
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <div>
                    <label className="form-label fw-semibold mb-1" htmlFor="email">Email</label>
                    <div className="input-icon-group">
                        <i className="bi bi-envelope lead-icon" />
                        <input id="email" name="email" type="email" className={`form-control form-control-lg ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)} autoComplete="username" autoFocus required placeholder="admin@example.com" />
                    </div>
                    {form.errors.email && <div className="invalid-feedback d-block" role="alert">{form.errors.email}</div>}
                </div>
                <div>
                    <PasswordInput label="Password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                    <label className="form-check m-0 small mt-2">
                        <input type="checkbox" className="form-check-input" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} /> Keep me signed in
                    </label>
                </div>
                <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Sign in
                </button>
            </form>
        </>
    );
}

function PlatformAuthShell({ children }: { children: ReactNode }) {
    const { brand } = usePage().props as unknown as PlatformProps;

    return (
        <div className="min-vh-100 d-flex flex-column align-items-center justify-content-center p-3" style={{ background: 'var(--app-bg, var(--bs-body-bg))' }}>
            <div className="position-absolute top-0 end-0 p-3"><ThemeToggle /></div>
            <div className="card shadow-sm w-100" style={{ maxWidth: 440 }}>
                <div className="card-body p-4 p-md-5">{children}</div>
            </div>
            <p className="small text-muted mt-3 mb-0">© {new Date().getFullYear()} {brand} · Platform portal</p>
            <Toaster />
        </div>
    );
}

PlatformLogin.layout = (page: ReactNode) => <PlatformAuthShell>{page}</PlatformAuthShell>;
