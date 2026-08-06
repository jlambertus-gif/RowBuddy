import { deleteSecureItem, getSecureItem, setSecureItem } from '@/lib/secureStore';

const PUSH_TOKEN_KEY = 'rowbuddy.push_token';

export function getStoredPushToken(): Promise<string | null> {
  return getSecureItem(PUSH_TOKEN_KEY);
}

export function setStoredPushToken(token: string): Promise<void> {
  return setSecureItem(PUSH_TOKEN_KEY, token);
}

export function clearStoredPushToken(): Promise<void> {
  return deleteSecureItem(PUSH_TOKEN_KEY);
}
