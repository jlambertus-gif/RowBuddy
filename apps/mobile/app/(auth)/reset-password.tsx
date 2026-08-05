import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useResetPassword } from '@/features/auth/hooks/useResetPassword';

/**
 * Reachable today via a manually-constructed deep link
 * (rowbuddy://reset-password?token=...&email=...) for testing, and
 * intended as the eventual destination once verified universal links
 * (ADR-028 Decision 7) let the OS hand the original emailed reset link
 * directly to the app instead of a browser — at that point this screen
 * needs no other change, since it already calls the real API directly.
 * Today's actual reset flow (custom-scheme development environment)
 * completes the reset on the web form instead; see
 * app/auth/callback.tsx.
 */
export default function ResetPassword() {
  const { t } = useTranslation('auth');
  const params = useLocalSearchParams<{ token?: string; email?: string }>();
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const resetPassword = useResetPassword();

  function fieldError(field: string): string | undefined {
    if (resetPassword.error instanceof ApiError) {
      const errors = (resetPassword.error.body as { errors?: Record<string, string[]> } | null)
        ?.errors;
      return errors?.[field]?.[0];
    }
    return undefined;
  }

  function handleSubmit() {
    if (!params.token || !params.email) {
      return;
    }

    resetPassword.mutate(
      {
        token: params.token,
        email: params.email,
        password,
        password_confirmation: passwordConfirmation,
      },
      {
        onSuccess: () => router.replace('/(auth)/login'),
      },
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('reset_password.title')}</Text>
      <Text style={styles.subtitle}>{t('reset_password.subtitle')}</Text>

      <View style={styles.field}>
        <Text style={styles.label}>{t('reset_password.password_label')}</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          testID="reset-password-password"
        />
        {fieldError('password') && <Text style={styles.error}>{fieldError('password')}</Text>}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>{t('reset_password.password_confirmation_label')}</Text>
        <TextInput
          style={styles.input}
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          secureTextEntry
          testID="reset-password-password-confirmation"
        />
      </View>

      <Pressable
        style={styles.button}
        onPress={handleSubmit}
        disabled={resetPassword.isPending || !params.token || !params.email}
        testID="reset-password-submit"
      >
        <Text style={styles.buttonText}>
          {resetPassword.isPending
            ? t('reset_password.submitting')
            : t('reset_password.submit_button')}
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 8,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
    marginBottom: 16,
  },
  field: {
    marginBottom: 12,
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    marginBottom: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  error: {
    color: '#dc2626',
    fontSize: 12,
    marginTop: 4,
  },
  button: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
