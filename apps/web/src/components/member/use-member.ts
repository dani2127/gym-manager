"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import useSWR from "swr";

import { ApiError, swrFetcher } from "@/lib/api";
import type { MemberHome } from "@/lib/types";

/** The signed-in member's summary. Sends signed-out visitors to the member sign-in. */
export function useMember() {
  const router = useRouter();
  const result = useSWR<{ data: MemberHome }, ApiError>("/me/member", swrFetcher, {
    shouldRetryOnError: (error) => !(error instanceof ApiError && [401, 403].includes(error.status)),
  });

  const status = result.error instanceof ApiError ? result.error.status : null;
  useEffect(() => {
    if (status === 401) router.replace("/app/login");
  }, [status, router]);

  return { ...result, notMember: status === 403, signedOut: status === 401 };
}
