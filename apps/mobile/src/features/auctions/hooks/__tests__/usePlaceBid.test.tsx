import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import React from 'react';

import { placeBid } from '@/api/auctions';

import { usePlaceBid } from '../usePlaceBid';

jest.mock('@/api/auctions');

function wrapper({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: { mutations: { retry: 2, retryDelay: 0, gcTime: 0 } },
  });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe('usePlaceBid', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('reuses the exact same idempotency key across every retry attempt', async () => {
    (placeBid as jest.Mock).mockRejectedValue(new Error('network blip'));

    const { result } = await renderHook(() => usePlaceBid('auction-1'), { wrapper });

    result.current.mutate({ amount_minor_units: 1100, currency: 'USD' });

    await waitFor(() => expect(result.current.isError).toBe(true));

    const calls = (placeBid as jest.Mock).mock.calls;
    // 1 initial attempt + 2 retries = 3 calls, per the wrapper's retry: 2.
    expect(calls.length).toBe(3);
    const keysUsed = new Set(calls.map((call) => call[2]));
    expect(keysUsed.size).toBe(1);
  });

  it('generates a fresh idempotency key for each separate mutate() call', async () => {
    (placeBid as jest.Mock).mockResolvedValue({
      data: { id: 'bid-1', auction_id: 'auction-1', amount: {}, placed_at: '' },
    });

    const { result } = await renderHook(() => usePlaceBid('auction-1'), { wrapper });

    result.current.mutate({ amount_minor_units: 1100, currency: 'USD' });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    const firstKey = (placeBid as jest.Mock).mock.calls[0][2];

    result.current.mutate({ amount_minor_units: 1200, currency: 'USD' });
    await waitFor(() => expect((placeBid as jest.Mock).mock.calls.length).toBe(2));
    const secondKey = (placeBid as jest.Mock).mock.calls[1][2];

    expect(firstKey).not.toBe(secondKey);
  });
});
