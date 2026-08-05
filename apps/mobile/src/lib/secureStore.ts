import * as SecureStore from 'expo-secure-store';

/**
 * Generic key/value wrapper over expo-secure-store (iOS Keychain /
 * Android Keystore). Deliberately has no concept of an auth token or
 * any other specific credential — Sprint 0 configures secure storage as
 * infrastructure only; ADR-028 Decision 2's Sanctum token is stored
 * through this same wrapper starting Sprint 1, not introduced here.
 */
export async function getSecureItem(key: string): Promise<string | null> {
  return SecureStore.getItemAsync(key);
}

export async function setSecureItem(key: string, value: string): Promise<void> {
  await SecureStore.setItemAsync(key, value);
}

export async function deleteSecureItem(key: string): Promise<void> {
  await SecureStore.deleteItemAsync(key);
}
