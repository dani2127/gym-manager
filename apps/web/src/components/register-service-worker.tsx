"use client";

import { useEffect } from "react";

/** Registers the member PWA service worker, scoped to /app (the pass page and everything under it). */
export function RegisterServiceWorker() {
  useEffect(() => {
    if (process.env.NODE_ENV !== "production" || !("serviceWorker" in navigator)) return;
    navigator.serviceWorker.register("/sw.js", { scope: "/app" }).catch(() => {});
  }, []);

  return null;
}
