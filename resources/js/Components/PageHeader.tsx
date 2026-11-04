import type { ReactNode } from 'react';

export default function PageHeader({ icon, title, description, actions }: { icon: string; title: string; description?: string; actions?: ReactNode }) {
    return (
        <div className="page-header">
            <div className="icon-tile">
                <i className={`bi bi-${icon}`} />
            </div>
            <div>
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions && <div className="actions">{actions}</div>}
        </div>
    );
}
