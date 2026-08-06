import { useQuery } from '@tanstack/react-query';

import { hasNotificationPermission } from '@/lib/pushNotifications';

/** No prompt — safe on mount, used to decide whether to show an enable-notifications control. */
export function useNotificationPermissionStatus() {
  return useQuery({
    queryKey: ['notifications', 'permission-status'],
    queryFn: hasNotificationPermission,
  });
}
