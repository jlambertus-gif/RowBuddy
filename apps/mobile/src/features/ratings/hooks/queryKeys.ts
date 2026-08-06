export const ratingQueryKeys = {
  transferRatings: (transferId: string) => ['transfers', transferId, 'ratings'] as const,
};
