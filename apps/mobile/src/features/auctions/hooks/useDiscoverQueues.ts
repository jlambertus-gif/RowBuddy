import { useInfiniteQuery } from '@tanstack/react-query';

import { discoverQueues } from '@/api/queues';

import { auctionQueryKeys } from './queryKeys';

/**
 * Disabled until the caller has real coordinates — discovery's
 * latitude/longitude are required by the backend
 * (DiscoverQueuesRequest), never optional.
 *
 * Mobile Sprint 7 (ADR-028 §3, discovery parity with web's own
 * Discover.jsx/Pagination.jsx). Paginated via useInfiniteQuery — the
 * backend's DiscoverQueuesController/QueueDiscoveryService already
 * support page/per_page/has_more, so this is a mobile-only change.
 */
export function useDiscoverQueues(coordinates: { latitude: number; longitude: number } | null) {
  return useInfiniteQuery({
    queryKey: coordinates
      ? auctionQueryKeys.discoverQueues(coordinates.latitude, coordinates.longitude)
      : ['queues', 'discover', 'disabled'],
    queryFn: ({ pageParam }) => discoverQueues({ ...coordinates!, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) => (lastPage.meta.has_more ? lastPage.meta.page + 1 : undefined),
    enabled: coordinates !== null,
  });
}
