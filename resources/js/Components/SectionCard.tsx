import type { ReactNode } from 'react';

interface Props {
    icon?: string;
    title?: ReactNode;
    subtitle?: string;
    accent?: string;
    actions?: ReactNode;
    children: ReactNode;
    flush?: boolean;
}

/** Card with a gradient top border in the section's accent colour. */
export default function SectionCard({ icon, title, subtitle, accent, actions, children, flush }: Props) {
    return (
        <section className="app-card section-card mb-4" style={accent ? { ['--accent' as string]: accent } : undefined}>
            {title && (
                <div className="section-head">
                    {icon && (
                        <span className="tile">
                            <i className={`bi bi-${icon}`} />
                        </span>
                    )}
                    <div>
                        <h2>{title}</h2>
                        {subtitle && <small>{subtitle}</small>}
                    </div>
                    {actions && <div className="ms-auto d-flex gap-2">{actions}</div>}
                </div>
            )}
            {flush ? children : <div className="section-body">{children}</div>}
        </section>
    );
}
