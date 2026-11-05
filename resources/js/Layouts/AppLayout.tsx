import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { NavItem } from '@/types';
import { initials } from '@/lib/format';
import Toaster from '@/Components/Toaster';
import ThemeToggle from '@/Components/ThemeToggle';

const COLLAPSE_KEY = 'sidebar-collapsed';

const TYPE_LABEL: Record<string, string> = { pharmacy: 'Pharmacy', inventory: 'Inventory', inventory_b2b: 'Inventory B2B' };

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
            <Sidebar collapsed={collapsed} mobileOpen={mobileOpen} onToggle={toggleCollapsed} />
            {mobileOpen && <div className="sidebar-backdrop d-lg-none" onClick={() => setMobileOpen(false)} />}
            <div className="app-main">
                <Topbar onOpenMobile={() => setMobileOpen(true)} />
                <main className="app-content" id="main">
                    {children}
                </main>
            </div>
            <Toaster />
        </div>
    );
}

function Sidebar({ collapsed, mobileOpen, onToggle }: { collapsed: boolean; mobileOpen: boolean; onToggle: () => void }) {
    const { navigation } = usePage().props;

    return (
        <aside className={`app-sidebar ${collapsed ? 'collapsed' : ''} ${mobileOpen ? 'mobile-open' : ''}`} aria-label="Main navigation">
            <div className="sidebar-head">
                <span className="brand-mark">
                    <i className="bi bi-capsule-pill" />
                </span>
                <span className="brand-name">PharmaSuite</span>
                <button type="button" className="sidebar-toggle d-none d-lg-inline-flex" onClick={onToggle} aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'} title={collapsed ? 'Expand' : 'Collapse'}>
                    <i className={`bi bi-chevron-double-${collapsed ? 'right' : 'left'}`} />
                </button>
            </div>

            <Workspace collapsed={collapsed} />

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

/** Current business card at the top of the sidebar: switch business or create a new one. */
function Workspace({ collapsed }: { collapsed: boolean }) {
    const { business, businesses } = usePage().props;
    if (!business) return null;
    const many = (businesses?.count ?? 0) > 1;

    return (
        <div className="dropdown workspace-dropdown">
            <button className="workspace" data-bs-toggle="dropdown" type="button" aria-expanded="false" title={collapsed ? business.name : 'Business menu'}>
                <span className="avatar-initials">{initials(business.name)}</span>
                <span className="ws-text">
                    <strong>{business.name}</strong>
                    <small>{TYPE_LABEL[business.type] ?? business.type}{business.impersonating ? ' · impersonating' : ''}</small>
                </span>
                <i className="bi bi-chevron-expand ws-chev" />
            </button>
            <div className="dropdown-menu shadow">
                {many && (
                    <>
                        <h6 className="dropdown-header">Switch business ({businesses!.count})</h6>
                        {businesses!.items.map((b) => (
                            <button
                                key={b.id}
                                type="button"
                                className={`dropdown-item d-flex align-items-center gap-2 ${b.id === business.id ? 'active' : ''}`}
                                onClick={() => router.post(route('business.switch'), { business_id: b.id })}
                            >
                                <span className="avatar-initials" style={{ width: 26, height: 26, fontSize: '0.7rem' }}>
                                    {initials(b.name)}
                                </span>
                                {b.name}
                            </button>
                        ))}
                        <div className="dropdown-divider" />
                    </>
                )}
                <Link className="dropdown-item" href={route('business.create')}>
                    <i className="bi bi-plus-circle me-2" />
                    Create new business
                </Link>
                <a className="dropdown-item" href={route('business.settings')}>
                    <i className="bi bi-gear me-2" />
                    Business settings
                </a>
            </div>
        </div>
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
    const className = `nav-link-item ${item.active ? 'active' : ''} ${item.locked ? 'locked' : ''}`;
    const content = (
        <>
            {item.icon && <i className={`bi bi-${item.icon}`} />}
            <span className="label">{item.label}</span>
            {item.locked && <i className="bi bi-lock-fill lock" title="Not in your plan: open Modules to switch it on" />}
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

function Topbar({ onOpenMobile }: { onOpenMobile: () => void }) {
    const { auth } = usePage().props;

    return (
        <header className="app-topbar">
            <button type="button" className="btn btn-icon btn-light d-lg-none" onClick={onOpenMobile} aria-label="Open menu">
                <i className="bi bi-list" />
            </button>

            <QuickSearch />

            <div className="ms-auto d-flex align-items-center gap-2">
                <ThemeToggle />
                {auth.user && (
                    <div className="dropdown">
                        <button className="topbar-pill dropdown-toggle" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                            <span className="avatar-initials" style={{ background: 'linear-gradient(135deg, var(--chart-4), var(--chart-7))' }}>
                                {initials(auth.user.name)}
                            </span>
                            <span className="d-none d-md-block">
                                <strong className="d-block text-truncate" style={{ maxWidth: 220 }}>{auth.user.name}</strong>
                                <small>{auth.user.roles[0] ?? 'User'}</small>
                            </span>
                        </button>
                        <div className="dropdown-menu dropdown-menu-end shadow">
                            <a className="dropdown-item" href={route('profile')}>
                                <i className="bi bi-person me-2" />
                                Profile
                            </a>
                            <button className="dropdown-item text-danger" type="button" onClick={() => router.post(route('logout'))}>
                                <i className="bi bi-box-arrow-right me-2" />
                                Log out
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </header>
    );
}

/** "Jump to" search over every screen the user can open (Ctrl/⌘ + K). */
function QuickSearch() {
    const { navigation } = usePage().props;
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [index, setIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);

    const entries = useMemo(() => {
        const list: { label: string; trail: string; item: NavItem }[] = [];
        navigation.forEach((g) =>
            g.items.forEach((item) => {
                if (item.href) list.push({ label: item.label, trail: g.group, item });
                item.children?.forEach((c) => c.href && list.push({ label: c.label, trail: `${g.group} › ${item.label}`, item: c }));
            }),
        );
        return list;
    }, [navigation]);

    const results = useMemo(() => {
        const term = q.trim().toLowerCase();
        if (!term) return entries.slice(0, 8);
        return entries.filter((e) => `${e.label} ${e.trail}`.toLowerCase().includes(term)).slice(0, 8);
    }, [q, entries]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
                setOpen(true);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const go = (item: NavItem) => {
        setOpen(false);
        setQ('');
        if (item.inertia) router.visit(item.href!);
        else window.location.href = item.href!;
    };

    return (
        <div className="quick-search">
            <i className="bi bi-search" />
            <input
                ref={inputRef}
                type="search"
                className="form-control"
                placeholder="Jump to a screen…"
                value={q}
                onChange={(e) => {
                    setQ(e.target.value);
                    setIndex(0);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') setIndex((i) => Math.min(i + 1, results.length - 1));
                    else if (e.key === 'ArrowUp') setIndex((i) => Math.max(i - 1, 0));
                    else if (e.key === 'Enter' && results[index]) go(results[index].item);
                    else if (e.key === 'Escape') (e.target as HTMLInputElement).blur();
                }}
                aria-label="Jump to a screen"
                role="combobox"
                aria-expanded={open}
            />
            <kbd className="d-none d-xl-inline">Ctrl K</kbd>
            {open && results.length > 0 && (
                <div className="quick-results" role="listbox">
                    {results.map((r, i) => (
                        <button key={`${r.trail}-${r.label}`} type="button" role="option" aria-selected={i === index} className={i === index ? 'active' : ''} onMouseDown={() => go(r.item)} onMouseEnter={() => setIndex(i)}>
                            <span>{r.label}</span>
                            <small>{r.trail}</small>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

