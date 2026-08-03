import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

// Backend and frontend namespaces are kept in step (see
// docs/architecture/localization.md). Each phase adds its own
// namespace's en/es resources here as that module's UI lands.
import auctionsEn from './lang/en/auctions.json';
import auditEn from './lang/en/audit.json';
import commonEn from './lang/en/common.json';
import disputesEn from './lang/en/disputes.json';
import paymentsEn from './lang/en/payments.json';
import presenceEn from './lang/en/presence.json';
import queuesEn from './lang/en/queues.json';
import transfersEn from './lang/en/transfers.json';
import auctionsEs from './lang/es/auctions.json';
import auditEs from './lang/es/audit.json';
import commonEs from './lang/es/common.json';
import disputesEs from './lang/es/disputes.json';
import paymentsEs from './lang/es/payments.json';
import presenceEs from './lang/es/presence.json';
import queuesEs from './lang/es/queues.json';
import transfersEs from './lang/es/transfers.json';

export const SUPPORTED_LOCALES = ['en', 'es'];
export const FALLBACK_LOCALE = 'en';

i18next.use(initReactI18next).init({
    resources: {
        en: { common: commonEn, queues: queuesEn, presence: presenceEn, disputes: disputesEn, audit: auditEn, auctions: auctionsEn, payments: paymentsEn, transfers: transfersEn },
        es: { common: commonEs, queues: queuesEs, presence: presenceEs, disputes: disputesEs, audit: auditEs, auctions: auctionsEs, payments: paymentsEs, transfers: transfersEs },
    },
    fallbackLng: FALLBACK_LOCALE,
    defaultNS: 'common',
    interpolation: {
        escapeValue: false,
    },
});

export default i18next;
