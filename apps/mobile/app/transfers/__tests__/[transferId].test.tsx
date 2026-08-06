import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useConfirmTransferAsBuyer } from '@/features/transfers/hooks/useConfirmTransferAsBuyer';
import { useConfirmTransferAsSeller } from '@/features/transfers/hooks/useConfirmTransferAsSeller';
import { useRevealQrToken } from '@/features/transfers/hooks/useRevealQrToken';
import { useTransfer } from '@/features/transfers/hooks/useTransfer';
import { getCurrentCoordinates } from '@/lib/location';

import TransferDetail from '../[transferId]';

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ transferId: 'transfer-1' }),
}));
jest.mock('@/features/transfers/hooks/useTransfer');
jest.mock('@/features/transfers/hooks/useRevealQrToken');
jest.mock('@/features/transfers/hooks/useConfirmTransferAsSeller');
jest.mock('@/features/transfers/hooks/useConfirmTransferAsBuyer');
jest.mock('@/lib/location');
jest.mock('expo-camera', () => ({
  useCameraPermissions: jest.fn(),
  CameraView: 'CameraView',
}));
jest.mock('react-native-qrcode-svg', () => 'QRCode');

const { useCameraPermissions } = jest.requireMock('expo-camera');

function baseTransfer(overrides: Record<string, unknown> = {}) {
  return {
    id: 'transfer-1',
    auction_id: 'auction-1',
    status: 'issued',
    seller_confirmed: false,
    buyer_confirmed: false,
    confirmed_at: null,
    expires_at: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('Transfer detail screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('shows a reveal-code button for the buyer, not a scanner', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'buyer' }),
    });
    (useRevealQrToken as jest.Mock).mockReturnValue({
      data: undefined,
      isFetching: false,
      refetch: jest.fn(),
    });
    (useConfirmTransferAsBuyer as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<TransferDetail />);

    expect(screen.getByTestId('reveal-code-button')).toBeVisible();
    expect(screen.queryByTestId('qr-scanner')).toBeNull();
  });

  it('renders a real QR code once the buyer reveals it', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'buyer' }),
    });
    (useRevealQrToken as jest.Mock).mockReturnValue({
      data: 'plaintext-token',
      isFetching: false,
      refetch: jest.fn(),
    });
    (useConfirmTransferAsBuyer as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<TransferDetail />);

    expect(screen.getByTestId('qr-code-display')).toBeVisible();
  });

  it("prompts for camera permission before showing the seller's scanner", async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'seller' }),
    });
    (useConfirmTransferAsSeller as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });
    useCameraPermissions.mockReturnValue([{ granted: false }, jest.fn()]);

    await render(<TransferDetail />);

    expect(screen.getByTestId('request-camera-permission')).toBeVisible();
    expect(screen.queryByTestId('qr-scanner')).toBeNull();
  });

  it('shows the scanner once camera permission is granted, then confirms with the scanned token and location', async () => {
    const mutate = jest.fn();
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'seller' }),
    });
    (useConfirmTransferAsSeller as jest.Mock).mockReturnValue({
      mutate,
      isPending: false,
      error: null,
    });
    useCameraPermissions.mockReturnValue([{ granted: true }, jest.fn()]);
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({
      latitude: 32.7157,
      longitude: -117.1611,
    });

    await render(<TransferDetail />);

    expect(screen.getByTestId('qr-scanner')).toBeVisible();

    await fireEvent(screen.getByTestId('qr-scanner'), 'onBarcodeScanned', {
      data: 'plaintext-token',
      type: 'qr',
    });
    await fireEvent.press(screen.getByTestId('confirm-transfer-button'));

    await waitFor(() =>
      expect(mutate).toHaveBeenCalledWith({
        qr_token: 'plaintext-token',
        latitude: 32.7157,
        longitude: -117.1611,
      }),
    );
  });

  it('hides the confirm form once this device has already confirmed', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'seller', seller_confirmed: true }),
    });
    (useConfirmTransferAsSeller as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });
    useCameraPermissions.mockReturnValue([{ granted: true }, jest.fn()]);

    await render(<TransferDetail />);

    expect(screen.queryByTestId('confirm-transfer-button')).toBeNull();
  });

  it('shows the backend error message when confirmation fails', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({ role: 'buyer' }),
    });
    (useRevealQrToken as jest.Mock).mockReturnValue({
      data: undefined,
      isFetching: false,
      refetch: jest.fn(),
    });
    (useConfirmTransferAsBuyer as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: new ApiError('Outside the handoff area.', 422, null),
    });

    await render(<TransferDetail />);

    expect(screen.getByText('Outside the handoff area.')).toBeVisible();
  });

  it('shows a not-found message for an unknown transfer', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: true,
      error: new ApiError('Not found', 404, null),
      data: undefined,
    });

    await render(<TransferDetail />);

    expect(screen.getByText('This transfer could not be found.')).toBeVisible();
  });
});
