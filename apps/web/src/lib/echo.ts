import Echo from "laravel-echo";
import Pusher from "pusher-js";

import { apiFetch } from "@/lib/api";

let echo: Echo<"reverb"> | null | undefined;

/**
 * The Reverb connection for live desk updates, created once per tab. Returns
 * null when Reverb isn't configured; screens then fall back to polling.
 */
export function getEcho(): Echo<"reverb"> | null {
  if (echo !== undefined) return echo;
  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
  if (typeof window === "undefined" || !key) return (echo = null);

  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https";
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? (scheme === "https" ? 443 : 80));

  echo = new Echo({
    broadcaster: "reverb",
    Pusher,
    key,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === "https",
    enabledTransports: ["ws", "wss"],
    // Private channels are authorised by the API with the staff session cookie.
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
        apiFetch<{ auth: string }>("/broadcasting/auth", { base: "/api", json: { socket_id: socketId, channel_name: channel.name } })
          .then((data) => callback(null, data))
          .catch((error: Error) => callback(error, null));
      },
    }),
  });

  return echo;
}
