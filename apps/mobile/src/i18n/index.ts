import * as Localization from 'expo-localization';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import enAuctions from './locales/en/auctions.json';
import enAuth from './locales/en/auth.json';
import en from './locales/en/common.json';
import enDisputes from './locales/en/disputes.json';
import enPayments from './locales/en/payments.json';
import enProfile from './locales/en/profile.json';
import enQueues from './locales/en/queues.json';
import enRatings from './locales/en/ratings.json';
import enTransfers from './locales/en/transfers.json';
import esAuctions from './locales/es/auctions.json';
import esAuth from './locales/es/auth.json';
import es from './locales/es/common.json';
import esDisputes from './locales/es/disputes.json';
import esPayments from './locales/es/payments.json';
import esProfile from './locales/es/profile.json';
import esQueues from './locales/es/queues.json';
import esRatings from './locales/es/ratings.json';
import esTransfers from './locales/es/transfers.json';

export const SUPPORTED_LOCALES = ['en', 'es'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];
export const FALLBACK_LOCALE: SupportedLocale = 'en';

function detectSupportedDeviceLocale(): SupportedLocale {
  const deviceLanguageTag = Localization.getLocales()[0]?.languageCode;
  return (SUPPORTED_LOCALES as readonly string[]).includes(deviceLanguageTag ?? '')
    ? (deviceLanguageTag as SupportedLocale)
    : FALLBACK_LOCALE;
}

/**
 * Unlike the web app's i18n.js (which never sets `lng` and always
 * resolves to `fallbackLng`, per FG-001 — locale switching is out of
 * scope for web), mobile explicitly detects the device locale on first
 * launch and sets it. ADR-028 Decision 4 makes mobile locale switching a
 * real, independent capability. The explicit in-app override
 * (`app/profile.tsx`, Sprint 4) calls `i18n.changeLanguage()` on top of
 * this initial detection.
 */
// eslint-disable-next-line import/no-named-as-default-member -- i18next's own documented chained-call pattern; `.use()` is a real instance method here, not the named export.
void i18n.use(initReactI18next).init({
  resources: {
    en: {
      common: en,
      auth: enAuth,
      queues: enQueues,
      auctions: enAuctions,
      payments: enPayments,
      transfers: enTransfers,
      ratings: enRatings,
      disputes: enDisputes,
      profile: enProfile,
    },
    es: {
      common: es,
      auth: esAuth,
      queues: esQueues,
      auctions: esAuctions,
      payments: esPayments,
      transfers: esTransfers,
      ratings: esRatings,
      disputes: esDisputes,
      profile: esProfile,
    },
  },
  lng: detectSupportedDeviceLocale(),
  fallbackLng: FALLBACK_LOCALE,
  defaultNS: 'common',
  interpolation: { escapeValue: false },
});

export default i18n;
