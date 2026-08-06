import { apiFetch } from '@/api/client';
import { DiscoveredQueue, DiscoverQueuesMeta, DiscoverQueuesRequest } from '@/types/queues';

/**
 * GET /queues/discover — public, unauthenticated, unversioned (reused
 * exactly as-is; see the note in src/api/client.ts).
 */
export function discoverQueues(
  params: DiscoverQueuesRequest,
): Promise<{ data: DiscoveredQueue[]; meta: DiscoverQueuesMeta }> {
  const query = new URLSearchParams({
    latitude: String(params.latitude),
    longitude: String(params.longitude),
    ...(params.page ? { page: String(params.page) } : {}),
    ...(params.per_page ? { per_page: String(params.per_page) } : {}),
  });

  return apiFetch(`/queues/discover?${query.toString()}`, { authenticated: false });
}
