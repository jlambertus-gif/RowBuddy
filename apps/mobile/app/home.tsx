import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { useCurrentUser } from '@/features/auth/hooks/useCurrentUser';
import { useLogout } from '@/features/auth/hooks/useLogout';

/**
 * Minimal authenticated placeholder — proves the full splash → login/
 * register → authenticated-bootstrap loop works end-to-end. Real
 * discovery/home content is Sprint 2 scope (src/features/auctions),
 * not this sprint's.
 */
export default function Home() {
  const { t } = useTranslation();
  const { data: user } = useCurrentUser(true);
  const logout = useLogout();

  function handleLogout() {
    logout.mutate(undefined, {
      onSettled: () => router.replace('/(auth)/login'),
    });
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('app_name')}</Text>
      {user && <Text style={styles.subtitle}>{user.email}</Text>}
      <Pressable style={styles.button} onPress={handleLogout} disabled={logout.isPending}>
        <Text style={styles.buttonText}>Log out</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    gap: 12,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
  button: {
    marginTop: 16,
    paddingVertical: 12,
    paddingHorizontal: 24,
    backgroundColor: '#dc2626',
    borderRadius: 8,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});
