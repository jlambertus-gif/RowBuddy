import './bootstrap';
import '../css/app.css';
import './i18n';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import i18next from './i18n';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        const locale = props.initialPage?.props?.locale?.current;

        if (locale && i18next.language !== locale) {
            i18next.changeLanguage(locale);
        }

        createRoot(el).render(<App {...props} />);
    },
});
