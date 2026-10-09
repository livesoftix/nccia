import { useEffect, useRef } from 'react';
import api from '../api';

/**
 * Refresh `callback` only when the backend actually has a change, instead of
 * re-fetching the heavy list/dashboard on every interval.
 *
 * It polls the very cheap `/sync/pulse` endpoint (a single indexed lookup on
 * the audit ledger) on a short cadence and runs the real `callback` only when
 * the global change-counter has advanced. This cuts server load sharply and
 * makes updates appear faster (≈15s instead of 60s).
 *
 * Backward compatible: same signature. If `/sync/pulse` is unavailable (older
 * backend, or ledger table not migrated yet) it falls back to the original
 * timed refetch, so nothing breaks.
 */
export function useAutoRefresh(callback, deps = [], intervalMs = 60000) {
  const saved = useRef(callback);
  saved.current = callback;

  useEffect(() => {
    let lastHead = null;       // last ledger "head" (version) we have seen
    let lastFull = Date.now(); // last time we ran the heavy callback
    let pulseOk = true;        // set false if the endpoint is not available
    let cancelled = false;

    const busy = () => {
      if (document.visibilityState !== 'visible') return true;
      const tag = document.activeElement?.tagName;
      return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    };

    const runFull = () => { lastFull = Date.now(); saved.current(); };

    // Initial load, plus establish a baseline version without a second fetch.
    runFull();
    api.get('/sync/pulse')
      .then(r => { if (!cancelled) { if (r?.data?.ledger) lastHead = r.data.head; else pulseOk = false; } })
      .catch(() => { pulseOk = false; });

    const pulseEvery = Math.max(5000, Math.min(intervalMs, 15000));

    const tick = async () => {
      if (busy()) return;
      if (pulseOk) {
        try {
          const params = lastHead != null ? { since: lastHead } : {};
          const r = await api.get('/sync/pulse', { params });
          if (!r?.data?.ledger) {
            pulseOk = false; // endpoint present but ledger off → fall through
          } else {
            const head = r.data.head;
            if (lastHead == null) lastHead = head;
            else if (head > lastHead) { lastHead = head; runFull(); }
            return;
          }
        } catch {
          pulseOk = false; // route missing / network → fall back to timed mode
        }
      }
      // Fallback: behave like the original timed refetch.
      if (Date.now() - lastFull >= intervalMs) runFull();
    };

    const timer = setInterval(tick, pulseEvery);
    return () => { cancelled = true; clearInterval(timer); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}
