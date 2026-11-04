import { useEffect, useId, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

interface Props {
    open: boolean;
    title: string;
    icon?: string;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    width?: number;
}

/** Accessible modal: Esc closes, focus moves inside, body scroll locked. */
export default function Modal({ open, title, icon, onClose, children, footer, width = 520 }: Props) {
    const titleId = useId();

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prev;
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <>
            <div className="modal-backdrop-custom" onClick={onClose} />
            <div className="modal-custom" role="dialog" aria-modal="true" aria-labelledby={titleId} onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
                <div className="modal-content" style={{ maxWidth: width }}>
                    <div className="modal-header border-0 pb-0">
                        <h5 className="modal-title d-flex align-items-center gap-2 fw-bold" id={titleId}>
                            {icon && <i className={`bi bi-${icon}`} style={{ color: 'var(--app-primary)' }} />}
                            {title}
                        </h5>
                        <button type="button" className="btn-close" aria-label="Close" onClick={onClose} />
                    </div>
                    <div className="modal-body">{children}</div>
                    {footer && <div className="modal-footer border-0 pt-0">{footer}</div>}
                </div>
            </div>
        </>,
        document.body,
    );
}
