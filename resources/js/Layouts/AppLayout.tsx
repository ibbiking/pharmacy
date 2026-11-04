import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import type { NavItem, ThemePref } from '@/types';
import { useTheme } from '@/hooks/useTheme';
import { initials } from '@/lib/format';
import Toaster from '@/Components/Toaster';

const COLLAPSE_KEY = 'sidebar-collapsed';

export default function AppLayout({ children }: { children: ReactNode }) {
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return localStorage.getItem(COLLAPSE_KEY) === '1';
        } catch {
            return false;
        }
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => router.on('navigate', () => setMobileOpen(false)), []);

    const toggleCollapsed = () => {
        setCollapsed((c) => {
            try {
                localStorage.setItem(COLLAPSE_KEY, c ? '0' : '1');
            } catch {
                /* ignore */
            }
            return !c;
        });
    };

    return (
        <div className="app-shell">
            <Sidebar collapsed={collapsed} mobileOpen={mobileOpen} />
            {mobileOpen && <div className="sidebar-backdrop d-lg-none" onClick={() => setMobileOpen(false)} />}
            <div className="app-main">
                <Topbar onToggleSidebar={toggleCollapsed} onOpenMobile={() => setMobileOpen(true)} />
                <main className="app-content" id="main">
                    {children}
                </main>
            </div>
            <Toaster />
        </div>
    );
}

function Sidebar({ collapsed, mobileOpen }: { collapsed: boolean; mobileOpen: boolean }) {
    const { navigation } = usePage().props;

    return (
        <aside className={`app-sidebar ${collapsed ? 'collapsed' : ''} ${mobileOpen ? 'mobile-open' : ''}`} aria-label="Main navigation">
            <div className="brand">
                <span className="brand-mark">
                    <i className="bi bi-capsule-pill" />
                </span>
                <span className="brand-name">PharmaSuite</span>
            </div>
            <nav className="nav-scroll">
                {navigation.map((group) => (
                    <div key={group.group}>
                        <div className="group-label">{group.group}</div>
                        {group.items.map((item) => (
                            <SidebarItem key={item.label} item={item} collapsed={collapsed} />
                        ))}
                    </div>
                ))}
            </nav>
        </aside>
    );
}

function SidebarItem({ item, collapsed }: { item: NavItem; collapsed: boolean }) {
    const [open, setOpen] = useState(item.active);

    if (item.children?.length) {
        return (
            <div>
                <button
                    type="button"
                    className={`nav-link-item ${open ? 'open' : ''} ${item.active ? 'active' : ''}`}
                    onClick={() => setOpen((o) => !o)}
                    aria-expanded={open}
                    title={collapsed ? item.label : undefined}
                >
                    <i className={`bi bi-${item.icon ?? 'circle'}`} />
                    <span className="label">{item.label}</span>
                    <i className="bi bi-chevron-right chev" />
                </button>
                {open && !collapsed && (
                    <div className="sub">
                        {item.children.map((child) => (
                            <NavLink key={child.label} item={child} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return <NavLink item={item} collapsed={collapsed} />;
}

function NavLink({ item, collapsed = false }: { item: NavItem; collapsed?: boolean }) {
    const className = `nav-link-item ${item.active ? 'active' : ''}`;
    const content = (
        <>
            {item.icon && <i className={`bi bi-${item.icon}`} />}
            <span className="label">{item.label}</span>
        </>
    );

    // Screens not yet moved to Inertia are classic pages: open them with a full page load.
    return item.inertia ? (
        <Link href={item.href!} className={className} title={collapsed ? item.label : undefined}>
            {content}
        </Link>
    ) : (
        <a href={item.href} className={className} title={collapsed ? item.label : undefined}>
            {content}
        </a>
    );
}

function Topbar({ onToggleSidebar, onOpenMobile }: { onToggleSidebar: () => void; onOpenMobile: () => void }) {
    const { business, businesses, auth } = usePage().props;

    return (
        <header className="app-topbar">
            <button type="button" className="btn btn-icon btn-light d-none d-lg-inline-flex" onClick={onToggleSidebar} aria-label="Collapse sidebar">
                <i className="bi bi-layout-sidebar" />
            </button>
            <button type="button" className="btn btn-icon btn-light d-lg-none" onClick={onOpenMobile} aria-label="Open menu">
                <i className="bi bi-list" />
            </button>

            {business && (
                <div className="business-chip text-truncate">
                    <span className="avatar-initials">{initials(business.name)}</span>
                    <span className="text-truncate">{business.name}</span>
                    {business.impersonating && <span className="pill" style={{ ['--accent' as string]: 'var(--app-danger)' }}>Impersonating</span>}
                </div>
            )}

            <div className="ms-auto d-flex align-items-center gap-2">
                <ThemeToggle />
                {businesses && businesses.count > 1 && <BusinessSwitcher />}
                {auth.user && (
                    <div className="dropdown">
                        <button className="topbar-pill dropdown-toggle" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                            <span className="avatar-initials" style={{ background: 'linear-gradient(135deg, var(--chart-4), var(--chart-7))' }}>
                                {initials(auth.user.name)}
                            </span>
                            <span className="d-none d-md-block">
                                <strong className="d-block">{auth.user.name}</strong>
                                <small>{auth.user.roles[0] ?? 'User'}</small>
                            </span>
                        </button>
                        <DropdownMenu>
                            <a className="dropdown-item" href={route('profile')}>
                                <i className="bi bi-person me-2" />
                                Profile
                            </a>
                            <button className="dropdown-item text-danger" type="button" onClick={() => router.post(route('logout'))}>
                                <i className="bi bi-box-arrow-right me-2" />
                                Log out
                            </button>
                        </DropdownMenu>
                    </div>
                )}
            </div>
        </header>
    );
}

/** Bootstrap dropdown menu (behaviour from bootstrap.bundle, loaded in app.tsx). */
function DropdownMenu({ children }: { children: ReactNode }) {
    return <div className="dropdown-menu dropdown-menu-end shadow">{children}</div>;
}

function ThemeToggle() {
    const { pref, choose } = useTheme();
    const options: { value: ThemePref; icon: string; label: string }[] = [
        { value: 'light', icon: 'sun', label: 'Light' },
        { value: 'dark', icon: 'moon-stars', label: 'Dark' },
        { value: 'system', icon: 'circle-half', label: 'System' },
    ];

    return (
        <div className="btn-group" role="group" aria-label="Theme">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    className={`btn btn-sm ${pref === o.value ? 'btn-primary' : 'btn-outline-secondary'}`}
                    onClick={() => choose(o.value)}
                    title={`${o.label} theme`}
                    aria-pressed={pref === o.value}
                >
                    <i className={`bi bi-${o.icon}`} />
                </button>
            ))}
        </div>
    );
}

function BusinessSwitcher() {
    const { business, businesses } = usePage().props;

    return (
        <div className="dropdown">
            <button className="topbar-pill dropdown-toggle" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                <i className="bi bi-buildings" style={{ color: 'var(--app-primary)' }} />
                <span className="d-none d-md-block">
                    <strong className="d-block">Switch business</strong>
                    <small>{businesses!.count} businesses</small>
                </span>
            </button>
            <DropdownMenu>
                {businesses!.items.map((b) => (
                    <button
                        key={b.id}
                        type="button"
                        className={`dropdown-item d-flex align-items-center gap-2 ${b.id === business?.id ? 'active' : ''}`}
                        onClick={() => router.post(route('business.switch'), { business_id: b.id })}
                    >
                        <span className="avatar-initials" style={{ width: 26, height: 26, fontSize: '0.7rem' }}>
                            {initials(b.name)}
                        </span>
                        {b.name}
                    </button>
                ))}
            </DropdownMenu>
        </div>
    );
}
