import { useMutation, useQueryClient } from '@tanstack/react-query';

import { registerDeviceToken } from '@/api/devices';
import { registerForPushNotificationsAsync } from '@/lib/pushNotifications';
import { setStoredPushToken } from '@/lib/pushToken';

/**
 * Triggered only from an explicit user action (Profile screen's own
 * "enable notifications" button) — requests permission, then registers
 * the resulting token, then stores it locally so logout can pass it back
 * for removal (ADR-028 Decision 6).
 */
export function useRegisterPushNotifications() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      const { platform, token } = await registerForPushNotificationsAsync();
      await registerDeviceToken({ platform, expo_push_token: token });
      await setStoredPushToken(token);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications', 'permission-status'] });
    },
  });
}
