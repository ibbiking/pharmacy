import { useId, useState } from 'react';

/** Rough strength score 0–4 (length, letters + digits, mixed case, symbols). Server rules still decide. */
export function passwordScore(pw: string): number {
    if (!pw) return 0;
    let s = 0;
    if (pw.length >= 8) s++;
    if (/[a-zA-Z]/.test(pw) && /\d/.test(pw)) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/[^a-zA-Z0-9]/.test(pw) || pw.length >= 14) s++;
    return s;
}

const LABELS = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
const COLORS = ['var(--app-danger)', 'var(--app-danger)', 'var(--app-warning)', 'var(--app-info)', 'var(--app-success)'];

interface Props {
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    autoComplete?: string;
    meter?: boolean;
    autoFocus?: boolean;
    name?: string;
}

/** Password field with show/hide and an optional strength meter. */
export default function PasswordInput({ label, value, onChange, error, autoComplete = 'current-password', meter, autoFocus, name = 'password' }: Props) {
    const id = useId();
    const [shown, setShown] = useState(false);
    const score = passwordScore(value);

    return (
        <div>
            <label className="form-label fw-semibold mb-1" htmlFor={id}>{label}</label>
            <div className="input-icon-group">
                <i className="bi bi-lock lead-icon" />
                <input id={id} name={name} type={shown ? 'text' : 'password'} className={`form-control form-control-lg ${error ? 'is-invalid' : ''}`} value={value}
                    onChange={(e) => onChange(e.target.value)} autoComplete={autoComplete} autoFocus={autoFocus} maxLength={200} aria-invalid={!!error} />
                <button type="button" className="trail-btn" onClick={() => setShown((s) => !s)} aria-label={shown ? 'Hide password' : 'Show password'}>
                    <i className={`bi bi-${shown ? 'eye-slash' : 'eye'}`} />
                </button>
            </div>
            {meter && value && (
                <div className="pw-meter mt-2" aria-live="polite">
                    <div className="bars">{[0, 1, 2, 3].map((i) => <span key={i} style={{ background: i < score ? COLORS[score] : undefined }} />)}</div>
                    <small style={{ color: COLORS[score] }}>{LABELS[score]}</small>
                </div>
            )}
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </div>
    );
}
