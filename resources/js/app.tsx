import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';

if (!import.meta.env.SSR) {
    configureEcho({
        broadcaster: 'reverb',
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});
