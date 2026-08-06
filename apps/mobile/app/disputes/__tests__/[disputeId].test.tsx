import '@/i18n';

import { render, screen } from '@testing-library/react-native';

import { useDispute } from '@/features/disputes/hooks/useDispute';

import DisputeStatus from '../[disputeId]';

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ disputeId: 'dispute-1' }),
}));
jest.mock('@/features/disputes/hooks/useDispute');

describe('Dispute status screen', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('shows an opened dispute with no outcome yet', async () => {
    (useDispute as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        id: 'dispute-1',
        transfer_id: 'transfer-1',
        reason: 'The seller never showed up.',
        status: 'opened',
        opened_at: '2026-01-01T00:00:00Z',
        resolution_outcome: null,
        refund_amount: null,
        resolved_at: null,
      },
    });

    await render(<DisputeStatus />);

    expect(screen.getByText('The seller never showed up.')).toBeVisible();
    expect(screen.getByText('Under review')).toBeVisible();
  });

  it('shows the resolution outcome and refund amount once resolved', async () => {
    (useDispute as jest.Mock).mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        id: 'dispute-1',
        transfer_id: 'transfer-1',
        reason: 'The seller never showed up.',
        status: 'resolved',
        opened_at: '2026-01-01T00:00:00Z',
        resolution_outcome: 'refund_to_buyer',
        refund_amount: { amount_minor_units: 1500, currency: 'USD' },
        resolved_at: '2026-01-02T00:00:00Z',
      },
    });

    await render(<DisputeStatus />);

    expect(screen.getByText('Resolved')).toBeVisible();
    expect(screen.getByText('Refunded to buyer')).toBeVisible();
    expect(screen.getByText('15.00 USD')).toBeVisible();
  });

  it('shows a load error when the dispute cannot be fetched', async () => {
    (useDispute as jest.Mock).mockReturnValue({ isLoading: false, isError: true, data: undefined });

    await render(<DisputeStatus />);

    expect(screen.getByText("We couldn't load this dispute.")).toBeVisible();
  });
});
