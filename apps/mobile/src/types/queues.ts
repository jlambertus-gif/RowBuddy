/**
 * Mirrors DiscoverQueuesController's response shape
 * (apps/web/app/Http/Controllers/DiscoverQueuesController.php +
 * RendersQueueResponses trait) exactly.
 */

export interface Geofence {
  latitude: number;
  longitude: number;
  radius_meters: number;
}

export interface DiscoveredQueue {
  id: string;
  category: string;
  jurisdiction_country: string;
  authorship: string;
  organizer_reference: string | null;
  status: string;
  geofence: Geofence;
  distance_meters: number;
}

export interface DiscoverQueuesMeta {
  page: number;
  per_page: number;
  total: number;
  has_more: boolean;
}

export interface DiscoverQueuesRequest {
  latitude: number;
  longitude: number;
  page?: number;
  per_page?: number;
}

/** Mirrors SubmitQueueController's request/response shapes exactly. */

export interface SubmitQueueRequest {
  category: string;
  jurisdiction_country: string;
  latitude: number;
  longitude: number;
  radius_meters: number;
}

export type SubmittedQueueStatus = 'pending' | 'approved' | 'published' | 'rejected';

export interface SubmittedQueue {
  id: string;
  category: string;
  jurisdiction_country: string;
  status: SubmittedQueueStatus;
}
