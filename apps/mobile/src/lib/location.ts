import * as Location from 'expo-location';

export class LocationPermissionDeniedError extends Error {
  constructor() {
    super('Location permission was denied.');
    this.name = 'LocationPermissionDeniedError';
  }
}

/**
 * Requested contextually — only when discovery actually needs it
 * (architecture review §8), never at app launch.
 */
export async function getCurrentCoordinates(): Promise<{ latitude: number; longitude: number }> {
  const { status } = await Location.requestForegroundPermissionsAsync();

  if (status !== Location.PermissionStatus.GRANTED) {
    throw new LocationPermissionDeniedError();
  }

  const position = await Location.getCurrentPositionAsync({});

  return { latitude: position.coords.latitude, longitude: position.coords.longitude };
}
