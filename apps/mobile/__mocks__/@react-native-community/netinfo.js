let listeners = [];

module.exports = {
  addEventListener: jest.fn((listener) => {
    listeners.push(listener);
    return () => {
      listeners = listeners.filter((registered) => registered !== listener);
    };
  }),
  fetch: jest.fn(() => Promise.resolve({ isConnected: true, isInternetReachable: true })),
  // Test-only helper (not part of the real module's API) — lets a test
  // simulate a connectivity change: require('@react-native-community/netinfo').__emit({ isConnected: false }).
  __emit: (state) => listeners.forEach((listener) => listener(state)),
};
