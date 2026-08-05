import { Link, router } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useLogin } from '@/features/auth/hooks/useLogin';

export default function Login() {
  const { t } = useTranslation('auth');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const login = useLogin();

  function fieldError(field: string): string | undefined {
    if (login.error instanceof ApiError) {
      const errors = (login.error.body as { errors?: Record<string, string[]> } | null)?.errors;
      return errors?.[field]?.[0];
    }
    return undefined;
  }

  function handleSubmit() {
    login.mutate(
      { email, password },
      {
        onSuccess: (data) => {
          router.replace(data.user.email_verified_at ? '/home' : '/(auth)/verify-email');
        },
      },
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('login.title')}</Text>
      <Text style={styles.subtitle}>{t('login.subtitle')}</Text>

      <View style={styles.field}>
        <Text style={styles.label}>{t('login.email_label')}</Text>
        <TextInput
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          testID="login-email"
        />
        {fieldError('email') && <Text style={styles.error}>{fieldError('email')}</Text>}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>{t('login.password_label')}</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          testID="login-password"
        />
      </View>

      <Pressable
        style={styles.button}
        onPress={handleSubmit}
        disabled={login.isPending}
        testID="login-submit"
      >
        <Text style={styles.buttonText}>
          {login.isPending ? t('login.submitting') : t('login.submit_button')}
        </Text>
      </Pressable>

      <Link href="/(auth)/forgot-password" style={styles.link}>
        {t('login.forgot_password_link')}
      </Link>

      <View style={styles.footerRow}>
        <Text>{t('login.no_account_prompt')} </Text>
        <Link href="/(auth)/register" style={styles.link}>
          {t('login.sign_up_link')}
        </Link>
      </View>
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
  link: {
    color: '#2563eb',
    marginTop: 16,
    textAlign: 'center',
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 8,
  },
});
