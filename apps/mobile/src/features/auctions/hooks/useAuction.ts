import { useQueryClient, useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { AppState } from 'react-native';

import { fetchAuction } from '@/api/auctions';
import { subscribeToAuctionSnapshot } from '@/lib/realtime';
import { AuctionSnapshot } from '@/types/auctions';

import { auctionQueryKeys } from './queryKeys';

/**
 * Implements the real-time design frozen in the architecture review
 * (§6): always fetch the authoritative REST snapshot first, then
 * subscribe to the public `auctions.{id}` Reverb channel for
 * low-latency updates on top of it — the socket is never trusted as
 * the sole source of correctness. On returning to the foreground, the
 * REST snapshot is re-fetched once, since the app may have missed
 * events while backgrounded (sockets are not kept alive in the
 * background).
 */
export function useAuction(auctionId: string) {
  const queryClient = useQueryClient();
  const queryKey = auctionQueryKeys.auction(auctionId);

  const query = useQuery({
    queryKey,
    queryFn: () => fetchAuction(auctionId).then((response) => response.data),
    staleTime: 10_000,
  });

  useEffect(() => {
    const unsubscribe = subscribeToAuctionSnapshot<AuctionSnapshot>(auctionId, (snapshot) => {
      queryClient.setQueryData(queryKey, snapshot);
    });

    const appStateSubscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        queryClient.invalidateQueries({ queryKey });
      }
    });

    return () => {
      unsubscribe();
      appStateSubscription.remove();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- queryKey is derived from auctionId; re-subscribing on queryClient identity change would be wasteful and queryClient is stable for the app's lifetime.
  }, [auctionId]);

  return query;
}
