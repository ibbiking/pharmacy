import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AuthLayout, { SignupJourney } from '@/Layouts/AuthLayout';

export default function CheckEmail({ email, resendIn, hours, devHint }: { email: string; resendIn: number; hours: number; devHint: boolean }) {
    const [wait, setWait] = useState(resendIn);
    const [busy, setBusy] = useState(false);
    useEffect(() => setWait(resendIn), [resendIn]);
    useEffect(() => {
        if (wait <= 0) return;
        const t = window.setTimeout(() => setWait((w) => w - 1), 1000);
        return () => window.clearTimeout(t);
    }, [wait]);

    return (
        <>
            <Head title="Check your email" />
            <SignupJourney step={1} />
            <div className="text-center">
                <div className="mail-art" aria-hidden="true"><i className="bi bi-envelope-paper-heart" /></div>
                <h1 className="auth-title">Check your email</h1>
                <p className="auth-sub">We sent a link to <strong className="text-break">{email}</strong>.<br />Click it to set your password. It works for {hours} hours.</p>
            </div>
            <ul className="tips">
                <li><i className="bi bi-search" />Can’t find it? Look in Spam or Promotions.</li>
                <li><i className="bi bi-pencil" />Wrong address? <Link href={route('signup')}>Start again</Link>.</li>
            </ul>
            {devHint && <div className="callout mt-3" style={{ ['--accent' as string]: 'var(--app-info)' }}><i className="bi bi-terminal" /><div><strong>Development</strong><p>Emails are written to <code>storage/logs/laravel.log</code>; open the link from there.</p></div></div>}
            <button type="button" className="btn btn-light btn-lg w-100 mt-3" disabled={wait > 0 || busy}
                onClick={() => { setBusy(true); router.post(route('signup.resend'), { email }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setWait(60) }); }}>
                {busy && <span className="spinner-border spinner-border-sm me-2" />}
                {wait > 0 ? `Send again in ${wait}s` : 'Send the link again'}
            </button>
        </>
    );
}

CheckEmail.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
