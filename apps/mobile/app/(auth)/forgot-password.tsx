import { Link } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { useForgotPassword } from '@/features/auth/hooks/useForgotPassword';

export default function ForgotPassword() {
  const { t } = useTranslation('auth');
  const [email, setEmail] = useState('');
  const forgotPassword = useForgotPassword();

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('forgot_password.title')}</Text>
      <Text style={styles.subtitle}>{t('forgot_password.subtitle')}</Text>

      {forgotPassword.isSuccess ? (
        <Text style={styles.success}>{t('forgot_password.success_message')}</Text>
      ) : (
        <>
          <View style={styles.field}>
            <Text style={styles.label}>{t('forgot_password.email_label')}</Text>
            <TextInput
              style={styles.input}
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
              testID="forgot-password-email"
            />
          </View>

          <Pressable
            style={styles.button}
            onPress={() => forgotPassword.mutate({ email })}
            disabled={forgotPassword.isPending}
            testID="forgot-password-submit"
          >
            <Text style={styles.buttonText}>
              {forgotPassword.isPending
                ? t('forgot_password.submitting')
                : t('forgot_password.submit_button')}
            </Text>
          </Pressable>
        </>
      )}

      <Link href="/(auth)/login" style={styles.link}>
        {t('forgot_password.back_to_login_link')}
      </Link>
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
  success: {
    color: '#16a34a',
    marginBottom: 16,
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
});
