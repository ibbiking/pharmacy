import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { ThemePref } from '@/types';

const STORAGE_KEY = 'theme-pref';

const THEMES: ThemePref[] = ['light', 'soft', 'dark'];

/** Bootstrap knows light / dark; the off-white theme is the light base with warm tokens (data-app-theme="soft"). */
export function applyTheme(pref: ThemePref): 'light' | 'dark' {
    const resolved = pref === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', resolved);
    document.documentElement.setAttribute('data-app-theme', pref);
    window.dispatchEvent(new CustomEvent('app:theme', { detail: resolved }));
    return resolved;
}

function storedPref(fallback: ThemePref): ThemePref {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return THEMES.includes(v as ThemePref) ? (v as ThemePref) : fallback;
    } catch {
        return fallback;
    }
}

/** Theme preference: dark by default, saved to the user's own account when signed in, cached in the browser otherwise. */
export function useTheme() {
    const { auth } = usePage().props;
    const signedIn = !!auth?.user;
    // Signed in: the user's own saved choice wins (a shared browser never shows another person's theme).
    // Signed out: this browser's last choice. Dark by default.
    const [pref, setPref] = useState<ThemePref>(() => (signedIn && THEMES.includes(auth?.user?.theme as ThemePref) ? (auth!.user!.theme as ThemePref) : storedPref('dark')));
    const [resolved, setResolved] = useState<'light' | 'dark'>(() =>
        document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
    );

    useEffect(() => {
        setResolved(applyTheme(pref));
    }, [pref]);

    const choose = useCallback((next: ThemePref) => {
        setPref(next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            /* private mode: theme still applies for this page */
        }
        // Signed-out pages (landing, sign-in) keep the choice in this browser only.
        if (signedIn) router.post(route('preferences.theme'), { theme: next }, { preserveScroll: true, preserveState: true, only: ['auth'] });
    }, [signedIn]);

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
