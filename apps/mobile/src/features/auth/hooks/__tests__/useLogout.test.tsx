import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import React from 'react';

import { logout } from '@/api/auth';
import { clearAuthToken } from '@/lib/authToken';
import { clearStoredPushToken, getStoredPushToken } from '@/lib/pushToken';

import { useLogout } from '../useLogout';

jest.mock('@/api/auth');
jest.mock('@/lib/authToken');
jest.mock('@/lib/pushToken');

/**
 * Mobile Sprint 6 SecureStore/cache audit: logout must always clear
 * every piece of local session state — the auth token, the push token,
 * and (found during this audit — a real gap, not previously covered)
 * TanStack Query's own in-memory cache, so a second account logging in
 * on a shared device never transiently sees the previous user's cached
 * profile/transfers/ratings.
 */
function buildQueryClient() {
  // gcTime: 0 avoids an open-handle warning after the test run
  // completes (the same fix usePlaceBid.test.tsx already needed).
  return new QueryClient({ defaultOptions: { queries: { gcTime: 0 }, mutations: { gcTime: 0 } } });
}

function buildWrapper(queryClient: QueryClient) {
  return function wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}

describe('useLogout', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('clears the auth token, the push token, and the entire query cache on success', async () => {
    (getStoredPushToken as jest.Mock).mockResolvedValue('ExponentPushToken[abc]');
    (logout as jest.Mock).mockResolvedValue(null);
    const queryClient = buildQueryClient();
    queryClient.setQueryData(['profile'], { name: 'Stale User' });

    const { result } = await renderHook(() => useLogout(), { wrapper: buildWrapper(queryClient) });
    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(logout).toHaveBeenCalledWith('ExponentPushToken[abc]');
    expect(clearAuthToken).toHaveBeenCalledTimes(1);
    expect(clearStoredPushToken).toHaveBeenCalledTimes(1);
    expect(queryClient.getQueryData(['profile'])).toBeUndefined();
  });

  it('still clears every local value and the cache even when the backend request fails', async () => {
    (getStoredPushToken as jest.Mock).mockResolvedValue(null);
    (logout as jest.Mock).mockRejectedValue(new Error('network down'));
    const queryClient = buildQueryClient();
    queryClient.setQueryData(['profile'], { name: 'Stale User' });

    const { result } = await renderHook(() => useLogout(), { wrapper: buildWrapper(queryClient) });
    result.current.mutate();

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(clearAuthToken).toHaveBeenCalledTimes(1);
    expect(clearStoredPushToken).toHaveBeenCalledTimes(1);
    expect(queryClient.getQueryData(['profile'])).toBeUndefined();
  });

  it('logs out without a push token when none was ever registered', async () => {
    (getStoredPushToken as jest.Mock).mockResolvedValue(null);
    (logout as jest.Mock).mockResolvedValue(null);
    const queryClient = buildQueryClient();

    const { result } = await renderHook(() => useLogout(), { wrapper: buildWrapper(queryClient) });
    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(logout).toHaveBeenCalledWith(undefined);
  });
});
