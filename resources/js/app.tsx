import '@fontsource-variable/inter';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../css/app.css';
// Bootstrap behaviour (dropdowns, tooltips) via delegated data attributes; works alongside React.
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';

type PageModule = { default: ResolvedComponent };

createInertiaApp({
    title: (title) => (title ? `${title} · PharmaSuite` : 'PharmaSuite'),
    resolve: (name) => {
        const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        // Every page gets the app shell unless it declares its own layout.
        page.default.layout ??= (child: ReactNode) => <AppLayout>{child}</AppLayout>;
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el!).render(<App {...props} />);
    },
    progress: { color: '#8b5cf6', showSpinner: false },
});
