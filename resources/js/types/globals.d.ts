import type { SharedProps } from './index';

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

declare global {
    /** Ziggy route helper (injected by the @routes Blade directive). */
    function route(name: string, params?: unknown, absolute?: boolean): string;
}

export {};
