import * as SecureStore from 'expo-secure-store';

import { deleteSecureItem, getSecureItem, setSecureItem } from '@/lib/secureStore';

jest.mock('expo-secure-store');

describe('secureStore', () => {
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('reads a value through expo-secure-store', async () => {
    (SecureStore.getItemAsync as jest.Mock).mockResolvedValue('stored-value');

    await expect(getSecureItem('some-key')).resolves.toBe('stored-value');
    expect(SecureStore.getItemAsync).toHaveBeenCalledWith('some-key');
  });

  it('writes a value through expo-secure-store', async () => {
    await setSecureItem('some-key', 'some-value');

    expect(SecureStore.setItemAsync).toHaveBeenCalledWith('some-key', 'some-value');
  });

  it('deletes a value through expo-secure-store', async () => {
    await deleteSecureItem('some-key');

    expect(SecureStore.deleteItemAsync).toHaveBeenCalledWith('some-key');
  });
});
