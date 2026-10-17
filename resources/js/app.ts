import { createInertiaApp } from '@inertiajs/vue3';
import { installI18n } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Abonemen';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    withApp: (app, { page }) => installI18n(app, String(page.props.locale)),
});
