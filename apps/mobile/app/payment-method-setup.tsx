import { CardField, StripeProvider, useConfirmSetupIntent } from '@stripe/stripe-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useBeginPaymentMethodSetup } from '@/features/payments/hooks/useBeginPaymentMethodSetup';
import { useCompletePaymentMethodSetup } from '@/features/payments/hooks/useCompletePaymentMethodSetup';

/**
 * Mirrors web's Payments/SetupPaymentMethod.jsx exactly (ADR-028 Sprint
 * 3): raw card data never reaches Laravel — the Stripe React Native
 * SDK's CardField + confirmSetupIntent() confirm entirely client-side,
 * and only the resulting setup_intent id is ever posted to the backend.
 * StripeProvider is mounted here, not at the root layout, since its
 * publishableKey is only known once the backend's setup-intent response
 * arrives (matching web's own dynamic `loadStripe(publishableKey)`).
 */
export default function PaymentMethodSetup() {
  const { t } = useTranslation('payments');
  const beginSetup = useBeginPaymentMethodSetup();

  useEffect(() => {
    beginSetup.mutate();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentionally run once on mount only.
  }, []);

  if (beginSetup.isError) {
    const message =
      beginSetup.error instanceof ApiError ? beginSetup.error.message : t('setup.load_error');
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>{message}</Text>
      </View>
    );
  }

  if (!beginSetup.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" />
        <Text style={styles.subtitle}>{t('setup.loading')}</Text>
      </View>
    );
  }

  return (
    <StripeProvider publishableKey={beginSetup.data.publishable_key}>
      <PaymentMethodForm clientSecret={beginSetup.data.client_secret} />
    </StripeProvider>
  );
}

function PaymentMethodForm({ clientSecret }: { clientSecret: string }) {
  const { t } = useTranslation('payments');
  const { confirmSetupIntent, loading: confirming } = useConfirmSetupIntent();
  const completeSetup = useCompletePaymentMethodSetup();
  const [cardComplete, setCardComplete] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setError(null);

    const { setupIntent, error: stripeError } = await confirmSetupIntent(clientSecret, {
      paymentMethodType: 'Card',
    });

    if (stripeError || !setupIntent) {
      setError(stripeError?.message ?? t('setup.generic_error'));
      return;
    }

    try {
      await completeSetup.mutateAsync(setupIntent.id);
    } catch (completionError) {
      setError(
        completionError instanceof ApiError ? completionError.message : t('setup.generic_error'),
      );
    }
  }

  if (completeSetup.isSuccess) {
    return (
      <View style={styles.centered}>
        <Text style={styles.subtitle}>{t('setup.saved')}</Text>
      </View>
    );
  }

  const saving = confirming || completeSetup.isPending;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('setup.title')}</Text>

      <Text style={styles.label}>{t('setup.card_label')}</Text>
      <CardField
        postalCodeEnabled={false}
        onCardChange={(details) => setCardComplete(details.complete)}
        style={styles.cardField}
        testID="payment-card-field"
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <Pressable
        style={styles.button}
        onPress={handleSave}
        disabled={!cardComplete || saving}
        testID="save-card-button"
      >
        <Text style={styles.buttonText}>{saving ? t('setup.saving') : t('setup.save_button')}</Text>
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
    marginBottom: 16,
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666',
  },
  cardField: {
    height: 50,
    marginVertical: 12,
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
    marginVertical: 4,
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
