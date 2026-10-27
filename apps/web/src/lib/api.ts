import type { Locale } from "@/i18n/config";

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

/** Error from the API, carrying Laravel's validation messages when present. */
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message);
  }

  /** First message for a field, e.g. "phone" or "assignments.0.branch". */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

const UNSAFE_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.split("=")[1]) : undefined;
}

/** Sanctum SPA auth: fetch the XSRF-TOKEN cookie before the first write. */
async function ensureCsrfCookie() {
  if (readCookie("XSRF-TOKEN")) return;
  await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: "include" });
}

let currentLocale: Locale | undefined;

/** The UI sets this once so every request asks for messages in that language. */
export function setApiLocale(locale: Locale) {
  currentLocale = locale;
}

/**
 * Thin fetch wrapper for the Laravel API (`/api/v1`). Sends the Sanctum
 * session cookie, the CSRF header on writes, and the UI locale.
 */
export async function apiFetch<T>(
  path: string,
  { locale, json, ...init }: RequestInit & { locale?: Locale; json?: unknown } = {},
): Promise<T> {
  const method = (init.method ?? (json !== undefined ? "POST" : "GET")).toUpperCase();

  if (UNSAFE_METHODS.has(method)) {
    await ensureCsrfCookie();
  }

  const xsrf = readCookie("XSRF-TOKEN");
  const lang = locale ?? currentLocale;

  const response = await fetch(`${API_URL}/api/v1${path}`, {
    credentials: "include",
    ...init,
    method,
    body: json !== undefined ? JSON.stringify(json) : init.body,
    headers: {
      Accept: "application/json",
      ...(json !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(lang ? { "X-Locale": lang } : {}),
      ...(xsrf && UNSAFE_METHODS.has(method) ? { "X-XSRF-TOKEN": xsrf } : {}),
      ...init.headers,
    },
  });

  if (response.status === 204) return undefined as T;

  const body = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(response.status, body.message ?? response.statusText, body.errors);
  }

  return body as T;
}

/** SWR fetcher: `useSWR('/me', swrFetcher)`. */
export const swrFetcher = <T>(path: string) => apiFetch<T>(path);
