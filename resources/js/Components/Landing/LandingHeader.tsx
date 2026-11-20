import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ThemeToggle from '@/Components/ThemeToggle';

/** Landing-page sections linked from the navbar, in presenting order (Guide sits between Modules and How it works). */
const LINKS: { id: string; label: string; icon: string; page?: 'guide' }[] = [
    { id: 'types', label: 'Businesses', icon: 'shop' },
    { id: 'modules', label: 'Modules', icon: 'puzzle' },
    { id: 'guide', label: 'Guide', icon: 'book', page: 'guide' },
    { id: 'how', label: 'How it works', icon: 'signpost-split' },
    { id: 'pricing', label: 'Pricing', icon: 'tag' },
    { id: 'faq', label: 'FAQ', icon: 'question-circle' },
];

/** Public navbar of the landing page and the Guide page. Section links jump within the landing page; on the landing page the current section is highlighted. */
export default function LandingHeader({ brand, signedIn, onLanding }: { brand: string; signedIn: boolean; onLanding: boolean }) {
    const [scrolled, setScrolled] = useState(false);
    const [current, setCurrent] = useState<string>(onLanding ? '' : 'guide');
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const on = () => setScrolled(window.scrollY > 10);
        on();
        window.addEventListener('scroll', on, { passive: true });
        return () => window.removeEventListener('scroll', on);
    }, []);

    // Scroll-spy: highlight the landing section in view.
    useEffect(() => {
        if (!onLanding) return;
        const ids = LINKS.filter((l) => !l.page).map((l) => l.id);
        const obs = new IntersectionObserver((entries) => entries.forEach((e) => e.isIntersecting && setCurrent(e.target.id)), { rootMargin: '-35% 0px -55% 0px' });
        ids.forEach((id) => { const el = document.getElementById(id); if (el) obs.observe(el); });
        const top = () => window.scrollY < 200 && setCurrent('');
        window.addEventListener('scroll', top, { passive: true });
        return () => { obs.disconnect(); window.removeEventListener('scroll', top); };
    }, [onLanding]);

    // Mobile menu: close on outside click or Escape.
    useEffect(() => {
        if (!open) return;
        const click = (e: MouseEvent) => { if (menuRef.current && !menuRef.current.contains(e.target as Node)) setOpen(false); };
        const key = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', click);
        document.addEventListener('keydown', key);
        return () => { document.removeEventListener('mousedown', click); document.removeEventListener('keydown', key); };
    }, [open]);

    const section = (id: string) => (onLanding ? `#${id}` : `${route('home')}#${id}`);
    const item = (l: (typeof LINKS)[number], mobile = false) => {
        const active = current === l.id;
        const cls = `l-nav-link ${active ? 'active' : ''} ${l.page ? 'is-page' : ''}`;
        const body = <><i className={`bi bi-${l.icon}`} /><span>{l.label}</span></>;
        const close = () => mobile && setOpen(false);
        return l.page
            ? <Link key={l.id} href={route('guide')} className={cls} aria-current={active ? 'page' : undefined} onClick={close}>{body}</Link>
            : <a key={l.id} href={section(l.id)} className={cls} aria-current={active ? 'true' : undefined} onClick={close}>{body}</a>;
    };

    return (
        <header className={`l-nav ${scrolled ? 'scrolled' : ''}`}>
            <div className="l-wrap d-flex align-items-center gap-3">
                <Link href={route('home')} className="auth-logo l-nav-brand"><span className="brand-mark"><i className="bi bi-boxes" /></span>{brand}</Link>
                <nav className="l-nav-pills d-none d-lg-flex" aria-label="Sections">
                    {LINKS.map((l) => item(l))}
                </nav>
                <div className="ms-auto d-flex align-items-center gap-1 gap-sm-2">
                    <span className="d-none d-sm-inline-flex"><ThemeToggle /></span>
                    {signedIn ? <a href={route('dashboard')} className="btn btn-primary l-nav-cta">Dashboard<i className="bi bi-arrow-right ms-1" /></a> : (
                        <>
                            <Link href={route('login')} className="btn btn-light d-none d-sm-inline-block">Sign in</Link>
                            <Link href={route('signup')} className="btn btn-primary l-nav-cta">Get started<i className="bi bi-arrow-right ms-1" /></Link>
                        </>
                    )}
                    <div className="position-relative d-lg-none" ref={menuRef}>
                        <button type="button" className="btn btn-light l-nav-burger" aria-label="Menu" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
                            <i className={`bi bi-${open ? 'x-lg' : 'list'}`} />
                        </button>
                        {open && (
                            <nav className="l-nav-menu" aria-label="Sections">
                                {LINKS.map((l) => item(l, true))}
                                {!signedIn && <Link href={route('login')} className="l-nav-link d-sm-none" onClick={() => setOpen(false)}><i className="bi bi-box-arrow-in-right" /><span>Sign in</span></Link>}
                            </nav>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}
