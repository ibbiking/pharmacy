require('./bootstrap');

import React from 'react';
import { createRoot } from 'react-dom/client';
import { InertiaApp } from '@inertiajs/inertia-react';
import { InertiaProgress } from '@inertiajs/progress';

InertiaProgress.init({
    color: '#28a745',
    showSpinner: false,
});

const app = document.getElementById('app');
const root = createRoot(app);

root.render(
    <InertiaApp
        initialPage={JSON.parse(app.dataset.page)}
        resolveComponent={(name) => {
            const pages = require.context('./Pages', true, /\.jsx$/);
            return pages(`./${name}.jsx`).default;
        }}
    />
);
