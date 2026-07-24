import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

// Backend and frontend namespaces are kept in step (see
// docs/architecture/localization.md). Each phase adds its own
// namespace's en/es resources here as that module's UI lands.
import commonEn from './lang/en/common.json';
import commonEs from './lang/es/common.json';
import queuesEn from './lang/en/queues.json';
import queuesEs from './lang/es/queues.json';

export const SUPPORTED_LOCALES = ['en', 'es'];
export const FALLBACK_LOCALE = 'en';

i18next.use(initReactI18next).init({
    resources: {
        en: { common: commonEn, queues: queuesEn },
        es: { common: commonEs, queues: queuesEs },
    },
    fallbackLng: FALLBACK_LOCALE,
    defaultNS: 'common',
    interpolation: {
        escapeValue: false,
    },
});

export default i18next;
