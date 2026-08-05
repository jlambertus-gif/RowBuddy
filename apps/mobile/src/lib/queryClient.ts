import { QueryClient } from '@tanstack/react-query';

/**
 * Mutation retry defaults to false project-wide (ADR-028 Decision 7 /
 * architecture review §9): a mutation is only allowed to retry once its
 * specific backend contract is confirmed idempotent, which each feature
 * decides for itself, not this shared client.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 2,
      staleTime: 30_000,
    },
    mutations: {
      retry: false,
    },
  },
});
