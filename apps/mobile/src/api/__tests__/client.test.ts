import { apiFetch, ApiError } from '@/api/client';
import { clearAuthToken, getAuthToken } from '@/lib/authToken';

jest.mock('@/lib/authToken');

function mockFetchOnce(status: number, body: unknown) {
  (globalThis.fetch as jest.Mock) = jest.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  });
}

describe('apiFetch', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('attaches the stored token as a bearer header when authenticated', async () => {
    (getAuthToken as jest.Mock).mockResolvedValue('a-real-token');
    mockFetchOnce(200, { data: { ok: true } });

    await apiFetch('/me');

    const [, requestInit] = (globalThis.fetch as jest.Mock).mock.calls[0];
    expect(requestInit.headers.Authorization).toBe('Bearer a-real-token');
  });

  it('never attaches a token when authenticated is false', async () => {
    (getAuthToken as jest.Mock).mockResolvedValue('a-real-token');
    mockFetchOnce(200, { data: { sent: true } });

    await apiFetch('/auth/forgot-password', { method: 'POST', authenticated: false });

    const [, requestInit] = (globalThis.fetch as jest.Mock).mock.calls[0];
    expect(requestInit.headers.Authorization).toBeUndefined();
    expect(getAuthToken).not.toHaveBeenCalled();
  });

  it('throws an ApiError carrying the status and parsed body on failure', async () => {
    mockFetchOnce(422, { message: 'The email field is required.', errors: { email: ['x'] } });

    await expect(
      apiFetch('/auth/login', { method: 'POST', authenticated: false }),
    ).rejects.toMatchObject({ status: 422, message: 'The email field is required.' });
  });

  it('clears the stored token whenever the response is 401, regardless of the endpoint', async () => {
    (getAuthToken as jest.Mock).mockResolvedValue('a-now-invalid-token');
    mockFetchOnce(401, { message: 'Unauthenticated.' });

    await expect(apiFetch('/me')).rejects.toBeInstanceOf(ApiError);
    expect(clearAuthToken).toHaveBeenCalledTimes(1);
  });

  it('does not clear the token for a non-401 failure', async () => {
    (getAuthToken as jest.Mock).mockResolvedValue('a-token');
    mockFetchOnce(422, { message: 'Validation failed.' });

    await expect(apiFetch('/me')).rejects.toBeInstanceOf(ApiError);
    expect(clearAuthToken).not.toHaveBeenCalled();
  });
});
