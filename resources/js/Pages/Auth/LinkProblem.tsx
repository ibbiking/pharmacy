import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function LinkProblem({ problem }: { problem: 'invalid' | 'expired' }) {
    const expired = problem === 'expired';
    return (
        <>
            <Head title={expired ? 'Link expired' : 'Link not valid'} />
            <div className="text-center">
                <div className="mail-art" style={{ ['--accent' as string]: 'var(--app-warning)' }} aria-hidden="true"><i className={`bi bi-${expired ? 'hourglass-bottom' : 'link-45deg'}`} /></div>
                <h1 className="auth-title">{expired ? 'This link has expired' : 'This link doesn’t work'}</h1>
                <p className="auth-sub">{expired ? 'For your safety, sign-up links work for a limited time.' : 'It may have been used already, or a newer link was sent.'} Sign up again and we’ll send a fresh link.</p>
                <div className="d-grid gap-2">
                    <Link href={route('signup')} className="btn btn-primary btn-lg">Sign up again</Link>
                    <Link href={route('login')} className="btn btn-light">I already have an account</Link>
                </div>
            </div>
        </>
    );
}

LinkProblem.layout = (page: ReactNode) => <AuthLayout>{page}</AuthLayout>;
