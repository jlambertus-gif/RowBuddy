import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useAuction } from '@/features/auctions/hooks/useAuction';
import { usePlaceBid } from '@/features/auctions/hooks/usePlaceBid';
import { useIsOnline } from '@/lib/network';
import { useStalenessLabel } from '@/lib/useStalenessLabel';
import { Money } from '@/types/auctions';

function formatMoney(money: Money): string {
  return `${(money.amount_minor_units / 100).toFixed(2)} ${money.currency}`;
}

const STATUS_KEY: Record<string, string> = {
  open: 'detail.status_open',
  closing: 'detail.status_closing',
  won: 'detail.status_won',
  expired: 'detail.status_expired',
  cancelled: 'detail.status_cancelled',
};

export default function AuctionDetail() {
  const { t } = useTranslation('auctions');
  const { t: tCommon } = useTranslation('common');
  const { auctionId } = useLocalSearchParams<{ auctionId: string }>();
  const auction = useAuction(auctionId ?? '');
  const placeBid = usePlaceBid(auctionId ?? '');
  const isOnline = useIsOnline();
  const staleness = useStalenessLabel(auction.dataUpdatedAt);
  const [bidAmount, setBidAmount] = useState('');

  if (auction.isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" />
        <Text style={styles.subtitle}>{t('detail.loading')}</Text>
      </View>
    );
  }

  if (auction.isError || !auction.data) {
    const isNotFound = auction.error instanceof ApiError && auction.error.status === 404;
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>
          {isNotFound ? t('detail.not_found') : t('detail.load_error')}
        </Text>
        {!isNotFound && (
          <Pressable
            style={styles.bidButton}
            onPress={() => auction.refetch()}
            testID="auction-retry"
          >
            <Text style={styles.bidButtonText}>{tCommon('retry_button')}</Text>
          </Pressable>
        )}
      </View>
    );
  }

  const snapshot = auction.data;
  const canBid = snapshot.minimum_next_amount !== null;

  function handlePlaceBid() {
    const amountMinorUnits = Math.round(parseFloat(bidAmount) * 100);
    if (!Number.isFinite(amountMinorUnits) || amountMinorUnits <= 0) {
      return;
    }

    placeBid.mutate(
      { amount_minor_units: amountMinorUnits, currency: snapshot.current_price.currency },
      { onSuccess: () => setBidAmount('') },
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('detail.title')}</Text>
      <Text style={styles.status}>{t(STATUS_KEY[snapshot.status] ?? snapshot.status)}</Text>

      <View style={styles.priceBlock}>
        <Text style={styles.label}>{t('detail.current_price')}</Text>
        <Text style={styles.price} testID="current-price">
          {formatMoney(snapshot.current_price)}
        </Text>
        <Text style={styles.staleness} testID="auction-staleness">
          {staleness}
        </Text>
      </View>

      <Text style={styles.subtitle}>
        {snapshot.bid_count === 1
          ? t('detail.bid_count', { count: snapshot.bid_count })
          : t('detail.bid_count_plural', { count: snapshot.bid_count })}
      </Text>
      <Text style={styles.subtitle}>
        {t('detail.closes_at', { time: new Date(snapshot.closes_at).toLocaleString() })}
      </Text>

      {canBid && (
        <View style={styles.bidForm}>
          <Text style={styles.label}>{t('detail.bid_amount_label')}</Text>
          {snapshot.minimum_next_amount && (
            <Text style={styles.hint}>
              {t('detail.minimum_bid_hint', { amount: formatMoney(snapshot.minimum_next_amount) })}
            </Text>
          )}
          <TextInput
            style={styles.input}
            value={bidAmount}
            onChangeText={setBidAmount}
            keyboardType="decimal-pad"
            testID="bid-amount-input"
          />

          {!isOnline && <Text style={styles.error}>{tCommon('offline.banner')}</Text>}

          {placeBid.error instanceof ApiError && (
            <Text style={styles.error}>{placeBid.error.message}</Text>
          )}

          <Pressable
            style={styles.bidButton}
            onPress={handlePlaceBid}
            disabled={placeBid.isPending || !isOnline}
            testID="place-bid-button"
          >
            <Text style={styles.bidButtonText}>
              {placeBid.isPending ? t('detail.bidding') : t('detail.bid_button')}
            </Text>
          </Pressable>
        </View>
      )}
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
  },
  status: {
    fontSize: 14,
    color: '#2563eb',
    fontWeight: '600',
    textTransform: 'uppercase',
  },
  priceBlock: {
    marginTop: 16,
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666',
  },
  price: {
    fontSize: 32,
    fontWeight: '700',
  },
  staleness: {
    fontSize: 11,
    color: '#999',
    marginTop: 2,
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
  },
  bidForm: {
    marginTop: 24,
    gap: 8,
  },
  hint: {
    fontSize: 12,
    color: '#999',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  bidButton: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  bidButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
