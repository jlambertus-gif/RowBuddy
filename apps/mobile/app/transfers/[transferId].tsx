import { CameraView, useCameraPermissions } from 'expo-camera';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';

import { ApiError } from '@/api/client';
import { useConfirmTransferAsBuyer } from '@/features/transfers/hooks/useConfirmTransferAsBuyer';
import { useConfirmTransferAsSeller } from '@/features/transfers/hooks/useConfirmTransferAsSeller';
import { useRevealQrToken } from '@/features/transfers/hooks/useRevealQrToken';
import { useTransfer } from '@/features/transfers/hooks/useTransfer';
import { getCurrentCoordinates, LocationPermissionDeniedError } from '@/lib/location';

/**
 * Mirrors web's Transfers/Show.jsx (ADR-028 Sprint 3), with one
 * deliberate mobile-native upgrade: web displays/accepts the QR/
 * confirmation code as plain text (typed manually) because rendering an
 * actual QR barcode was out of its own Sprint 3 scope; this screen
 * renders a real QR code for the buyer (react-native-qrcode-svg) and
 * scans it with the camera for the seller (expo-camera), per this
 * sprint's explicit camera-permission scope item. The underlying
 * contract is unchanged — both paths exchange the exact same plaintext
 * qr_token string the backend already expects; nothing about the
 * backend confirmation protocol is new.
 */
export default function TransferDetail() {
  const { t } = useTranslation('transfers');
  const { transferId } = useLocalSearchParams<{ transferId: string }>();
  const id = transferId ?? '';
  const transfer = useTransfer(id);

  if (transfer.isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" />
        <Text style={styles.subtitle}>{t('detail.loading')}</Text>
      </View>
    );
  }

  if (transfer.isError || !transfer.data) {
    const message =
      transfer.error instanceof ApiError && transfer.error.status === 404
        ? t('detail.not_found')
        : t('detail.load_error');
    return (
      <View style={styles.centered}>
        <Text style={styles.error}>{message}</Text>
      </View>
    );
  }

  const data = transfer.data;
  const myConfirmation = data.role === 'seller' ? data.seller_confirmed : data.buyer_confirmed;
  const canConfirm = data.status === 'issued' && !myConfirmation;

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('detail.title')}</Text>
      <Text style={styles.subtitle}>
        {t(data.role === 'seller' ? 'detail.role_seller' : 'detail.role_buyer')}
      </Text>

      <View style={styles.statusGrid}>
        <View style={styles.statusItem}>
          <Text style={styles.label}>{t('detail.seller_confirmed_label')}</Text>
          <Text style={styles.value}>{t(data.seller_confirmed ? 'detail.yes' : 'detail.no')}</Text>
        </View>
        <View style={styles.statusItem}>
          <Text style={styles.label}>{t('detail.buyer_confirmed_label')}</Text>
          <Text style={styles.value}>{t(data.buyer_confirmed ? 'detail.yes' : 'detail.no')}</Text>
        </View>
        <View style={styles.statusItem}>
          <Text style={styles.label}>{t('detail.expires_at_label')}</Text>
          <Text style={styles.value}>{new Date(data.expires_at).toLocaleString()}</Text>
        </View>
        <View style={styles.statusItem}>
          <Text style={styles.label}>{t('detail.title')}</Text>
          <Text style={styles.value}>{t(`detail.status_${data.status}`)}</Text>
        </View>
      </View>

      {data.role === 'buyer' && data.status === 'issued' && <BuyerQrCode transferId={id} />}
      {canConfirm && data.role === 'seller' && <SellerConfirmForm transferId={id} />}
      {canConfirm && data.role === 'buyer' && <BuyerConfirmForm transferId={id} />}
    </View>
  );
}

function BuyerQrCode({ transferId }: { transferId: string }) {
  const { t } = useTranslation('transfers');
  const reveal = useRevealQrToken(transferId);

  return (
    <View style={styles.section}>
      {reveal.data ? (
        <View style={styles.centered} testID="qr-code-display">
          <Text style={styles.label}>{t('detail.your_code_label')}</Text>
          <QRCode value={reveal.data} size={200} />
        </View>
      ) : (
        <Pressable
          style={styles.button}
          onPress={() => reveal.refetch()}
          disabled={reveal.isFetching}
          testID="reveal-code-button"
        >
          <Text style={styles.buttonText}>{t('detail.reveal_code_button')}</Text>
        </Pressable>
      )}
    </View>
  );
}

function SellerConfirmForm({ transferId }: { transferId: string }) {
  const { t } = useTranslation('transfers');
  const [permission, requestPermission] = useCameraPermissions();
  const [scannedToken, setScannedToken] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const confirm = useConfirmTransferAsSeller(transferId);

  async function handleConfirm() {
    if (!scannedToken) {
      return;
    }

    setError(null);

    try {
      const coordinates = await getCurrentCoordinates();
      confirm.mutate({ qr_token: scannedToken, ...coordinates });
    } catch (locationError) {
      setError(
        locationError instanceof LocationPermissionDeniedError
          ? t('detail.location_denied')
          : t('detail.generic_error'),
      );
    }
  }

  if (!permission || !permission.granted) {
    return (
      <View style={styles.section}>
        <Text style={styles.subtitle}>{t('detail.camera_permission_denied')}</Text>
        <Pressable
          style={styles.button}
          onPress={requestPermission}
          testID="request-camera-permission"
        >
          <Text style={styles.buttonText}>{t('detail.camera_permission_button')}</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={styles.section}>
      <Text style={styles.label}>{t('detail.scan_code_label')}</Text>

      {scannedToken ? (
        <Pressable
          style={styles.button}
          onPress={() => setScannedToken(null)}
          testID="scan-again-button"
        >
          <Text style={styles.buttonText}>{t('detail.scan_again_button')}</Text>
        </Pressable>
      ) : (
        <CameraView
          style={styles.camera}
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
          onBarcodeScanned={({ data }) => setScannedToken(data)}
          testID="qr-scanner"
        />
      )}

      {(error || confirm.error instanceof ApiError) && (
        <Text style={styles.error}>{error ?? (confirm.error as ApiError).message}</Text>
      )}

      <Pressable
        style={styles.button}
        onPress={handleConfirm}
        disabled={!scannedToken || confirm.isPending}
        testID="confirm-transfer-button"
      >
        <Text style={styles.buttonText}>
          {confirm.isPending ? t('detail.confirming') : t('detail.confirm_button')}
        </Text>
      </Pressable>
    </View>
  );
}

function BuyerConfirmForm({ transferId }: { transferId: string }) {
  const { t } = useTranslation('transfers');
  const [error, setError] = useState<string | null>(null);
  const confirm = useConfirmTransferAsBuyer(transferId);

  async function handleConfirm() {
    setError(null);

    try {
      const coordinates = await getCurrentCoordinates();
      confirm.mutate(coordinates);
    } catch (locationError) {
      setError(
        locationError instanceof LocationPermissionDeniedError
          ? t('detail.location_denied')
          : t('detail.generic_error'),
      );
    }
  }

  return (
    <View style={styles.section}>
      {(error || confirm.error instanceof ApiError) && (
        <Text style={styles.error}>{error ?? (confirm.error as ApiError).message}</Text>
      )}

      <Pressable
        style={styles.button}
        onPress={handleConfirm}
        disabled={confirm.isPending}
        testID="confirm-transfer-button"
      >
        <Text style={styles.buttonText}>
          {confirm.isPending ? t('detail.confirming') : t('detail.confirm_button')}
        </Text>
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
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  title: {
    fontSize: 22,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  statusGrid: {
    marginTop: 16,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
  },
  statusItem: {
    minWidth: '40%',
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666',
  },
  value: {
    fontSize: 16,
    fontWeight: '600',
  },
  section: {
    marginTop: 24,
    gap: 12,
  },
  camera: {
    height: 280,
    borderRadius: 8,
    overflow: 'hidden',
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
  },
  button: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
