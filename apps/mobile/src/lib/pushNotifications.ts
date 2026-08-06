import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { DevicePlatform } from '@/types/devices';

export class NotificationPermissionDeniedError extends Error {
  constructor() {
    super('Notification permission was denied.');
    this.name = 'NotificationPermissionDeniedError';
  }
}

/** No prompt — safe to call on mount to silently detect an already-granted permission. */
export async function hasNotificationPermission(): Promise<boolean> {
  const { granted } = await Notifications.getPermissionsAsync();
  return granted;
}

/**
 * Requested only from an explicit user action (the Profile screen's own
 * "enable notifications" control) — never automatically at launch or on
 * any other screen's mount (ADR-028 Decision 6's permission model: "the
 * app requests this contextually, not at first launch").
 */
export async function registerForPushNotificationsAsync(): Promise<{
  platform: DevicePlatform;
  token: string;
}> {
  const { granted } = await Notifications.requestPermissionsAsync();

  if (!granted) {
    throw new NotificationPermissionDeniedError();
  }

  const projectId = Constants.easConfig?.projectId;
  const { data: token } = await Notifications.getExpoPushTokenAsync(
    projectId ? { projectId } : undefined,
  );

  return { platform: Platform.OS === 'ios' ? 'ios' : 'android', token };
}
