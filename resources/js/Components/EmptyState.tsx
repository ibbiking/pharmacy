import type { ReactNode } from 'react';

export default function EmptyState({ icon = 'inbox', title, hint, action }: { icon?: string; title: string; hint?: string; action?: ReactNode }) {
    return (
        <div className="empty-state">
            <div className="bubble">
                <i className={`bi bi-${icon}`} />
            </div>
            <h3 className="h6 fw-bold mb-1" style={{ color: 'var(--app-text)' }}>{title}</h3>
            {hint && <p className="mb-3 small">{hint}</p>}
            {action}
        </div>
    );
}
