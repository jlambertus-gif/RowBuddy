import { router } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useLogout } from '@/features/auth/hooks/useLogout';
import { useNotificationPermissionStatus } from '@/features/notifications/hooks/useNotificationPermissionStatus';
import { useRegisterPushNotifications } from '@/features/notifications/hooks/useRegisterPushNotifications';
import { useAccountStanding } from '@/features/profile/hooks/useAccountStanding';
import { useProfile } from '@/features/profile/hooks/useProfile';
import { useUpdateProfile } from '@/features/profile/hooks/useUpdateProfile';
import i18n, { SUPPORTED_LOCALES, SupportedLocale } from '@/i18n';
import { NotificationPermissionDeniedError } from '@/lib/pushNotifications';

/**
 * Mobile Sprint 4 (ADR-028 §3/§4). Mobile is the first-ever writer of
 * language/country_code/currency/timezone — selecting a language here
 * calls i18n.changeLanguage() immediately (instant UI feedback,
 * independent of the save button) in addition to being included in the
 * saved payload, matching ADR-028 Decision 4's "explicit, persistent
 * in-app override."
 */
export default function Profile() {
  const { t } = useTranslation('profile');
  const profile = useProfile();
  const accountStanding = useAccountStanding();
  const updateProfile = useUpdateProfile();
  const logout = useLogout();
  const permissionStatus = useNotificationPermissionStatus();
  const registerPush = useRegisterPushNotifications();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [language, setLanguage] = useState<SupportedLocale>(i18n.language as SupportedLocale);
  const [countryCode, setCountryCode] = useState('');
  const [currency, setCurrency] = useState('');
  const [timezone, setTimezone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pushError, setPushError] = useState<string | null>(null);
  // Tracks which profile.data snapshot the form fields were last hydrated
  // from — adjusting state directly during render (React's own
  // recommended pattern for "reset state when a prop changes") rather
  // than inside a useEffect, which would cause an extra render pass and
  // trip react-hooks/set-state-in-effect.
  const [hydratedFrom, setHydratedFrom] = useState<typeof profile.data>(undefined);

  if (profile.data && profile.data !== hydratedFrom) {
    setHydratedFrom(profile.data);
    setName(profile.data.name);
    setEmail(profile.data.email);
    setLanguage((profile.data.language as SupportedLocale) ?? (i18n.language as SupportedLocale));
    setCountryCode(profile.data.country_code ?? '');
    setCurrency(profile.data.currency ?? '');
    setTimezone(profile.data.timezone ?? '');
  }

  function selectLanguage(locale: SupportedLocale) {
    setLanguage(locale);
    void i18n.changeLanguage(locale);
  }

  function handleSave() {
    setError(null);

    updateProfile.mutate(
      {
        name,
        email,
        language,
        country_code: countryCode || undefined,
        currency: currency || undefined,
        timezone: timezone || undefined,
      },
      {
        onError: (saveError) => {
          setError(saveError instanceof ApiError ? saveError.message : t('generic_error'));
        },
      },
    );
  }

  async function handleEnableNotifications() {
    setPushError(null);

    try {
      await registerPush.mutateAsync();
    } catch (registerError) {
      setPushError(
        registerError instanceof NotificationPermissionDeniedError
          ? t('notifications_permission_denied')
          : t('generic_error'),
      );
    }
  }

  function handleLogout() {
    logout.mutate(undefined, {
      onSettled: () => router.replace('/(auth)/login'),
    });
  }

  if (profile.isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" />
        <Text style={styles.subtitle}>{t('loading')}</Text>
      </View>
    );
  }

  if (profile.isError || !profile.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>{t('load_error')}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('title')}</Text>

      {accountStanding.data && (
        <View testID="account-standing">
          <Text style={styles.label}>{t('account_status_label')}</Text>
          <Text style={accountStanding.data.state === 'suspended' ? styles.error : styles.subtitle}>
            {t(
              accountStanding.data.state === 'suspended'
                ? 'account_status_suspended'
                : 'account_status_active',
            )}
          </Text>
        </View>
      )}

      <Text style={styles.label}>{t('name_label')}</Text>
      <TextInput
        style={styles.input}
        value={name}
        onChangeText={setName}
        testID="profile-name-input"
      />

      <Text style={styles.label}>{t('email_label')}</Text>
      <TextInput
        style={styles.input}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
        testID="profile-email-input"
      />

      <Text style={styles.label}>{t('language_label')}</Text>
      <View style={styles.languageRow}>
        {SUPPORTED_LOCALES.map((locale) => (
          <Pressable
            key={locale}
            style={[styles.languageOption, language === locale && styles.languageOptionSelected]}
            onPress={() => selectLanguage(locale)}
            testID={`language-option-${locale}`}
          >
            <Text
              style={[
                styles.languageOptionText,
                language === locale && styles.languageOptionTextSelected,
              ]}
            >
              {locale === 'en' ? 'English' : 'Español'}
            </Text>
          </Pressable>
        ))}
      </View>

      <Text style={styles.label}>{t('country_code_label')}</Text>
      <TextInput
        style={styles.input}
        value={countryCode}
        onChangeText={(value) => setCountryCode(value.toUpperCase())}
        placeholder={t('country_code_placeholder')}
        autoCapitalize="characters"
        maxLength={2}
        testID="profile-country-input"
      />

      <Text style={styles.label}>{t('currency_label')}</Text>
      <TextInput
        style={styles.input}
        value={currency}
        onChangeText={(value) => setCurrency(value.toUpperCase())}
        placeholder={t('currency_placeholder')}
        autoCapitalize="characters"
        maxLength={3}
        testID="profile-currency-input"
      />

      <Text style={styles.label}>{t('timezone_label')}</Text>
      <TextInput
        style={styles.input}
        value={timezone}
        onChangeText={setTimezone}
        placeholder={t('timezone_placeholder')}
        autoCapitalize="none"
        testID="profile-timezone-input"
      />

      {error && <Text style={styles.error}>{error}</Text>}
      {updateProfile.isSuccess && <Text style={styles.success}>{t('saved')}</Text>}

      <Pressable
        style={styles.button}
        onPress={handleSave}
        disabled={updateProfile.isPending}
        testID="save-profile-button"
      >
        <Text style={styles.buttonText}>
          {updateProfile.isPending ? t('saving') : t('save_button')}
        </Text>
      </Pressable>

      <View style={styles.section}>
        <Text style={styles.label}>{t('notifications_title')}</Text>
        {permissionStatus.data ? (
          <Text style={styles.subtitle}>{t('notifications_enabled')}</Text>
        ) : (
          <>
            {pushError && <Text style={styles.error}>{pushError}</Text>}
            <Pressable
              style={styles.button}
              onPress={handleEnableNotifications}
              disabled={registerPush.isPending}
              testID="enable-notifications-button"
            >
              <Text style={styles.buttonText}>
                {registerPush.isPending
                  ? t('notifications_enabling')
                  : t('notifications_enable_button')}
              </Text>
            </Pressable>
          </>
        )}
      </View>

      <Pressable
        style={styles.logoutButton}
        onPress={handleLogout}
        disabled={logout.isPending}
        testID="profile-logout-button"
      >
        <Text style={styles.logoutButtonText}>{t('logout_button')}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 24,
    gap: 8,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    gap: 8,
  },
  title: {
    fontSize: 22,
    fontWeight: '600',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666',
    marginTop: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginTop: 4,
  },
  languageRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
  languageOption: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingVertical: 10,
    alignItems: 'center',
  },
  languageOptionSelected: {
    backgroundColor: '#2563eb',
    borderColor: '#2563eb',
  },
  languageOptionText: {
    color: '#333',
    fontWeight: '500',
  },
  languageOptionTextSelected: {
    color: '#fff',
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
    marginTop: 8,
  },
  success: {
    color: '#16a34a',
    fontSize: 13,
    marginTop: 8,
  },
  button: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 12,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
  section: {
    marginTop: 24,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    paddingTop: 16,
  },
  logoutButton: {
    marginTop: 24,
    alignItems: 'center',
    paddingVertical: 12,
  },
  logoutButtonText: {
    color: '#dc2626',
    fontWeight: '600',
  },
});
