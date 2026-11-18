import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ThemeToggle from '@/Components/ThemeToggle';

/** Public navbar of the landing page and the Guide page. Section links jump within the landing page. */
export default function LandingHeader({ brand, signedIn, onLanding }: { brand: string; signedIn: boolean; onLanding: boolean }) {
    const [scrolled, setScrolled] = useState(false);
    useEffect(() => {
        const on = () => setScrolled(window.scrollY > 10);
        on();
        window.addEventListener('scroll', on, { passive: true });
        return () => window.removeEventListener('scroll', on);
    }, []);
    const section = (id: string) => (onLanding ? `#${id}` : `${route('home')}#${id}`);
    const isGuide = !onLanding;

    return (
        <header className={`l-nav ${scrolled ? 'scrolled' : ''}`}>
            <div className="l-wrap d-flex align-items-center gap-3">
                <Link href={route('home')} className="auth-logo"><span className="brand-mark"><i className="bi bi-capsule-pill" /></span>{brand}</Link>
                <nav className="d-none d-md-flex gap-3 ms-4" aria-label="Sections">
                    <a href={section('types')}>Businesses</a><a href={section('modules')}>Modules</a><a href={section('how')}>How it works</a>
                    <Link href={route('guide')} className={isGuide ? 'active' : ''} aria-current={isGuide ? 'page' : undefined}>Guide</Link>
                    <a href={section('pricing')}>Pricing</a><a href={section('faq')}>FAQ</a>
                </nav>
                <div className="ms-auto d-flex align-items-center gap-1 gap-sm-2">
                    <Link href={route('guide')} className="btn btn-light d-md-none" aria-label="Guide" title="Guide"><i className="bi bi-book" /></Link>
                    <span className="d-none d-sm-inline-flex"><ThemeToggle /></span>
                    {signedIn ? <a href={route('dashboard')} className="btn btn-primary">Dashboard</a> : (
                        <>
                            <Link href={route('login')} className="btn btn-light d-none d-sm-inline-block">Sign in</Link>
                            <Link href={route('signup')} className="btn btn-primary">Get started</Link>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
