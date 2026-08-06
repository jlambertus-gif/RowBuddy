export const transferQueryKeys = {
  transfer: (transferId: string) => ['transfers', transferId] as const,
};
