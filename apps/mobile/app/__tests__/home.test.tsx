import '@/i18n';

import { render, screen, waitFor } from '@testing-library/react-native';

import { useDiscoverQueues } from '@/features/auctions/hooks/useDiscoverQueues';
import { useLogout } from '@/features/auth/hooks/useLogout';
import { getCurrentCoordinates, LocationPermissionDeniedError } from '@/lib/location';

import Home from '../home';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn() },
}));
jest.mock('@/features/auctions/hooks/useDiscoverQueues');
jest.mock('@/features/auth/hooks/useLogout');
jest.mock('@/lib/location');

describe('Home/discovery screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('lists discovered queues once location and discovery both succeed', async () => {
    (getCurrentCoordinates as jest.Mock).mockResolvedValue({ latitude: 1, longitude: 2 });
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useDiscoverQueues as jest.Mock).mockReturnValue({
      isLoading: false,
      isSuccess: true,
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
  });

  it('shows a location-denied message and a retry button when permission is refused', async () => {
    (getCurrentCoordinates as jest.Mock).mockRejectedValue(new LocationPermissionDeniedError());
    (useLogout as jest.Mock).mockReturnValue({ mutate: jest.fn(), isPending: false });
    (useDiscoverQueues as jest.Mock).mockReturnValue({ isLoading: false, isSuccess: false });

    await render(<Home />);

    await waitFor(() => expect(screen.getByTestId('discovery-retry')).toBeVisible());
  });
});
