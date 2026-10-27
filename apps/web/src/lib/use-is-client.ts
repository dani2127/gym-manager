import { useSyncExternalStore } from "react";

const subscribe = () => () => {};

/** False during prerender/hydration, true afterwards. For UI that depends on browser-only state. */
export function useIsClient() {
  return useSyncExternalStore(subscribe, () => true, () => false);
}
