import * as Localization from 'expo-localization';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import enAuth from './locales/en/auth.json';
import en from './locales/en/common.json';
import esAuth from './locales/es/auth.json';
import es from './locales/es/common.json';

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
 * real, independent capability. An explicit in-app override (Sprint 5,
 * `src/features/profile`) will call `i18n.changeLanguage()` on top of
 * this initial detection — not implemented in Sprint 0.
 */
// eslint-disable-next-line import/no-named-as-default-member -- i18next's own documented chained-call pattern; `.use()` is a real instance method here, not the named export.
void i18n.use(initReactI18next).init({
  resources: {
    en: { common: en, auth: enAuth },
    es: { common: es, auth: esAuth },
  },
  lng: detectSupportedDeviceLocale(),
  fallbackLng: FALLBACK_LOCALE,
  defaultNS: 'common',
  interpolation: { escapeValue: false },
});

export default i18n;
