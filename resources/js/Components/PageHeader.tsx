import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** `help` = a Help Center topic key: shows a "How it works" button. */
export default function PageHeader({ icon, title, description, actions, help }: { icon: string; title: string; description?: string; actions?: ReactNode; help?: string }) {
    return (
        <div className="page-header">
            <div className="icon-tile">
                <i className={`bi bi-${icon}`} />
            </div>
            <div>
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {(actions || help) && (
                <div className="actions">
                    {help && (
                        <Link href={route('help.show', help)} className="btn btn-light">
                            <i className="bi bi-life-preserver me-1" />
                            How it works
                        </Link>
                    )}
                    {actions}
                </div>
            )}
        </div>
    );
}
