import { deleteSecureItem, getSecureItem, setSecureItem } from '@/lib/secureStore';

const AUTH_TOKEN_KEY = 'rowbuddy.auth_token';

export function getAuthToken(): Promise<string | null> {
  return getSecureItem(AUTH_TOKEN_KEY);
}

export function setAuthToken(token: string): Promise<void> {
  return setSecureItem(AUTH_TOKEN_KEY, token);
}

export function clearAuthToken(): Promise<void> {
  return deleteSecureItem(AUTH_TOKEN_KEY);
}
