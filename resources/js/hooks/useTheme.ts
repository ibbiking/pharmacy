import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { ThemePref } from '@/types';

const STORAGE_KEY = 'theme-pref';

function systemPrefersDark(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function applyTheme(pref: ThemePref): 'light' | 'dark' {
    const resolved = pref === 'dark' || (pref === 'system' && systemPrefersDark()) ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', resolved);
    window.dispatchEvent(new CustomEvent('app:theme', { detail: resolved }));
    return resolved;
}

function storedPref(fallback: ThemePref): ThemePref {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return v === 'light' || v === 'dark' || v === 'system' ? v : fallback;
    } catch {
        return fallback;
    }
}

/** Theme preference: applied instantly, cached per browser, saved to the user account. */
export function useTheme() {
    const { auth } = usePage().props;
    const [pref, setPref] = useState<ThemePref>(() => storedPref(auth.user?.theme ?? 'system'));
    const [resolved, setResolved] = useState<'light' | 'dark'>(() =>
        document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
    );

    useEffect(() => {
        setResolved(applyTheme(pref));
        if (pref !== 'system') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => setResolved(applyTheme('system'));
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [pref]);

    const choose = useCallback((next: ThemePref) => {
        setPref(next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            /* private mode: theme still applies for this page */
        }
        router.post(route('preferences.theme'), { theme: next }, { preserveScroll: true, preserveState: true, only: ['auth'] });
    }, []);

    return { pref, resolved, choose };
}

/** Current resolved theme, updated live (for charts and other canvas-drawn UI). */
export function useResolvedTheme(): 'light' | 'dark' {
    const [theme, setTheme] = useState<'light' | 'dark'>(() =>
        document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
    );
    useEffect(() => {
        const onTheme = (e: Event) => setTheme((e as CustomEvent<'light' | 'dark'>).detail);
        window.addEventListener('app:theme', onTheme);
        return () => window.removeEventListener('app:theme', onTheme);
    }, []);
    return theme;
}
