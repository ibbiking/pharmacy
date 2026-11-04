import type { ReactNode } from 'react';

export interface Stat {
    key: string;
    icon: string;
    label: string;
    value: ReactNode;
    accent?: string;
    hint?: string;
    /** When set, the chip is a filter toggle (e.g. show only "in use"). */
    onClick?: () => void;
    selected?: boolean;
}

/**
 * Compact, clickable stats shown above every list (standard for all listing screens):
 * one short row that scrolls horizontally on small screens instead of taking vertical space.
 */
export default function StatStrip({ stats }: { stats: Stat[] }) {
    return (
        <div className="stat-strip" role="group" aria-label="Summary">
            {stats.map((s) => {
                const body = (
                    <>
                        <span className="dot">
                            <i className={`bi bi-${s.icon}`} />
                        </span>
                        <span>
                            <span className="lbl d-block">{s.label}</span>
                            <span className="val d-block">{s.value}</span>
                        </span>
                        {s.hint && <span className="hint">{s.hint}</span>}
                    </>
                );
                const style = { ['--accent' as string]: s.accent ?? 'var(--app-primary)' };
                return s.onClick ? (
                    <button key={s.key} type="button" className={`stat-chip ${s.selected ? 'selected' : ''}`} style={style} onClick={s.onClick} aria-pressed={s.selected}>
                        {body}
                    </button>
                ) : (
                    <div key={s.key} className="stat-chip" style={style}>
                        {body}
                    </div>
                );
            })}
        </div>
    );
}
