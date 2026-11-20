import type { ThemePref } from '@/types';
import { useTheme } from '@/hooks/useTheme';

const OPTIONS: { value: ThemePref; icon: string; label: string }[] = [
    { value: 'light', icon: 'sun', label: 'Light' },
    { value: 'soft', icon: 'cloud-sun', label: 'Off-white' },
    { value: 'dark', icon: 'moon-stars', label: 'Dark' },
];

/** Light / off-white / dark switch. Dark by default; saved to the user's own account when signed in, to this browser otherwise. */
export default function ThemeToggle() {
    const { pref, choose } = useTheme();

    return (
        <div className="theme-toggle" role="group" aria-label="Theme">
            {OPTIONS.map((o) => (
                <button key={o.value} type="button" className={pref === o.value ? 'active' : ''} onClick={() => choose(o.value)} title={`${o.label} theme`} aria-pressed={pref === o.value}>
                    <i className={`bi bi-${o.icon}`} />
                </button>
            ))}
        </div>
    );
}
