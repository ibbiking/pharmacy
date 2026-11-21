import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { initials } from '@/lib/format';
import Toaster from '@/Components/Toaster';
import ThemeToggle from '@/Components/ThemeToggle';

/** Shared props of the platform portal (HandlePlatformInertiaRequests). */
export interface PlatformProps {
    brand: string;
    admin: { id: number; name: string; email: string } | null;
    [key: string]: unknown;
}

const NAV: { group: string; items: { label: string; icon: string; route: string; path: string }[] }[] = [
    { group: 'Overview', items: [
        { label: 'Dashboard', icon: 'speedometer2', route: 'platform.dashboard', path: '/platform' },
        { label: 'Businesses', icon: 'buildings', route: 'platform.businesses', path: '/platform/businesses' },
        { label: 'People', icon: 'people', route: 'platform.people', path: '/platform/people' },
        { label: 'Visits', icon: 'graph-up-arrow', route: 'platform.visits', path: '/platform/visits' },
    ] },
    { group: 'Money', items: [
        { label: 'Plans & pricing', icon: 'tags', route: 'platform.billing', path: '/platform/billing' },
        { label: 'Subscriptions', icon: 'credit-card-2-front', route: 'platform.billing.subscriptions', path: '/platform/billing/subscriptions' },
    ] },
    { group: 'Platform', items: [
        { label: 'Global roles', icon: 'shield-check', route: 'platform.roles', path: '/platform/people/roles' },
        { label: 'App settings', icon: 'sliders2', route: 'platform.settings', path: '/platform/settings' },
        { label: 'Backups', icon: 'cloud-arrow-down', route: 'platform.backups', path: '/platform/backups' },
        { label: 'Platform admins', icon: 'person-lock', route: 'platform.admins', path: '/platform/admins' },
    ] },
];

/** The super admin portal shell: its own sidebar and top bar, separate from the business app. */
export default function PlatformLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { brand, admin } = page.props as unknown as PlatformProps;
    const path = page.url.split('?')[0];
    // The longest matching menu path wins (Subscriptions over Plans, Roles over People).
    const all = NAV.flatMap((g) => g.items);
    const active = all.filter((i) => path === i.path || (i.path !== '/platform' && path.startsWith(i.path + '/'))).sort((a, b) => b.path.length - a.path.length)[0]?.path;
    const [mobileOpen, setMobileOpen] = useState(false);
    useEffect(() => router.on('navigate', () => setMobileOpen(false)), []);

    return (
        <div className="app-shell platform-shell">
            <aside className={`app-sidebar ${mobileOpen ? 'mobile-open' : ''}`} aria-label="Platform navigation">
                <div className="sidebar-head">
                    <span className="brand-mark" style={{ background: 'linear-gradient(135deg, var(--chart-5), var(--chart-2))' }}><i className="bi bi-shield-lock" /></span>
                    <span className="brand-name">{brand} <small className="text-muted fw-normal">Platform</small></span>
                </div>
                <nav className="nav-scroll">
                    {NAV.map((g) => (
                        <div key={g.group}>
                            <div className="group-label">{g.group}</div>
                            {g.items.map((item) => (
                                <Link key={item.route} href={route(item.route)} className={`nav-link-item ${active === item.path ? 'active' : ''}`}>
                                    <i className={`bi bi-${item.icon}`} />
                                    <span className="label">{item.label}</span>
                                </Link>
                            ))}
                        </div>
                    ))}
                </nav>
            </aside>
            {mobileOpen && <div className="sidebar-backdrop d-lg-none" onClick={() => setMobileOpen(false)} />}
            <div className="app-main">
                <header className="app-topbar">
                    <button type="button" className="btn btn-icon btn-light d-lg-none" onClick={() => setMobileOpen(true)} aria-label="Open menu">
                        <i className="bi bi-list" />
                    </button>
                    <span className="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle"><i className="bi bi-shield-lock me-1" />Platform portal</span>
                    <div className="ms-auto d-flex align-items-center gap-2">
                        <ThemeToggle />
                        {admin && (
                            <div className="dropdown">
                                <button className="topbar-pill dropdown-toggle" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                                    <span className="avatar-initials" style={{ background: 'linear-gradient(135deg, var(--chart-5), var(--chart-2))' }}>{initials(admin.name)}</span>
                                    <span className="d-none d-md-block">
                                        <strong className="d-block text-truncate" style={{ maxWidth: 220 }}>{admin.name}</strong>
                                        <small>Platform admin</small>
                                    </span>
                                </button>
                                <div className="dropdown-menu dropdown-menu-end shadow">
                                    <span className="dropdown-item-text small text-muted">{admin.email}</span>
                                    <button className="dropdown-item text-danger" type="button" onClick={() => router.post(route('platform.logout'))}>
                                        <i className="bi bi-box-arrow-right me-2" />
                                        Log out
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </header>
                <main className="app-content" id="main">{children}</main>
            </div>
            <Toaster />
        </div>
    );
}
