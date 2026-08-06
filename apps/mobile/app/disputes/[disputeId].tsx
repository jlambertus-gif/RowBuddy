import { useLocalSearchParams } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { useDispute } from '@/features/disputes/hooks/useDispute';

/** Buyer-only status view (ADR-028 §3) — GET /api/v1/disputes/{id}. */
export default function DisputeStatus() {
  const { t } = useTranslation('disputes');
  const { disputeId } = useLocalSearchParams<{ disputeId: string }>();
  const dispute = useDispute(disputeId ?? '');

  if (dispute.isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" />
        <Text style={styles.subtitle}>{t('status.loading')}</Text>
      </View>
    );
  }

  if (dispute.isError || !dispute.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>{t('status.load_error')}</Text>
      </View>
    );
  }

  const data = dispute.data;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('status.title')}</Text>

      <Text style={styles.label}>{t('status.reason_label')}</Text>
      <Text style={styles.value}>{data.reason}</Text>

      <Text style={styles.label}>{t('status.status_label')}</Text>
      <Text style={styles.value}>{t(`status.status_${data.status}`)}</Text>

      <Text style={styles.label}>{t('status.opened_at_label')}</Text>
      <Text style={styles.value}>{new Date(data.opened_at).toLocaleString()}</Text>

      {data.status === 'resolved' && (
        <>
          <Text style={styles.label}>{t('status.outcome_label')}</Text>
          <Text style={styles.value}>
            {data.resolution_outcome ? t(`status.outcome_${data.resolution_outcome}`) : ''}
          </Text>

          {data.refund_amount && (
            <>
              <Text style={styles.label}>{t('status.refund_amount_label')}</Text>
              <Text style={styles.value}>
                {(data.refund_amount.amount_minor_units / 100).toFixed(2)}{' '}
                {data.refund_amount.currency}
              </Text>
            </>
          )}

          {data.resolved_at && (
            <>
              <Text style={styles.label}>{t('status.resolved_at_label')}</Text>
              <Text style={styles.value}>{new Date(data.resolved_at).toLocaleString()}</Text>
            </>
          )}
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 24,
    gap: 4,
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
    marginBottom: 12,
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
  value: {
    fontSize: 16,
    fontWeight: '600',
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
  },
});
