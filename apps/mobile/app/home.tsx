import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { useDiscoverQueues } from '@/features/auctions/hooks/useDiscoverQueues';
import { useLogout } from '@/features/auth/hooks/useLogout';
import { getCurrentCoordinates, LocationPermissionDeniedError } from '@/lib/location';
import { DiscoveredQueue } from '@/types/queues';

/**
 * Home/discovery (ADR-028 Sprint 2). Discovery returns queues, not
 * auctions — there is no backend endpoint linking a queue to whichever
 * auction is currently open against it (confirmed: DiscoverQueuesController's
 * response has no auction reference at all, and no auction-listing
 * endpoint exists anywhere — the same gap this project's own RC1
 * Functional Acceptance stage already recorded as FG-002, "no UI exists
 * to open an auction"). The queue list below is real and functional;
 * reaching a specific auction's live-bidding screen today requires
 * already knowing its id (the "view an auction by id" field), exactly
 * matching the web app's own current capability — not a shortcut
 * invented for mobile.
 */
export default function Home() {
  const { t } = useTranslation('queues');
  const { t: tTransfers } = useTranslation('transfers');
  const [coordinates, setCoordinates] = useState<{ latitude: number; longitude: number } | null>(
    null,
  );
  const [locationError, setLocationError] = useState<string | null>(null);
  const [auctionIdInput, setAuctionIdInput] = useState('');
  const [transferIdInput, setTransferIdInput] = useState('');
  const logout = useLogout();

  function locateAndFetch() {
    getCurrentCoordinates()
      .then(setCoordinates)
      .catch((error) => {
        setLocationError(
          error instanceof LocationPermissionDeniedError
            ? t('discovery.location_denied')
            : t('discovery.location_unavailable'),
        );
      });
  }

  // Mount-only: no eager `setLocationError(null)` here, since it's
  // already null on first render — that reset only matters for the
  // retry button below, which is an event handler, not an effect.
  useEffect(() => {
    locateAndFetch();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- intentionally run once on mount only; locateAndFetch is stable in behavior across renders.
  }, []);

  function requestLocation() {
    setLocationError(null);
    locateAndFetch();
  }

  const discovery = useDiscoverQueues(coordinates);

  function handleLogout() {
    logout.mutate(undefined, {
      onSettled: () => router.replace('/(auth)/login'),
    });
  }

  function renderQueue({ item }: { item: DiscoveredQueue }) {
    return (
      <View style={styles.queueRow} testID={`queue-${item.id}`}>
        <Text style={styles.queueCategory}>{item.category}</Text>
        <Text style={styles.queueDistance}>
          {t('discovery.distance_meters', { distance: Math.round(item.distance_meters) })}
        </Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('discovery.title')}</Text>
        <View style={styles.headerActions}>
          <Pressable
            onPress={() => router.push('/payment-method-setup')}
            testID="payment-method-link"
          >
            <Text style={styles.headerLink}>{t('discovery.payment_method_button')}</Text>
          </Pressable>
          <Pressable onPress={handleLogout} disabled={logout.isPending} testID="home-logout">
            <Text style={styles.logoutLabel}>Log out</Text>
          </Pressable>
        </View>
      </View>

      {locationError && (
        <View style={styles.centered}>
          <Text style={styles.error}>{locationError}</Text>
          <Pressable style={styles.button} onPress={requestLocation} testID="discovery-retry">
            <Text style={styles.buttonText}>{t('discovery.retry_button')}</Text>
          </Pressable>
        </View>
      )}

      {!locationError && discovery.isLoading && (
        <View style={styles.centered}>
          <ActivityIndicator />
          <Text style={styles.subtitle}>{t('discovery.loading')}</Text>
        </View>
      )}

      {!locationError && discovery.isSuccess && discovery.data.data.length === 0 && (
        <View style={styles.centered}>
          <Text style={styles.subtitle}>{t('discovery.empty')}</Text>
        </View>
      )}

      {!locationError && discovery.isSuccess && discovery.data.data.length > 0 && (
        <FlatList
          data={discovery.data.data}
          keyExtractor={(item) => item.id}
          renderItem={renderQueue}
          testID="queue-list"
        />
      )}

      <View style={styles.auctionLookup}>
        <Text style={styles.label}>{t('discovery.view_auction_by_id')}</Text>
        <View style={styles.auctionLookupRow}>
          <TextInput
            style={styles.input}
            value={auctionIdInput}
            onChangeText={setAuctionIdInput}
            placeholder={t('discovery.auction_id_placeholder')}
            autoCapitalize="none"
            testID="auction-id-input"
          />
          <Pressable
            style={styles.button}
            onPress={() => router.push(`/auctions/${auctionIdInput.trim()}`)}
            disabled={auctionIdInput.trim().length === 0}
            testID="view-auction-button"
          >
            <Text style={styles.buttonText}>{t('discovery.view_button')}</Text>
          </Pressable>
        </View>
      </View>

      <View style={styles.auctionLookup}>
        <Text style={styles.label}>{tTransfers('lookup.view_transfer_by_id')}</Text>
        <View style={styles.auctionLookupRow}>
          <TextInput
            style={styles.input}
            value={transferIdInput}
            onChangeText={setTransferIdInput}
            placeholder={tTransfers('lookup.transfer_id_placeholder')}
            autoCapitalize="none"
            testID="transfer-id-input"
          />
          <Pressable
            style={styles.button}
            onPress={() => router.push(`/transfers/${transferIdInput.trim()}`)}
            disabled={transferIdInput.trim().length === 0}
            testID="view-transfer-button"
          >
            <Text style={styles.buttonText}>{tTransfers('lookup.view_button')}</Text>
          </Pressable>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    paddingTop: 48,
    paddingHorizontal: 16,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: '600',
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  headerLink: {
    color: '#2563eb',
    fontWeight: '600',
  },
  logoutLabel: {
    color: '#dc2626',
    fontWeight: '600',
  },
  centered: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 32,
    gap: 8,
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  error: {
    fontSize: 14,
    color: '#dc2626',
    textAlign: 'center',
  },
  queueRow: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  queueCategory: {
    fontSize: 15,
    fontWeight: '500',
    textTransform: 'capitalize',
  },
  queueDistance: {
    fontSize: 13,
    color: '#666',
  },
  auctionLookup: {
    borderTopWidth: 1,
    borderTopColor: '#eee',
    paddingVertical: 16,
    marginTop: 8,
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    marginBottom: 8,
  },
  auctionLookupRow: {
    flexDirection: 'row',
    gap: 8,
  },
  input: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  button: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 10,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
