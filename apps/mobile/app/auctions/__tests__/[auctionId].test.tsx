import '@/i18n';

import { fireEvent, render, screen } from '@testing-library/react-native';

import { useAuction } from '@/features/auctions/hooks/useAuction';
import { usePlaceBid } from '@/features/auctions/hooks/usePlaceBid';

import AuctionDetail from '../[auctionId]';

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ auctionId: 'auction-1' }),
}));

jest.mock('@/features/auctions/hooks/useAuction');
jest.mock('@/features/auctions/hooks/usePlaceBid');

describe('Auction detail screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('shows the current price and bid form for an open auction', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        id: 'auction-1',
        status: 'open',
        current_price: { amount_minor_units: 1100, currency: 'USD' },
        minimum_next_amount: { amount_minor_units: 1101, currency: 'USD' },
        closes_at: '2026-01-01T00:00:00Z',
        bid_count: 3,
      },
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
  });

  it('hides the bid form once the auction can no longer accept bids', async () => {
    (useAuction as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        id: 'auction-1',
        status: 'closing',
        current_price: { amount_minor_units: 1100, currency: 'USD' },
        minimum_next_amount: null,
        closes_at: '2026-01-01T00:00:00Z',
        bid_count: 3,
      },
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
      data: {
        id: 'auction-1',
        status: 'open',
        current_price: { amount_minor_units: 1100, currency: 'USD' },
        minimum_next_amount: { amount_minor_units: 1101, currency: 'USD' },
        closes_at: '2026-01-01T00:00:00Z',
        bid_count: 3,
      },
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
});
