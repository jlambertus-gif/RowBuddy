import { Link, router } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useRegister } from '@/features/auth/hooks/useRegister';

export default function Register() {
  const { t } = useTranslation('auth');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const register = useRegister();

  function fieldError(field: string): string | undefined {
    if (register.error instanceof ApiError) {
      const errors = (register.error.body as { errors?: Record<string, string[]> } | null)?.errors;
      return errors?.[field]?.[0];
    }
    return undefined;
  }

  function handleSubmit() {
    register.mutate(
      { name, email, password, password_confirmation: passwordConfirmation },
      {
        onSuccess: () => router.replace('/(auth)/verify-email'),
      },
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('register.title')}</Text>
      <Text style={styles.subtitle}>{t('register.subtitle')}</Text>

      <View style={styles.field}>
        <Text style={styles.label}>{t('register.name_label')}</Text>
        <TextInput
          style={styles.input}
          value={name}
          onChangeText={setName}
          testID="register-name"
        />
        {fieldError('name') && <Text style={styles.error}>{fieldError('name')}</Text>}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>{t('register.email_label')}</Text>
        <TextInput
          style={styles.input}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
          testID="register-email"
        />
        {fieldError('email') && <Text style={styles.error}>{fieldError('email')}</Text>}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>{t('register.password_label')}</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          testID="register-password"
        />
        {fieldError('password') && <Text style={styles.error}>{fieldError('password')}</Text>}
      </View>

      <View style={styles.field}>
        <Text style={styles.label}>{t('register.password_confirmation_label')}</Text>
        <TextInput
          style={styles.input}
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          secureTextEntry
          testID="register-password-confirmation"
        />
      </View>

      <Pressable
        style={styles.button}
        onPress={handleSubmit}
        disabled={register.isPending}
        testID="register-submit"
      >
        <Text style={styles.buttonText}>
          {register.isPending ? t('register.submitting') : t('register.submit_button')}
        </Text>
      </Pressable>

      <View style={styles.footerRow}>
        <Text>{t('register.have_account_prompt')} </Text>
        <Link href="/(auth)/login" style={styles.link}>
          {t('register.log_in_link')}
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
  },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 16,
  },
});
