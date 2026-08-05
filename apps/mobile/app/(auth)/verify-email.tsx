import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { useResendVerification } from '@/features/auth/hooks/useResendVerification';

export default function VerifyEmail() {
  const { t } = useTranslation('auth');
  const resend = useResendVerification();

  const alreadyVerified =
    resend.isSuccess && 'already_verified' in resend.data.data && resend.data.data.already_verified;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('verify_email.title')}</Text>
      <Text style={styles.body}>{t('verify_email.body')}</Text>

      {resend.isSuccess && (
        <Text style={styles.success}>
          {alreadyVerified
            ? t('verify_email.already_verified_message')
            : t('verify_email.sent_message')}
        </Text>
      )}

      <Pressable
        style={styles.button}
        onPress={() => {
          resend.mutate(undefined, {
            onSuccess: (response) => {
              if ('already_verified' in response.data && response.data.already_verified) {
                router.replace('/home');
              }
            },
          });
        }}
        disabled={resend.isPending}
        testID="verify-email-resend"
      >
        <Text style={styles.buttonText}>
          {resend.isPending ? t('verify_email.resending') : t('verify_email.resend_button')}
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
    gap: 12,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
  },
  body: {
    fontSize: 14,
    color: '#666',
  },
  success: {
    color: '#16a34a',
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
