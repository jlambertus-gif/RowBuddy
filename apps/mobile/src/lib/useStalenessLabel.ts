import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * "Last updated Xs/m ago" (architecture review §9): cached discovery/
 * auction/transfer reads are never presented as live truth for a
 * decision that commits money or a handoff. Ticks every 5s so the label
 * stays roughly current without re-rendering on every frame.
 */
export function useStalenessLabel(dataUpdatedAt: number): string {
  const { t } = useTranslation('common');
  // `Date.now()` is only ever read inside the effect below, never during
  // render itself — reading it directly in the render body would make
  // this hook impure (unstable results depending on when React happens
  // to re-render it).
  const [now, setNow] = useState(0);

  useEffect(() => {
    // Deferred via setTimeout rather than called synchronously here —
    // a direct setState call in an effect body triggers a cascading
    // render (react-hooks/set-state-in-effect); scheduling it as a
    // (zero-delay) callback keeps the update async, exactly like the
    // interval tick below.
    const initial = setTimeout(() => setNow(Date.now()), 0);
    const interval = setInterval(() => setNow(Date.now()), 5000);
    return () => {
      clearTimeout(initial);
      clearInterval(interval);
    };
  }, []);

  const seconds = Math.max(0, Math.round((now - dataUpdatedAt) / 1000));

  if (seconds < 60) {
    return t('staleness.seconds', { count: seconds });
  }

  return t('staleness.minutes', { count: Math.round(seconds / 60) });
}
