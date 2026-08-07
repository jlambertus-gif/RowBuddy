import { apiFetch } from '@/api/client';
import {
  DiscoveredQueue,
  DiscoverQueuesMeta,
  DiscoverQueuesRequest,
  SubmitQueueRequest,
  SubmittedQueue,
} from '@/types/queues';

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

/**
 * Mobile Sprint 5 (ADR-028 §3). A Sanctum-guarded JSON mirror of the
 * existing web session-guarded /queues route.
 */
export function submitQueue(payload: SubmitQueueRequest): Promise<{ data: SubmittedQueue }> {
  return apiFetch('/api/v1/queues', { method: 'POST', body: payload });
}
