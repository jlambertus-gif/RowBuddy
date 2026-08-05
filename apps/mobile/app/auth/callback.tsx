import { router } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

/**
 * The single, allowlisted deep-link target the backend's "return to the
 * app" interstitial redirects to (ADR-028 Decision 7,
 * config('mobile.return_url') = "rowbuddy://auth/callback"). By the
 * time this fires, the verification or password reset has already
 * completed successfully on the backend — this screen carries no
 * token, email, or other secret (the backend never puts one in the
 * link) and exists only to route the user back to a fresh login.
 */
export default function AuthCallback() {
  useEffect(() => {
    router.replace('/(auth)/login');
  }, []);

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
    backgroundColor: '#fff',
  },
});
