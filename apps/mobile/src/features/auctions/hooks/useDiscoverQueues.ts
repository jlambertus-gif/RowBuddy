import { useQuery } from '@tanstack/react-query';

import { discoverQueues } from '@/api/queues';

import { auctionQueryKeys } from './queryKeys';

/**
 * Disabled until the caller has real coordinates — discovery's
 * latitude/longitude are required by the backend
 * (DiscoverQueuesRequest), never optional.
 */
export function useDiscoverQueues(coordinates: { latitude: number; longitude: number } | null) {
  return useQuery({
    queryKey: coordinates
      ? auctionQueryKeys.discoverQueues(coordinates.latitude, coordinates.longitude)
      : ['queues', 'discover', 'disabled'],
    queryFn: () => discoverQueues(coordinates!),
    enabled: coordinates !== null,
  });
}
