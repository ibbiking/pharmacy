import { router } from '@inertiajs/react';

export interface RangeFilters { range: string; from: string; to: string }

const PRESETS: [string, string][] = [['today', 'Today'], ['7d', '7 days'], ['30d', '30 days'], ['month', 'This month'], ['last_month', 'Last month'], ['custom', 'Custom']];

/** The shared date filter of every report (server computes the dates; the URL keeps them). */
export default function RangeFilter({ routeName, filters }: { routeName: string; filters: RangeFilters }) {
    const go = (patch: Partial<RangeFilters>) => router.get(route(routeName), { ...filters, ...patch, page: 1 }, { preserveScroll: true, preserveState: false, replace: true });

    return (
        <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div className="btn-group btn-group-sm flex-wrap" role="radiogroup" aria-label="Period">
                {PRESETS.map(([k, label]) => (
                    <button key={k} type="button" role="radio" aria-checked={filters.range === k} className={`btn ${filters.range === k ? 'btn-primary' : 'btn-light'}`}
                        onClick={() => go(k === 'custom' ? { range: 'custom' } : { range: k })}>{label}</button>
                ))}
            </div>
            <div className="d-flex align-items-center gap-1">
                <input type="date" className="form-control form-control-sm" style={{ width: 150 }} aria-label="From" value={filters.from} onChange={(e) => go({ range: 'custom', from: e.target.value })} />
                <span className="text-muted small">to</span>
                <input type="date" className="form-control form-control-sm" style={{ width: 150 }} aria-label="To" value={filters.to} onChange={(e) => go({ range: 'custom', to: e.target.value })} />
            </div>
        </div>
    );
}
