export type DevicePlatform = 'ios' | 'android';

export interface RegisterDeviceTokenRequest {
  platform: DevicePlatform;
  expo_push_token: string;
}
