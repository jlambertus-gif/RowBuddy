import { generateIdempotencyKey } from '@/lib/idempotencyKey';

describe('generateIdempotencyKey', () => {
  it('generates a non-empty string', () => {
    expect(generateIdempotencyKey().length).toBeGreaterThan(0);
  });

  it('generates a different value on each call', () => {
    const keys = new Set(Array.from({ length: 20 }, () => generateIdempotencyKey()));
    expect(keys.size).toBe(20);
  });
});
