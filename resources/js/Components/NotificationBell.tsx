import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

interface Item { id: string; title: string; lines: string[]; url: string | null; read: boolean; when: string | null }

/**
 * Top-bar bell (Roadmap 13c): this business's notifications (e.g. low stock after a sale), 10 at a time,
 * mark one / all read. The unread badge comes from the shared prop, so it follows the business switcher.
 */
export default function NotificationBell() {
    const { unreadNotifications, business } = usePage().props;
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<Item[]>([]);
    const [next, setNext] = useState<number | null>(null);
    const [unread, setUnread] = useState(unreadNotifications ?? 0);
    const [loading, setLoading] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => setUnread(unreadNotifications ?? 0), [unreadNotifications]);
    useEffect(() => { setItems([]); setNext(null); setOpen(false); }, [business?.id]);
    useEffect(() => {
        if (!open) return;
        const close = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const load = async (page = 1) => {
        setLoading(true);
        try {
            const { data } = await axios.get(route('notifications.index'), { params: { page } });
            setItems((prev) => (page === 1 ? data.data : [...prev, ...data.data]));
            setNext(data.next_page);
            setUnread(data.unread);
        } finally {
            setLoading(false);
        }
    };
    const toggle = () => { const o = !open; setOpen(o); if (o) void load(1); };
    const readOne = async (n: Item) => {
        if (n.read) return;
        const { data } = await axios.post(route('notifications.read', n.id));
        setUnread(data.unread);
        setItems((prev) => prev.map((i) => (i.id === n.id ? { ...i, read: true } : i)));
    };
    const readAll = async () => {
        await axios.post(route('notifications.read-all'));
        setUnread(0);
        setItems((prev) => prev.map((i) => ({ ...i, read: true })));
    };

    if (!business) return null;

    return (
        <div className="position-relative" ref={ref}>
            <button type="button" className="btn btn-icon btn-light position-relative" onClick={toggle} aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`} aria-expanded={open}>
                <i className="bi bi-bell" />
                {unread > 0 && (
                    <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill" style={{ background: 'var(--app-danger)', fontSize: '0.65rem' }}>
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>
            {open && (
                <div className="app-card shadow position-absolute end-0 mt-2 p-0" style={{ width: 'min(360px, calc(100vw - 32px))', zIndex: 1050, background: 'var(--app-surface)' }}>
                    <div className="d-flex align-items-center px-3 py-2" style={{ borderBottom: '1px solid var(--app-border)' }}>
                        <strong className="small">Notifications</strong>
                        {unread > 0 && <button type="button" className="btn btn-link btn-sm ms-auto p-0" onClick={readAll}>Mark all read</button>}
                    </div>
                    <div style={{ maxHeight: 380, overflowY: 'auto' }}>
                        {items.length === 0 && !loading && <div className="text-muted small text-center py-4">Nothing new. Low-stock alerts after a sale appear here.</div>}
                        {items.map((n) => (
                            <div key={n.id} className="px-3 py-2" style={{ borderBottom: '1px solid var(--app-border)', background: n.read ? undefined : 'var(--app-primary-soft)' }}>
                                <div className="d-flex align-items-start gap-2">
                                    <i className="bi bi-exclamation-triangle mt-1" style={{ color: 'var(--app-warning)' }} />
                                    <div className="flex-grow-1 small">
                                        <div className="fw-semibold">{n.title}</div>
                                        {n.lines.slice(0, 3).map((l) => <div key={l} className="text-muted">{l}</div>)}
                                        <div className="d-flex gap-3 mt-1">
                                            {n.url && <Link href={n.url} onClick={() => { void readOne(n); setOpen(false); }}>Open</Link>}
                                            {!n.read && <button type="button" className="btn btn-link btn-sm p-0" onClick={() => readOne(n)}>Mark read</button>}
                                            <span className="text-muted ms-auto">{n.when}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                        {loading && <div className="text-center py-3"><span className="spinner-border spinner-border-sm" /></div>}
                        {next && !loading && <button type="button" className="btn btn-link btn-sm w-100" onClick={() => load(next)}>Older</button>}
                    </div>
                </div>
            )}
        </div>
    );
}
