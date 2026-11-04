import type { ReactNode } from 'react';

interface Props {
    icon: string;
    label: string;
    value: ReactNode;
    accent?: string;
    selected?: boolean;
    onClick?: () => void;
}

/** Stat card with a coloured icon ring. Clickable when onClick is given (e.g. to filter a list). */
export default function KpiCard({ icon, label, value, accent = 'var(--app-primary)', selected, onClick }: Props) {
    const body = (
        <>
            <span className="ring">
                <i className={`bi bi-${icon}`} />
            </span>
            <span>
                <span className="label d-block">{label}</span>
                <span className="value d-block">{value}</span>
            </span>
        </>
    );
    const style = { ['--accent' as string]: accent };

    return onClick ? (
        <button type="button" className={`kpi-card border ${selected ? 'selected' : ''}`} style={style} onClick={onClick} aria-pressed={selected}>
            {body}
        </button>
    ) : (
        <div className="kpi-card" style={style}>
            {body}
        </div>
    );
}
