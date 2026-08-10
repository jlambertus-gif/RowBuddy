import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useFileDispute } from '@/features/disputes/hooks/useFileDispute';
import { useSubmitRating } from '@/features/ratings/hooks/useSubmitRating';
import { useTransferRatings } from '@/features/ratings/hooks/useTransferRatings';
import { useConfirmTransferAsBuyer } from '@/features/transfers/hooks/useConfirmTransferAsBuyer';
import { useConfirmTransferAsSeller } from '@/features/transfers/hooks/useConfirmTransferAsSeller';
import { useRevealQrToken } from '@/features/transfers/hooks/useRevealQrToken';
import { useTransfer } from '@/features/transfers/hooks/useTransfer';
import { getCurrentCoordinates } from '@/lib/location';

import TransferDetail from '../[transferId]';

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ transferId: 'transfer-1' }),
  router: { push: jest.fn() },
}));
jest.mock('@/features/transfers/hooks/useTransfer');
jest.mock('@/features/transfers/hooks/useRevealQrToken');
jest.mock('@/features/transfers/hooks/useConfirmTransferAsSeller');
jest.mock('@/features/transfers/hooks/useConfirmTransferAsBuyer');
jest.mock('@/features/ratings/hooks/useTransferRatings');
jest.mock('@/features/ratings/hooks/useSubmitRating');
jest.mock('@/features/disputes/hooks/useFileDispute');
jest.mock('@/lib/location');
jest.mock('expo-camera', () => ({
  useCameraPermissions: jest.fn(),
  CameraView: 'CameraView',
}));
jest.mock('react-native-qrcode-svg', () => 'QRCode');
jest.mock('expo-screen-capture', () => ({
  preventScreenCaptureAsync: jest.fn().mockResolvedValue(undefined),
  allowScreenCaptureAsync: jest.fn().mockResolvedValue(undefined),
}));

const { useCameraPermissions } = jest.requireMock('expo-camera');
const ScreenCapture = jest.requireMock('expo-screen-capture');

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

  it('prevents screen capture only once the QR code is actually revealed, not before', async () => {
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

    expect(ScreenCapture.preventScreenCaptureAsync).not.toHaveBeenCalled();
  });

  it('re-allows screen capture once the QR code is no longer displayed', async () => {
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

    const view = await render(<TransferDetail />);

    await waitFor(() => expect(ScreenCapture.preventScreenCaptureAsync).toHaveBeenCalledTimes(1));

    await view.unmount();

    expect(ScreenCapture.allowScreenCaptureAsync).toHaveBeenCalledTimes(1);
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

  it('shows a not-found message for an unknown transfer, without a retry button', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: true,
      error: new ApiError('Not found', 404, null),
      data: undefined,
    });

    await render(<TransferDetail />);

    expect(screen.getByText('This transfer could not be found.')).toBeVisible();
    expect(screen.queryByTestId('transfer-retry')).toBeNull();
  });

  it('shows a generic load error with a retry button for a non-404 failure', async () => {
    const refetch = jest.fn();
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: true,
      error: new ApiError('Server error', 503, null),
      refetch,
      data: undefined,
    });

    await render(<TransferDetail />);
    await fireEvent.press(screen.getByTestId('transfer-retry'));

    expect(screen.getByText("We couldn't load this transfer.")).toBeVisible();
    expect(refetch).toHaveBeenCalledTimes(1);
  });

  it('shows a staleness label once the transfer loads', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      dataUpdatedAt: Date.now(),
      refetch: jest.fn(),
      data: baseTransfer({ role: 'buyer' }),
    });
    (useRevealQrToken as jest.Mock).mockReturnValue({
      data: undefined,
      isFetching: false,
      refetch: jest.fn(),
    });

    await render(<TransferDetail />);

    expect(screen.getByTestId('transfer-staleness')).toHaveTextContent('Updated 0s ago');
  });

  it('lets a participant submit a rating once the transfer is confirmed', async () => {
    const mutate = jest.fn();
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'buyer',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: { mine: null, counterpart: null, counterpart_submitted: false },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate, isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });

    await render(<TransferDetail />);
    await fireEvent.press(screen.getByTestId('score-option-4'));
    await fireEvent.press(screen.getByTestId('submit-rating-button'));

    expect(mutate).toHaveBeenCalledWith({ score: 4, comment: undefined }, expect.anything());
  });

  it('shows the submitted rating instead of the form once this participant has already rated', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'buyer',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: {
        mine: {
          id: 'rating-1',
          transfer_id: 'transfer-1',
          score: 5,
          comment: null,
          submitted_at: '2026-01-01T00:00:00Z',
        },
        counterpart: null,
        counterpart_submitted: false,
      },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });

    await render(<TransferDetail />);

    expect(screen.getByTestId('my-rating')).toBeVisible();
    expect(screen.queryByTestId('submit-rating-button')).toBeNull();
  });

  it('distinguishes an unsubmitted counterpart rating from a submitted-but-unrevealed one', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'seller',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: { mine: null, counterpart: null, counterpart_submitted: true },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });

    await render(<TransferDetail />);

    expect(screen.getByText("The other party's rating will appear once revealed.")).toBeVisible();
  });

  it('shows "Report a problem" for the buyer once confirmed', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'buyer',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: { mine: null, counterpart: null, counterpart_submitted: false },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });

    await render(<TransferDetail />);

    expect(screen.getByTestId('open-report-problem')).toBeVisible();
  });

  it('never shows "Report a problem" for the seller', async () => {
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'seller',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: { mine: null, counterpart: null, counterpart_submitted: false },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });

    await render(<TransferDetail />);

    expect(screen.queryByTestId('open-report-problem')).toBeNull();
  });

  it('files a dispute and navigates to its status screen on success', async () => {
    const mutate = jest.fn((_, options) => options?.onSuccess?.({ data: { id: 'dispute-1' } }));
    (useTransfer as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: baseTransfer({
        role: 'buyer',
        status: 'confirmed',
        buyer_confirmed: true,
        seller_confirmed: true,
      }),
    });
    (useTransferRatings as jest.Mock).mockReturnValue({
      isLoading: false,
      data: { mine: null, counterpart: null, counterpart_submitted: false },
    });
    (useSubmitRating as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useFileDispute as jest.Mock).mockReturnValue({ mutate, isPending: false });
    const { router } = jest.requireMock('expo-router');

    await render(<TransferDetail />);
    await fireEvent.press(screen.getByTestId('open-report-problem'));
    await fireEvent.changeText(
      screen.getByTestId('dispute-reason-input'),
      'The seller never showed up at all.',
    );
    await fireEvent.press(screen.getByTestId('submit-dispute-button'));

    expect(mutate).toHaveBeenCalledWith(
      { reason: 'The seller never showed up at all.' },
      expect.anything(),
    );
    expect(router.push).toHaveBeenCalledWith('/disputes/dispute-1');
  });
});
