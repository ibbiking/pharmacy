export interface BusinessType {
    key: string;
    name: string;
    icon: string;
    accent: string;
    tagline: string;
    description: string;
    examples: string[];
    bundle: string[];
    bundle_names: string[];
}

/** The business types as picture blocks (landing page, sign-up and the create-business wizard). */
export default function BusinessTypePicker({ types, value, onChange, compact }: { types: BusinessType[]; value: string | null; onChange: (key: string) => void; compact?: boolean }) {
    return (
        <div className="row g-3" role="radiogroup" aria-label="Type of business">
            {types.map((t) => {
                const selected = value === t.key;
                return (
                    <div className={compact ? 'col-12 col-sm-4' : 'col-md-4'} key={t.key}>
                        <button type="button" role="radio" aria-checked={selected} className={`type-block ${selected ? 'selected' : ''} ${compact ? 'compact' : ''}`}
                            style={{ ['--accent' as string]: t.accent }} onClick={() => onChange(t.key)}>
                            <span className="type-art" aria-hidden="true">
                                <span className="orb" /><i className={`bi bi-${t.icon}`} />
                            </span>
                            <span className="check"><i className={`bi bi-${selected ? 'check-circle-fill' : 'circle'}`} /></span>
                            <strong className="d-block">{t.name}</strong>
                            <span className="tagline">{t.tagline}</span>
                            {!compact && <span className="desc">{t.description}</span>}
                            {!compact && (
                                <span className="examples">
                                    {t.examples.map((e) => <span key={e}>{e}</span>)}
                                </span>
                            )}
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
