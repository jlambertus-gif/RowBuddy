import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser';
import { getAuthToken } from '@/lib/authToken';

/**
 * Splash/token-bootstrap (ADR-028 Sprint 1 scope). Reads any stored
 * token, validates it against GET /me, and routes accordingly. A 401
 * from /me already clears the stored token (src/api/client.ts) before
 * this screen ever sees the failure, so there is no risk of looping
 * back here with a dead token.
 */
export default function Splash() {
  const [hasCheckedToken, setHasCheckedToken] = useState(false);
  const [hasStoredToken, setHasStoredToken] = useState(false);

  useEffect(() => {
    getAuthToken().then((token) => {
      setHasStoredToken(token !== null);
      setHasCheckedToken(true);
    });
  }, []);

  const { data: user, isError, isFetched } = useCurrentUser(hasCheckedToken && hasStoredToken);

  useEffect(() => {
    if (!hasCheckedToken) {
      return;
    }

    if (!hasStoredToken) {
      router.replace('/(auth)/login');
      return;
    }

    if (isError) {
      router.replace('/(auth)/login');
      return;
    }

    if (isFetched && user) {
      router.replace(user.email_verified_at ? '/home' : '/(auth)/verify-email');
    }
  }, [hasCheckedToken, hasStoredToken, isError, isFetched, user]);

  return (
    <View style={styles.container}>
      <ActivityIndicator size="large" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    // Matches the native splash screen's own background (app.json's
    // expo-splash-screen config) so there's no color flash the instant
    // the native splash hides and this token-bootstrap screen mounts.
    backgroundColor: '#E6F4FE',
  },
});
