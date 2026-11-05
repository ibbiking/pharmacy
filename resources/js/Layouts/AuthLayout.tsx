import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Toaster from '@/Components/Toaster';
import ThemeToggle from '@/Components/ThemeToggle';

const HIGHLIGHTS = [
    { icon: 'receipt', title: 'Live POS receipt', text: 'Every sale updates on screen and prints exactly the same.' },
    { icon: 'calendar2-check', title: 'Batches & expiry', text: 'FEFO / FIFO stock, near-expiry alerts and returns that restock the right batch.' },
    { icon: 'puzzle', title: 'Only what you need', text: 'Switch modules on per business: POS, stock, pharmacy pack, B2B and more.' },
];

/** Sign-in / sign-up screens: brand panel on the left, the form card on the right. */
export default function AuthLayout({ children, wide }: { children: ReactNode; wide?: boolean }) {
    const { brand } = usePage().props;

    return (
        <div className="auth-shell">
            <aside className="auth-brand">
                <Link href={route('home')} className="auth-logo"><span className="brand-mark"><i className="bi bi-capsule-pill" /></span>{brand}</Link>
                <div className="auth-pitch">
                    <h2>Run your store with confidence.</h2>
                    <p>Pharmacies, shops and wholesalers: sell, stock and report in one place.</p>
                    <ul>
                        {HIGHLIGHTS.map((h, i) => (
                            <li key={h.title} style={{ animationDelay: `${0.1 + i * 0.12}s` }}>
                                <span className="h-icon"><i className={`bi bi-${h.icon}`} /></span>
                                <span><strong>{h.title}</strong><small>{h.text}</small></span>
                            </li>
                        ))}
                    </ul>
                </div>
                <ReceiptMock />
            </aside>
            <main className="auth-main">
                <div className="auth-top">
                    <Link href={route('home')} className="auth-logo d-lg-none"><span className="brand-mark"><i className="bi bi-capsule-pill" /></span>{brand}</Link>
                    <div className="ms-auto"><ThemeToggle /></div>
                </div>
                <div className={`auth-card ${wide ? 'wide' : ''}`}>{children}</div>
                <p className="auth-foot">© {new Date().getFullYear()} {brand}</p>
            </main>
            <Toaster />
        </div>
    );
}

/** Small animated receipt illustration (decorative). */
function ReceiptMock() {
    return (
        <div className="receipt-mock" aria-hidden="true">
            <div className="r-head"><strong>Receipt</strong><span>INV-000128</span></div>
            {[['Panadol 500mg × 2 strips', '240.00'], ['ORS sachet × 5', '150.00'], ['Vitamin C × 1 box', '420.00']].map(([n, p], i) => (
                <div className="r-line" key={n} style={{ animationDelay: `${0.4 + i * 0.25}s` }}><span>{n}</span><span>{p}</span></div>
            ))}
            <div className="r-total"><span>Total</span><span>810.00</span></div>
            <div className="r-chip"><i className="bi bi-check-circle-fill" /> Paid · batch B-2291 · exp 08/27</div>
        </div>
    );
}

/** Progress across the sign-up journey (shown on each sign-up screen). */
export function SignupJourney({ step }: { step: 0 | 1 | 2 | 3 }) {
    const steps = ['Your details', 'Confirm email', 'Password', 'Your business'];
    return (
        <ol className="journey" aria-label="Sign-up progress">
            {steps.map((s, i) => (
                <li key={s} className={i < step ? 'done' : i === step ? 'current' : ''} aria-current={i === step ? 'step' : undefined}>
                    <span className="n">{i < step ? <i className="bi bi-check" /> : i + 1}</span><span className="t">{s}</span>
                </li>
            ))}
        </ol>
    );
}
