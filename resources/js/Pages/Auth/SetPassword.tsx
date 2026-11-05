import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AuthLayout, { SignupJourney } from '@/Layouts/AuthLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function SetPassword({ token, name, email, type }: { token: string; name: string; email: string; type: string | null }) {
    const form = useForm({ token, password: '', password_confirmation: '' });
    const mismatch = form.data.password_confirmation !== '' && form.data.password !== form.data.password_confirmation;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (mismatch) return;
        form.post(route('register.complete'));
    };

    return (
        <>
            <Head title="Set your password" />
            <SignupJourney step={2} />
            <h1 className="auth-title">Hi {name.split(' ')[0]}, email confirmed!</h1>
            <p className="auth-sub">Choose a password for <strong className="text-break">{email}</strong>{type && <> · {type}</>}.</p>
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <input type="email" value={email} autoComplete="username" readOnly hidden />
                <PasswordInput label="Password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} autoComplete="new-password" meter autoFocus />
                <PasswordInput label="Repeat password" name="password_confirmation" value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)} autoComplete="new-password"
                    error={mismatch ? 'The passwords don’t match yet.' : undefined} />
                <ul className="rules">
                    <Rule ok={form.data.password.length >= 8}>At least 8 characters</Rule>
                    <Rule ok={/[a-zA-Z]/.test(form.data.password) && /\d/.test(form.data.password)}>Letters and numbers</Rule>
                </ul>
                <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing || mismatch || !form.data.password}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Create account<i className="bi bi-arrow-right ms-2" />
                </button>
            </form>
        </>
    );
}

function Rule({ ok, children }: { ok: boolean; children: ReactNode }) {
    return <li className={ok ? 'ok' : ''}><i className={`bi bi-${ok ? 'check-circle-fill' : 'circle'}`} />{children}</li>;
}

SetPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
