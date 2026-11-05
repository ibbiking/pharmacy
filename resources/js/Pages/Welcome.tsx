import { Head, Link } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import Toaster from '@/Components/Toaster';
import ThemeToggle from '@/Components/ThemeToggle';
import type { BusinessType } from '@/Components/BusinessTypePicker';

interface ModuleCard { key: string; name: string; description: string; icon: string; accent: string; available: boolean; core: boolean }
interface PlanCard { id: number; name: string; description: string | null; currency: string; monthly_price: number; trial_days: number; modules: string[] }
interface Props { brand: string; types: BusinessType[]; modules: ModuleCard[]; plans: PlanCard[]; free: boolean; signedIn: boolean }

const STEPS = [
    { icon: 'person-plus', title: 'Sign up in a minute', text: 'Pick your business type, confirm your email and set a password.' },
    { icon: 'puzzle', title: 'Choose your modules', text: 'Recommended modules are pre-selected for your type. Change them any time.' },
    { icon: 'box-seam', title: 'Add stock', text: 'Import medicines from the generic catalogue or add your own items, then record purchases.' },
    { icon: 'cart-check', title: 'Start selling', text: 'The POS picks the right batch, prices it and prints a receipt that matches the screen.' },
];

const FAQS = [
    ['Is it really free?', 'During launch every module is free. When paid plans start you’ll see the prices on the Billing page first, and nothing changes without your choice.'],
    ['Can I run more than one business?', 'Yes. Create as many businesses as you need from one login and switch between them. Each has its own stock, invoices, team and settings.'],
    ['Does it handle batches and expiry?', 'Yes. Every purchase is a batch with an expiry date. Sales use FEFO (first expiring first) or FIFO, and returns go back to the exact batch.'],
    ['Can my staff use it?', 'Invite people by email and give each a role: cashier, manager or your own custom role with exactly the permissions you choose.'],
    ['Will it work with FBR?', 'Invoice numbering already follows a With/Without FBR preference; the guided FBR integration is on the roadmap.'],
];

export default function Welcome({ brand, types, modules, plans, free, signedIn }: Props) {
    const [scrolled, setScrolled] = useState(false);
    useEffect(() => {
        const on = () => setScrolled(window.scrollY > 10);
        on();
        window.addEventListener('scroll', on, { passive: true });
        return () => window.removeEventListener('scroll', on);
    }, []);

    const cta = signedIn
        ? <a href={route('dashboard')} className="btn btn-primary btn-lg">Open your dashboard<i className="bi bi-arrow-right ms-2" /></a>
        : <Link href={route('signup')} className="btn btn-primary btn-lg">Get started{free ? ' free' : ''}<i className="bi bi-arrow-right ms-2" /></Link>;

    return (
        <div className="landing">
            <Head title="Pharmacy, shop & wholesale management" />
            <header className={`l-nav ${scrolled ? 'scrolled' : ''}`}>
                <div className="l-wrap d-flex align-items-center gap-3">
                    <Link href={route('home')} className="auth-logo"><span className="brand-mark"><i className="bi bi-capsule-pill" /></span>{brand}</Link>
                    <nav className="d-none d-md-flex gap-3 ms-4" aria-label="Sections">
                        <a href="#types">Businesses</a><a href="#modules">Modules</a><a href="#how">How it works</a><a href="#pricing">Pricing</a><a href="#faq">FAQ</a>
                    </nav>
                    <div className="ms-auto d-flex align-items-center gap-2">
                        <ThemeToggle />
                        {signedIn ? <a href={route('dashboard')} className="btn btn-primary">Dashboard</a> : (
                            <>
                                <Link href={route('login')} className="btn btn-light d-none d-sm-inline-block">Sign in</Link>
                                <Link href={route('signup')} className="btn btn-primary">Get started</Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <section className="l-hero">
                <div className="l-wrap row align-items-center g-5">
                    <div className="col-lg-6">
                        {free && <span className="l-badge"><i className="bi bi-gift" />Free during launch</span>}
                        <h1>Sell, stock and grow: <span className="grad">one app for your store.</span></h1>
                        <p className="lead">For pharmacies, shops and wholesalers. A fast POS with a live receipt, batches and expiry, smart pricing, team roles and reports, set up in minutes.</p>
                        <div className="d-flex flex-wrap gap-2 mt-4">
                            {cta}
                            <a href="#how" className="btn btn-light btn-lg">See how it works</a>
                        </div>
                        <div className="l-trust">
                            <span><i className="bi bi-shield-check" />Your data is per business</span>
                            <span><i className="bi bi-moon-stars" />Light & dark</span>
                            <span><i className="bi bi-phone" />Works on any screen</span>
                        </div>
                    </div>
                    <div className="col-lg-6">
                        <HeroMock />
                    </div>
                </div>
            </section>

            <section id="types" className="l-section">
                <div className="l-wrap">
                    <SectionTitle eyebrow="Built for your business" title="Pick your kind of business" text="Wording, screens and recommended modules adapt to it: “Medicines” for a pharmacy, “Items” for a shop." />
                    <div className="row g-3">
                        {types.map((t) => (
                            <div className="col-md-4" key={t.key}>
                                <Link href={signedIn ? route('business.create', { type: t.key }) : route('signup', { type: t.key })} className="type-block link" style={{ ['--accent' as string]: t.accent }}>
                                    <span className="type-art" aria-hidden="true"><span className="orb" /><i className={`bi bi-${t.icon}`} /></span>
                                    <strong className="d-block">{t.name}</strong>
                                    <span className="tagline">{t.tagline}</span>
                                    <span className="desc">{t.description}</span>
                                    <span className="examples">{t.examples.map((e) => <span key={e}>{e}</span>)}</span>
                                    <span className="go">{signedIn ? 'Create this business' : 'Start as ' + t.name}<i className="bi bi-arrow-right ms-1" /></span>
                                </Link>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="modules" className="l-section alt">
                <div className="l-wrap">
                    <SectionTitle eyebrow="Modules" title="Switch on only what you need" text="Every business chooses its own modules. New modules arrive regularly." />
                    <div className="row g-3">
                        {modules.map((m, i) => (
                            <div className="col-sm-6 col-lg-4" key={m.key}>
                                <div className={`module-card reveal-up ${m.available ? '' : 'unavailable'}`} style={{ ['--accent' as string]: m.accent, animationDelay: `${(i % 6) * 0.05}s` }}>
                                    <div className="d-flex align-items-center gap-2">
                                        <span className="m-icon"><i className={`bi bi-${m.icon}`} /></span>
                                        <h3 className="ms-1">{m.name}</h3>
                                        {!m.available && <span className="pill ms-auto" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Soon</span>}
                                        {m.core && <span className="pill ms-auto">Included</span>}
                                    </div>
                                    <p>{m.description}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="how" className="l-section">
                <div className="l-wrap">
                    <SectionTitle eyebrow="How it works" title="From sign-up to first sale" text="No installation. Your data stays separate per business." />
                    <div className="l-steps">
                        {STEPS.map((s, i) => (
                            <div className="l-step" key={s.title}>
                                <span className="n">{i + 1}</span>
                                <span className="ic"><i className={`bi bi-${s.icon}`} /></span>
                                <strong>{s.title}</strong>
                                <p>{s.text}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section id="pricing" className="l-section alt">
                <div className="l-wrap">
                    <SectionTitle eyebrow="Pricing" title={free ? 'Free while we launch' : 'Simple plans'} text={free ? 'Use every module free today. These are the plans after launch, so nothing surprises you later.' : 'Pick a plan, or build your own from modules. Cancel any time.'} />
                    <div className="row g-3 justify-content-center">
                        {plans.map((p, idx) => (
                            <div className="col-md-6 col-xl-3" key={p.id}>
                                <div className={`plan-card ${idx === 1 ? 'featured' : ''}`}>
                                    {idx === 1 && <span className="ribbon">Most popular</span>}
                                    <div><h3 className="h6 fw-bold mb-1">{p.name}</h3>{p.description && <p className="small text-muted mb-0">{p.description}</p>}</div>
                                    <div className="price">{free ? <>Free<small> now</small></> : p.monthly_price > 0 ? <>{money(p.monthly_price, p.currency)}<small> / month</small></> : 'Free'}</div>
                                    {free && p.monthly_price > 0 && <small className="text-muted">then {money(p.monthly_price, p.currency)} / month</small>}
                                    <ul>{p.modules.map((m) => <li key={m}><i className="bi bi-check2-circle" />{m}</li>)}</ul>
                                    {signedIn ? <a href={route('billing.index')} className="btn btn-outline-primary">View billing</a> : <Link href={route('signup')} className={`btn ${idx === 1 ? 'btn-primary' : 'btn-outline-primary'}`}>Get started</Link>}
                                </div>
                            </div>
                        ))}
                        {plans.length === 0 && <p className="text-center text-muted">Plans will be published soon.</p>}
                    </div>
                </div>
            </section>

            <section id="faq" className="l-section">
                <div className="l-wrap" style={{ maxWidth: 820 }}>
                    <SectionTitle eyebrow="Questions" title="Frequently asked" />
                    <div className="app-card px-4 py-2">
                        {FAQS.map(([q, a]) => <details key={q} className="faq"><summary>{q}</summary><p className="text-muted mb-0">{a}</p></details>)}
                    </div>
                </div>
            </section>

            <section className="l-cta">
                <div className="l-wrap text-center">
                    <h2>Ready when you are.</h2>
                    <p>Set up your business in a few minutes{free ? ', free during launch' : ''}.</p>
                    {cta}
                </div>
            </section>

            <footer className="l-foot">
                <div className="l-wrap d-flex flex-wrap gap-3 align-items-center">
                    <span className="auth-logo"><span className="brand-mark"><i className="bi bi-capsule-pill" /></span>{brand}</span>
                    <span className="text-muted small ms-md-3">© {new Date().getFullYear()} {brand}. All rights reserved.</span>
                    <span className="ms-auto d-flex gap-3 small">{signedIn ? <a href={route('dashboard')}>Dashboard</a> : <><Link href={route('login')}>Sign in</Link><Link href={route('signup')}>Create account</Link></>}</span>
                </div>
            </footer>
            <Toaster />
        </div>
    );
}

Welcome.layout = (page: ReactNode) => page;

const money = (n: number, c: string) => {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: c, maximumFractionDigits: 0 }).format(n); } catch { return `${c} ${n}`; }
};

function SectionTitle({ eyebrow, title, text }: { eyebrow: string; title: string; text?: string }) {
    return (
        <div className="l-title">
            <span className="eyebrow">{eyebrow}</span>
            <h2>{title}</h2>
            {text && <p>{text}</p>}
        </div>
    );
}

/** Decorative product preview: KPI tiles, a bar chart and a live receipt. */
function HeroMock() {
    const bars = [42, 58, 36, 70, 64, 88, 76];
    return (
        <div className="hero-mock" aria-hidden="true">
            <div className="hm-window">
                <div className="hm-bar"><span /><span /><span /><em>Dashboard</em></div>
                <div className="hm-body">
                    <div className="hm-kpis">
                        {[['Sales today', '48,250', 'var(--chart-1)'], ['Invoices', '126', 'var(--chart-2)'], ['Profit', '9,870', 'var(--chart-3)']].map(([l, v, c]) => (
                            <div key={l} className="hm-kpi" style={{ ['--accent' as string]: c }}><small>{l}</small><strong>{v}</strong></div>
                        ))}
                    </div>
                    <div className="hm-chart">
                        {bars.map((h, i) => <span key={i} style={{ height: `${h}%`, animationDelay: `${0.2 + i * 0.08}s` }} />)}
                    </div>
                    <div className="hm-alert"><i className="bi bi-exclamation-triangle" />3 batches expire this month: <b>sell first</b></div>
                </div>
            </div>
            <div className="receipt-mock floating">
                <div className="r-head"><strong>Receipt</strong><span>INV-000128</span></div>
                {[['Panadol × 2', '240.00'], ['ORS × 5', '150.00'], ['Vitamin C', '420.00']].map(([n, p], i) => (
                    <div className="r-line" key={n} style={{ animationDelay: `${0.8 + i * 0.25}s` }}><span>{n}</span><span>{p}</span></div>
                ))}
                <div className="r-total"><span>Total</span><span>810.00</span></div>
            </div>
        </div>
    );
}
