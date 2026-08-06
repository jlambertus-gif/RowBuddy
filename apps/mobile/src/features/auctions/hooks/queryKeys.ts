export const auctionQueryKeys = {
  discoverQueues: (latitude: number, longitude: number) =>
    ['queues', 'discover', latitude, longitude] as const,
  auction: (auctionId: string) => ['auctions', auctionId] as const,
};
