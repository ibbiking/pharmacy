import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';
import PasswordInput from '@/Components/PasswordInput';

export default function ResetPassword({ email }: { email: string }) {
    const form = useForm({ password: '', password_confirmation: '' });
    const mismatch = form.data.password_confirmation !== '' && form.data.password !== form.data.password_confirmation;
    const submit = (e: FormEvent) => { e.preventDefault(); if (!mismatch) form.post(route('password.update')); };

    return (
        <>
            <Head title="Choose a new password" />
            <div className="mail-art sm" style={{ ['--accent' as string]: 'var(--app-success)' }} aria-hidden="true"><i className="bi bi-check2-circle" /></div>
            <h1 className="auth-title">Choose a new password</h1>
            <p className="auth-sub">For <strong className="text-break">{email}</strong>.</p>
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <input type="email" value={email} autoComplete="username" readOnly hidden />
                <PasswordInput label="New password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} autoComplete="new-password" meter autoFocus />
                <PasswordInput label="Repeat new password" name="password_confirmation" value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)} autoComplete="new-password" error={mismatch ? 'The passwords don’t match yet.' : undefined} />
                <button type="submit" className="btn btn-primary btn-lg" disabled={form.processing || mismatch || !form.data.password}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Save new password
                </button>
            </form>
        </>
    );
}

ResetPassword.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
