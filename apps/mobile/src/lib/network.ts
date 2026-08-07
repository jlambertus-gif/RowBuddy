import NetInfo from '@react-native-community/netinfo';
import { useEffect, useState } from 'react';

/**
 * Offline/retry strategy (architecture review §9): "no offline bids —
 * the bid mutation is disabled outright (not queued) when connectivity
 * is down." A queued mutation fired after reconnection could act on a
 * stale price/closing-time; disabling the action entirely avoids that,
 * rather than attempting to detect and recover from it after the fact.
 */
export function useIsOnline(): boolean {
  const [isOnline, setIsOnline] = useState(true);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      setIsOnline(state.isConnected !== false);
    });

    return unsubscribe;
  }, []);

  return isOnline;
}
