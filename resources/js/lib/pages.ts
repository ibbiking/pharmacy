import type { ComponentType } from 'react';

/** Every Inertia page, loaded once (shared by the app and the side drawer). */
export type PageComponent = ComponentType<Record<string, unknown>> & { layout?: unknown };
type PageModule = { default: PageComponent };

export const pages = import.meta.glob<PageModule>('../Pages/**/*.tsx', { eager: true });

export function findPage(name: string): PageModule | undefined {
    return pages[`../Pages/${name}.tsx`];
}
