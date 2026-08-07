import '@/i18n';

import { act, fireEvent, render, screen } from '@testing-library/react-native';

import { ApiError } from '@/api/client';
import { useAuction } from '@/features/auctions/hooks/useAuction';
import { usePlaceBid } from '@/features/auctions/hooks/usePlaceBid';

import AuctionDetail from '../[auctionId]';

const netInfo = jest.requireMock('@react-native-community/netinfo');

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ auctionId: 'auction-1' }),
}));
jest.mock('@react-native-community/netinfo');

jest.mock('@/features/auctions/hooks/useAuction');
jest.mock('@/features/auctions/hooks/usePlaceBid');

function openAuctionSnapshot() {
  return {
    id: 'auction-1',
    status: 'open',
    current_price: { amount_minor_units: 1100, currency: 'USD' },
    minimum_next_amount: { amount_minor_units: 1101, currency: 'USD' },
    closes_at: '2026-01-01T00:00:00Z',
    bid_count: 3,
  };
}

describe('Auction detail screen', () => {
  afterEach(async () => {
    jest.clearAllMocks();
    await act(async () => {
      netInfo.__emit({ isConnected: true, isInternetReachable: true });
    });
  });

  it('shows the current price, staleness label, and bid form for an open auction', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      dataUpdatedAt: Date.now(),
      refetch: jest.fn(),
      data: openAuctionSnapshot(),
    });
    (usePlaceBid as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<AuctionDetail />);

    expect(screen.getByTestId('current-price')).toBeVisible();
    expect(screen.getByText('11.00 USD')).toBeVisible();
    expect(screen.getByTestId('place-bid-button')).toBeVisible();
    expect(screen.getByTestId('auction-staleness')).toHaveTextContent('Updated 0s ago');
  });

  it('hides the bid form once the auction can no longer accept bids', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      dataUpdatedAt: Date.now(),
      refetch: jest.fn(),
      data: { ...openAuctionSnapshot(), status: 'closing', minimum_next_amount: null },
    });
    (usePlaceBid as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<AuctionDetail />);

    expect(screen.queryByTestId('place-bid-button')).toBeNull();
  });

  it('submits the entered amount converted to minor units', async () => {
    const mutate = jest.fn();
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      dataUpdatedAt: Date.now(),
      refetch: jest.fn(),
      data: openAuctionSnapshot(),
    });
    (usePlaceBid as jest.Mock).mockReturnValue({ mutate, isPending: false, error: null });

    await render(<AuctionDetail />);

    await fireEvent.changeText(screen.getByTestId('bid-amount-input'), '12.50');
    await fireEvent.press(screen.getByTestId('place-bid-button'));

    expect(mutate).toHaveBeenCalledWith(
      { amount_minor_units: 1250, currency: 'USD' },
      expect.anything(),
    );
  });

  it('shows an offline notice and disables the bid button when connectivity drops', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      dataUpdatedAt: Date.now(),
      refetch: jest.fn(),
      data: openAuctionSnapshot(),
    });
    (usePlaceBid as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<AuctionDetail />);
    await act(async () => {
      netInfo.__emit({ isConnected: false, isInternetReachable: false });
    });

    expect(
      screen.getByText("You're offline. Some actions are unavailable until you reconnect."),
    ).toBeVisible();
    expect(screen.getByTestId('place-bid-button')).toBeDisabled();
  });

  it('shows a not-found message for a missing auction, without a retry button', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: true,
      error: new ApiError('Not found', 404, null),
      dataUpdatedAt: 0,
      refetch: jest.fn(),
      data: undefined,
    });
    (usePlaceBid as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<AuctionDetail />);

    expect(screen.getByText('This auction is no longer available.')).toBeVisible();
    expect(screen.queryByTestId('auction-retry')).toBeNull();
  });

  it('shows a generic load error with a retry button for a non-404 failure', async () => {
    const refetch = jest.fn();
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: true,
      error: new ApiError('Server error', 503, null),
      dataUpdatedAt: 0,
      refetch,
      data: undefined,
    });
    (usePlaceBid as jest.Mock).mockReturnValue({
      mutate: jest.fn(),
      isPending: false,
      error: null,
    });

    await render(<AuctionDetail />);
    await fireEvent.press(screen.getByTestId('auction-retry'));

    expect(screen.getByText("We couldn't load this auction.")).toBeVisible();
    expect(refetch).toHaveBeenCalledTimes(1);
  });
});
