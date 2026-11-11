import { Link, router } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useId, useMemo, useRef, useState, type AnchorHTMLAttributes, type MouseEvent, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { findPage } from '@/lib/pages';

/**
 * Side drawer: opens any screen of the app from the right, on top of the current page, without leaving it
 * (setup wizard, sale preferences, add stock, edit, details …). The screen is the same Inertia page used for the
 * full-page URL, so nothing is built twice.
 *
 * How it works:
 * - `openDrawer(url)` asks the server for that page as Inertia JSON and renders its component here (without the app shell).
 * - While a drawer is open, every save (POST/PUT/PATCH/DELETE) carries `X-Drawer-Return: <current page>`; the server
 *   (`App\Http\Middleware\DrawerRedirect`) sends a successful save back to the page underneath, which reloads with
 *   fresh data and the success toast. The drawer then closes (or goes back one level and refreshes it).
 * - Validation errors come back on the page underneath; the drawer screen reads them from `usePage()` as usual.
 * - A normal link inside the drawer opens its page normally and closes the drawer.
 */

interface Entry { key: number; url: string; title?: string; width?: number; component?: string; props?: Record<string, unknown>; error?: string }
interface DrawerApi { open: (url: string, opts?: { title?: string; width?: number }) => void; close: () => void; back: () => void; replace: (url: string) => void; stack: Entry[] }
interface EmbeddedApi { embedded: boolean; close: () => void; visit: (url: string) => void }

const DrawerContext = createContext<DrawerApi | null>(null);
const EmbeddedContext = createContext<EmbeddedApi>({ embedded: false, close: () => {}, visit: (url) => router.visit(url) });

export const useDrawer = () => useContext(DrawerContext);
/** Inside a drawer? Pages use this to hide their "back" link and turn Cancel into "close the drawer". */
export const useEmbedded = () => useContext(EmbeddedContext);

let seq = 0;

export function DrawerProvider({ children, version }: { children: ReactNode; version: string | null }) {
    const [stack, setStack] = useState<Entry[]>([]);
    const stackRef = useRef(stack);
    stackRef.current = stack;
    const basePath = useRef<string | null>(null);
    const saving = useRef(false);

    const load = useCallback(async (entry: Entry) => {
        try {
            const res = await fetch(entry.url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Version': version ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html, application/xhtml+xml',
                },
                credentials: 'same-origin',
            });
            if (res.status === 409 || !res.headers.get('X-Inertia')) {
                // New build deployed, or a classic (non-Inertia) page: open it normally.
                setStack([]);
                router.visit(entry.url);
                return;
            }
            const page = (await res.json()) as { component: string; props: Record<string, unknown> };
            setStack((s) => s.map((e) => (e.key === entry.key ? { ...e, component: page.component, props: page.props, error: undefined } : e)));
        } catch {
            setStack((s) => s.map((e) => (e.key === entry.key ? { ...e, error: 'Could not load this screen. Check your connection and try again.' } : e)));
        }
    }, [version]);

    const open = useCallback((url: string, opts: { title?: string; width?: number } = {}) => {
        if (stackRef.current.length === 0) basePath.current = window.location.pathname;
        const entry: Entry = { key: ++seq, url, ...opts };
        setStack((s) => [...s, entry]);
        void load(entry);
    }, [load]);

    const close = useCallback(() => setStack([]), []);
    const back = useCallback(() => {
        setStack((s) => {
            const next = s.slice(0, -1);
            const top = next[next.length - 1];
            if (top) void load(top); // refresh what's underneath: it may have changed
            return next;
        });
    }, [load]);

    const replace = useCallback((url: string) => {
        const top = stackRef.current[stackRef.current.length - 1];
        if (!top) return;
        const entry = { ...top, url };
        setStack((s) => [...s.slice(0, -1), entry]);
        void load(entry);
    }, [load]);

    const replaceRef = useRef(replace);
    replaceRef.current = replace;

    // Saves made while a drawer is open come back to the page underneath (see DrawerRedirect).
    useEffect(() => {
        const offBefore = router.on('before', (event) => {
            if (stackRef.current.length === 0) return;
            const visit = event.detail.visit;
            if (visit.method !== 'get') {
                saving.current = true;
                visit.headers = { ...visit.headers, 'X-Drawer-Return': window.location.href };
            }
        });
        const offSuccess = router.on('success', (event) => {
            if (!saving.current) return;
            saving.current = false;
            const props = event.detail.page.props as { errors?: Record<string, string>; flash?: { drawer_next?: string | null } };
            if (Object.keys(props.errors ?? {}).length > 0) return;
            if (props.flash?.drawer_next) replaceRef.current(props.flash.drawer_next); // next step opens in the same drawer
            else back();
        });
        const offError = router.on('error', () => { saving.current = false; });
        const offNavigate = router.on('navigate', (event) => {
            if (stackRef.current.length === 0) return;
            const path = new URL(event.detail.page.url, window.location.origin).pathname;
            if (basePath.current && path !== basePath.current) setStack([]); // a link inside the drawer opened another page
        });
        return () => { offBefore(); offSuccess(); offError(); offNavigate(); };
    }, [back]);

    const api = useMemo(() => ({ open, close, back, replace, stack }), [open, close, back, replace, stack]);

    return (
        <DrawerContext.Provider value={api}>
            {children}
        </DrawerContext.Provider>
    );
}

/** Rendered by the app layout (inside the Inertia app, so drawer screens can use `usePage()`). */
export function DrawerOutlet() {
    const drawer = useDrawer();
    if (!drawer || drawer.stack.length === 0) return null;
    return <DrawerPanel stack={drawer.stack} onClose={drawer.close} onBack={drawer.back} onReplace={drawer.replace} />;
}

function DrawerPanel({ stack, onClose, onBack, onReplace }: { stack: Entry[]; onClose: () => void; onBack: () => void; onReplace: (url: string) => void }) {
    const top = stack[stack.length - 1]!;
    const titleId = useId();
    const panel = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            // Esc closes only when nothing inside (a dropdown, a dialog) handled it first.
            if (e.key === 'Escape' && !e.defaultPrevented && !document.querySelector('.modal-custom')) (stack.length > 1 ? onBack : onClose)();
        };
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panel.current?.focus();
        return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = prev; };
    }, [stack.length, onBack, onClose]);

    const Page = top.component ? findPage(top.component)?.default : undefined;

    return createPortal(
        <>
            <div className="drawer-backdrop" onClick={onClose} />
            <div ref={panel} tabIndex={-1} className="drawer-panel" role="dialog" aria-modal="true" aria-labelledby={titleId} style={top.width ? { ['--drawer-w' as string]: `${top.width}px` } : undefined}>
                <div className="drawer-head">
                    {stack.length > 1 && <button type="button" className="btn btn-icon btn-soft" onClick={onBack} aria-label="Back"><i className="bi bi-arrow-left" /></button>}
                    <h2 className="drawer-title" id={titleId}>{top.title ?? ''}</h2>
                    <a href={top.url} className="btn btn-icon btn-soft" title="Open as a full page" aria-label="Open as a full page" onClick={(e) => { e.preventDefault(); onClose(); router.visit(top.url); }}><i className="bi bi-arrows-angle-expand" /></a>
                    <button type="button" className="btn btn-icon btn-soft" onClick={onClose} aria-label="Close"><i className="bi bi-x-lg" /></button>
                </div>
                <div className="drawer-body">
                    {top.error ? (
                        <div className="callout" style={{ ['--accent' as string]: 'var(--app-danger)' }}><i className="bi bi-wifi-off" /><div><strong>Not loaded</strong><p>{top.error}</p></div></div>
                    ) : !Page || !top.props ? (
                        <DrawerSkeleton />
                    ) : (
                        <EmbeddedContext.Provider value={{ embedded: true, close: stack.length > 1 ? onBack : onClose, visit: onReplace }}>
                            <Page key={top.key} {...top.props} />
                        </EmbeddedContext.Provider>
                    )}
                </div>
            </div>
        </>,
        document.body,
    );
}

function DrawerSkeleton() {
    return (
        <div className="d-grid gap-3" aria-busy="true" aria-label="Loading">
            <div className="skeleton" style={{ height: 28, width: '45%' }} />
            <div className="skeleton" style={{ height: 120 }} />
            <div className="skeleton" style={{ height: 220 }} />
        </div>
    );
}

/**
 * A link that opens its page in the side drawer. Ctrl/⌘-click, middle-click and "open in new tab" still open the
 * full page, because it is a real link.
 */
export function DrawerLink({ href, title, width, children, onClick, ...rest }: AnchorHTMLAttributes<HTMLAnchorElement> & { href: string; title?: string; width?: number }) {
    const drawer = useDrawer();
    const handle = (e: MouseEvent<HTMLAnchorElement>) => {
        onClick?.(e);
        if (e.defaultPrevented || !drawer || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        drawer.open(href, { title: (rest['aria-label'] as string | undefined) ?? title, width });
    };
    return <a href={href} title={title} onClick={handle} {...rest}>{children}</a>;
}

/** "Cancel": closes the drawer when inside one, otherwise a normal link to `href`. */
export function CancelLink({ href, className = 'btn btn-light', children = 'Cancel' }: { href: string; className?: string; children?: ReactNode }) {
    const { embedded, close } = useEmbedded();
    if (embedded) return <button type="button" className={className} onClick={close}>{children}</button>;
    return <Link href={href} className={className}>{children}</Link>;
}

/** The small "← back" line above a page title; hidden inside a drawer (the drawer has its own back / close). */
export function BackLink({ href, children }: { href: string; children: ReactNode }) {
    const { embedded } = useEmbedded();
    if (embedded) return null;
    return <div className="mb-2"><Link href={href} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />{children}</Link></div>;
}
