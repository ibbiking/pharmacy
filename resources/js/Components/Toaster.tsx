import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Kind = 'success' | 'error' | 'info';
interface Toast { id: number; kind: Kind; text: string }

const ACCENT: Record<Kind, string> = { success: 'var(--app-success)', error: 'var(--app-danger)', info: 'var(--app-info)' };
const ICON: Record<Kind, string> = { success: 'check-circle-fill', error: 'exclamation-octagon-fill', info: 'info-circle-fill' };

/** Shows server flash messages (success / error / message) and `app:toast` events as toasts. */
export default function Toaster() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState<Toast[]>([]);
    const seq = useRef(0);

    const push = useCallback((kind: Kind, text: string) => {
        const id = ++seq.current;
        setToasts((t) => [...t, { id, kind, text }]);
        window.setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 4000);
    }, []);

    useEffect(() => {
        if (flash.success) push('success', flash.success);
        if (flash.error) push('error', flash.error);
        if (flash.message) push('info', flash.message);
    }, [flash, push]);

    useEffect(() => {
        const onToast = (e: Event) => {
            const { kind, text } = (e as CustomEvent<{ kind: Kind; text: string }>).detail;
            push(kind, text);
        };
        window.addEventListener('app:toast', onToast);
        const offError = router.on('httpException', () => push('error', 'Something went wrong. Please try again.'));
        return () => {
            window.removeEventListener('app:toast', onToast);
            offError();
        };
    }, [push]);

    return (
        <div className="toast-stack" aria-live="polite">
            {toasts.map((t) => (
                <div key={t.id} className="app-toast" style={{ ['--accent' as string]: ACCENT[t.kind] }} role={t.kind === 'error' ? 'alert' : 'status'}>
                    <i className={`bi bi-${ICON[t.kind]}`} />
                    <div className="flex-grow-1">{t.text}</div>
                    <button type="button" className="btn-close btn-sm" aria-label="Dismiss" onClick={() => setToasts((x) => x.filter((y) => y.id !== t.id))} />
                </div>
            ))}
        </div>
    );
}

export function toast(kind: Kind, text: string) {
    window.dispatchEvent(new CustomEvent('app:toast', { detail: { kind, text } }));
}
