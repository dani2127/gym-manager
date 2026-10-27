"use client";

import { useEffect, useState } from "react";

import { apiFetch } from "@/lib/api";

type Labels = { label: string; online: string; offline: string; checking: string };

/** Small badge showing whether the Laravel API answers /api/v1/health. */
export function ApiStatus({ labels }: { labels: Labels }) {
  const [status, setStatus] = useState<"checking" | "online" | "offline">("checking");

  useEffect(() => {
    let cancelled = false;
    apiFetch<{ status: string }>("/health")
      .then((body) => !cancelled && setStatus(body.status === "ok" ? "online" : "offline"))
      .catch(() => !cancelled && setStatus("offline"));
    return () => {
      cancelled = true;
    };
  }, []);

  const color =
    status === "online" ? "bg-emerald-500" : status === "offline" ? "bg-red-500" : "bg-amber-400";

  return (
    <span className="inline-flex items-center gap-2 text-xs text-muted">
      <span className={`h-2 w-2 rounded-full ${color}`} aria-hidden />
      {labels.label}: {labels[status]}
    </span>
  );
}
