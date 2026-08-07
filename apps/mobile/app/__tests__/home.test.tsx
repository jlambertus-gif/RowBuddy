import '@/i18n';

import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { useDiscoverQueues } from '@/features/auctions/hooks/useDiscoverQueues';
import { getCurrentCoordinates, LocationPermissionDeniedError } from '@/lib/location';

import Home from '../home';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn() },
}));
jest.mock('@/features/auctions/hooks/useDiscoverQueues');
jest.mock('@/lib/location');

describe('Home/discovery screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('lists discovered queues once location and discovery both succeed', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({
      isLoading: false,
      isSuccess: true,
      dataUpdatedAt: Date.now(),
      data: {
        data: [
          {
            id: 'queue-1',
            category: 'concert',
            jurisdiction_country: 'US',
            authorship: 'admin_curated',
            organizer_reference: 'venue-1',
            status: 'published',
            geofence: { latitude: 1, longitude: 2, radius_meters: 100 },
            distance_meters: 42.3,
          },
        ],
        meta: { page: 1, per_page: 20, total: 1, has_more: false },
      },
    });

    await render(<Home />);

    await waitFor(() => expect(screen.getByTestId('queue-queue-1')).toBeVisible());
    expect(screen.getByText('42 m away')).toBeVisible();
    expect(screen.getByTestId('discovery-staleness')).toBeVisible();
  });

  it('shows a retry button when discovery itself fails, distinct from a location error', async () => {
    const refetch = jest.fn();
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({
      isLoading: false,
      isSuccess: false,
      isError: true,
      refetch,
    });

    await render(<Home />);
    await waitFor(() => expect(screen.getByTestId('discovery-load-retry')).toBeVisible());
    await fireEvent.press(screen.getByTestId('discovery-load-retry'));

    expect(screen.getByText('Could not search for queues. Please try again.')).toBeVisible();
    expect(refetch).toHaveBeenCalledTimes(1);
  });

  it('shows a location-denied message and a retry button when permission is refused', async () => {
    (getCurrentCoordinates as jest.Mock).mockRejectedValue(new LocationPermissionDeniedError());
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });

    await render(<Home />);

    await waitFor(() => expect(screen.getByTestId('discovery-retry')).toBeVisible());
  });

  it('navigates to payment method setup from the header link', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });
    const { router } = jest.requireMock('expo-router');

    await render(<Home />);
    await fireEvent.press(screen.getByTestId('payment-method-link'));

    expect(router.push).toHaveBeenCalledWith('/payment-method-setup');
  });

  it('navigates to queue submission from the header link', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });
    const { router } = jest.requireMock('expo-router');

    await render(<Home />);
    await fireEvent.press(screen.getByTestId('submit-queue-link'));

    expect(router.push).toHaveBeenCalledWith('/queues/submit');
  });

  it('navigates to the profile screen from the header link', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });
    const { router } = jest.requireMock('expo-router');

    await render(<Home />);
    await fireEvent.press(screen.getByTestId('profile-link'));

    expect(router.push).toHaveBeenCalledWith('/profile');
  });

  it('navigates to a transfer by entered ID, matching the auction-lookup stopgap pattern', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });
    const { router } = jest.requireMock('expo-router');

    await render(<Home />);

    expect(screen.getByTestId('view-transfer-button')).toBeDisabled();
    await fireEvent.changeText(screen.getByTestId('transfer-id-input'), 'transfer-1');
    await fireEvent.press(screen.getByTestId('view-transfer-button'));

    expect(router.push).toHaveBeenCalledWith('/transfers/transfer-1');
  });
});
