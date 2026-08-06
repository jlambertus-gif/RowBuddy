import { clearAuthToken, getAuthToken } from '@/lib/authToken';

/**
 * The backend's own origin — apps/web (ADR-028 Decision 2, never a
 * separate apps/api installation). Deliberately NOT pinned to /api/v1:
 * some endpoints mobile calls (GET /queues/discover, GET /auctions/{id})
 * are already-public, unversioned web.php routes reused as-is (no auth,
 * no session/CSRF dependency, so no reason to mirror them); only the
 * ones that genuinely need it (auth, bid placement) live under
 * /api/v1. Callers pass the full path, including /api/v1 where it
 * applies. Overridable via EXPO_PUBLIC_API_BASE_URL for non-local
 * environments; defaults to the local docker-compose backend used
 * throughout this repo's own development setup.
 */
const API_BASE_URL = process.env.EXPO_PUBLIC_API_BASE_URL ?? 'http://localhost:8000';

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

interface ApiFetchOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
  body?: object;
  authenticated?: boolean;
  headers?: Record<string, string>;
}

export async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const { method = 'GET', body, authenticated = true, headers: extraHeaders } = options;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...extraHeaders,
  };

  if (authenticated) {
    const token = await getAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const responseBody = response.status === 204 ? null : await response.json().catch(() => null);

  if (!response.ok) {
    // A 401 always means the stored token is no longer valid (expired,
    // revoked, or never existed) — the token is cleared unconditionally
    // here so a caller can never accidentally keep retrying with a dead
    // token. Deciding what to do next (route to Login) is left to the
    // caller/UI layer, not this data-layer function.
    if (response.status === 401) {
      await clearAuthToken();
    }

    const message =
      responseBody && typeof responseBody === 'object' && 'message' in responseBody
        ? String((responseBody as { message: unknown }).message)
        : `Request failed with status ${response.status}`;

    throw new ApiError(message, response.status, responseBody);
  }

  return responseBody as T;
}
