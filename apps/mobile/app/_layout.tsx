import '@/i18n';

import { QueryClientProvider } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { queryClient } from '@/lib/queryClient';

/**
 * Sprint 0 root layout: wires the app-wide providers (i18n, TanStack
 * Query, safe-area) with no screen content, no auth, and no backend
 * calls. Real navigation structure (tabs, auth-gated stacks) is
 * introduced starting Sprint 1.
 */
export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <StatusBar style="auto" />
        <Stack screenOptions={{ headerShown: false }} />
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
