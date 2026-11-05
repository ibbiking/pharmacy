import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type ClipboardEvent, type FormEvent, type KeyboardEvent, type ReactNode } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function VerifyCode({ email, resendIn, expiryMinutes }: { email: string; resendIn: number; expiryMinutes: number }) {
    const form = useForm({ email, code: '' });
    const [digits, setDigits] = useState<string[]>(Array(6).fill(''));
    const [wait, setWait] = useState(resendIn);
    const refs = useRef<(HTMLInputElement | null)[]>([]);

    useEffect(() => setWait(resendIn), [resendIn]);
    useEffect(() => {
        if (wait <= 0) return;
        const t = window.setTimeout(() => setWait((w) => w - 1), 1000);
        return () => window.clearTimeout(t);
    }, [wait]);
    useEffect(() => { refs.current[0]?.focus(); }, []);

    const setAll = (next: string[]) => {
        setDigits(next);
        form.setData('code', next.join(''));
        if (form.errors.code) form.clearErrors('code');
    };
    const put = (i: number, v: string) => {
        const d = v.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[i] = d;
        setAll(next);
        if (d && i < 5) refs.current[i + 1]?.focus();
    };
    const onKey = (i: number, e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Backspace' && !digits[i] && i > 0) refs.current[i - 1]?.focus();
        if (e.key === 'ArrowLeft' && i > 0) refs.current[i - 1]?.focus();
        if (e.key === 'ArrowRight' && i < 5) refs.current[i + 1]?.focus();
    };
    const onPaste = (e: ClipboardEvent) => {
        const text = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        if (!text) return;
        e.preventDefault();
        setAll(Array.from({ length: 6 }, (_, i) => text[i] ?? ''));
        refs.current[Math.min(text.length, 5)]?.focus();
    };

    const complete = digits.every((d) => d !== '');
    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        if (!complete) return;
        form.post(route('password.verify'), { onError: () => { setAll(Array(6).fill('')); refs.current[0]?.focus(); } });
    };
    // Submit automatically when the 6th digit is typed.
    useEffect(() => { if (complete && !form.processing) submit(); }, [complete]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <>
            <Head title="Enter your code" />
            <div className="mail-art sm" aria-hidden="true"><i className="bi bi-shield-lock" /></div>
            <h1 className="auth-title">Enter the 6-digit code</h1>
            <p className="auth-sub">If <strong className="text-break">{email}</strong> has an account, the code is in its inbox. It expires in {expiryMinutes} minutes.</p>
            <form onSubmit={submit} noValidate>
                <div className="otp" onPaste={onPaste}>
                    {digits.map((d, i) => (
                        <input key={i} ref={(el) => { refs.current[i] = el; }} inputMode="numeric" autoComplete={i === 0 ? 'one-time-code' : 'off'} maxLength={1} value={d}
                            className={`form-control ${form.errors.code ? 'is-invalid' : ''}`} onChange={(e) => put(i, e.target.value)} onKeyDown={(e) => onKey(i, e)} aria-label={`Digit ${i + 1}`} />
                    ))}
                </div>
                {form.errors.code && <div className="text-danger small text-center mt-2" role="alert">{form.errors.code}</div>}
                <button type="submit" className="btn btn-primary btn-lg w-100 mt-3" disabled={!complete || form.processing}>
                    {form.processing && <span className="spinner-border spinner-border-sm me-2" />}Verify code
                </button>
            </form>
            <div className="d-flex justify-content-between mt-3 small">
                <Link href={route('password.request', { email })}>Use another email</Link>
                <button type="button" className="btn btn-link btn-sm p-0" disabled={wait > 0} onClick={() => router.post(route('password.resend'), { email }, { preserveScroll: true, onSuccess: () => setWait(60) })}>
                    {wait > 0 ? `Resend in ${wait}s` : 'Send a new code'}
                </button>
            </div>
        </>
    );
}

VerifyCode.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
