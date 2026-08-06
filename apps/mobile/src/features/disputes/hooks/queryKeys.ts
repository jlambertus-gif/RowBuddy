export const disputeQueryKeys = {
  dispute: (disputeId: string) => ['disputes', disputeId] as const,
};
