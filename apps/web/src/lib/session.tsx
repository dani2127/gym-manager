"use client";

import { usePathname, useRouter } from "next/navigation";
import { createContext, useContext, useEffect, useLayoutEffect, useState, type ReactNode } from "react";
import useSWR from "swr";

import type { Locale } from "@/i18n/config";
import { ApiError, apiFetch, setApiLocale, swrFetcher } from "@/lib/api";
import type { Branch, Me } from "@/lib/types";

type Session = {
  me: Me;
  /** The branch the staff member is working in (persisted per device). */
  branch: Branch | null;
  setBranch: (id: string) => void;
  refresh: () => Promise<unknown>;
  signOut: () => Promise<void>;
};

const SessionContext = createContext<Session | null>(null);
const BRANCH_KEY = "gym.branch";

function readStoredBranch(): string | null {
  try {
    return typeof window === "undefined" ? null : window.localStorage.getItem(BRANCH_KEY);
  } catch {
    return null;
  }
}

/**
 * Loads /me for the staff workspaces. Unauthenticated visitors are sent to
 * /login; the API remains the real gatekeeper for every action.
 */
export function SessionProvider({
  locale,
  loading,
  forbidden,
  children,
}: {
  locale: Locale;
  loading: ReactNode;
  forbidden: (signOut: () => Promise<void>) => ReactNode;
  children: ReactNode;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const [branchId, setBranchId] = useState<string | null>(readStoredBranch);

  // A layout effect declared before useSWR runs before SWR's own (layout) effect,
  // so the very first request already carries the locale.
  useLayoutEffect(() => setApiLocale(locale), [locale]);

  const { data: me, error, mutate } = useSWR<Me>("/me", swrFetcher, { shouldRetryOnError: false });
  const unauthenticated = error instanceof ApiError && error.status === 401;

  useEffect(() => {
    if (unauthenticated) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [unauthenticated, pathname, router]);

  async function signOut() {
    await apiFetch("/auth/logout", { method: "POST" }).catch(() => {});
    // Full load so no cached data from this account survives in memory.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- intentional hard navigation
    window.location.assign("/login");
  }

  if (error && !unauthenticated) return <>{forbidden(signOut)}</>;
  if (!me) return <>{loading}</>;
  if (me.user.account_type === "member") return <>{forbidden(signOut)}</>;

  const branch = me.branches.find((b) => b.id === branchId) ?? me.branches[0] ?? null;

  function setBranch(id: string) {
    setBranchId(id);
    try {
      window.localStorage.setItem(BRANCH_KEY, id);
    } catch {
      // Storage unavailable: the choice lasts for this page view only.
    }
  }

  return (
    <SessionContext.Provider value={{ me, branch, setBranch, refresh: () => mutate(), signOut }}>
      {children}
    </SessionContext.Provider>
  );
}

export function useSession() {
  const session = useContext(SessionContext);
  if (!session) throw new Error("useSession must be used inside SessionProvider");
  return session;
}
