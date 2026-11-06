import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = (import.meta.env.VITE_APP_NAME as string) || 'SpiggleMail';

createInertiaApp({
    title: (title: string) => title ? `${title} - ${appName}` : appName,
    resolve: (name: string) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#6366f1',
    },
});
