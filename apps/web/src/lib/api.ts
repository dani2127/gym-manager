import type { Locale } from "@/i18n/config";

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

/**
 * Thin fetch wrapper for the Laravel API (`/api/v1`). Sends the Sanctum
 * session cookie and the UI locale so API messages come back translated.
 */
export async function apiFetch<T>(
  path: string,
  { locale, ...init }: RequestInit & { locale?: Locale } = {},
): Promise<T> {
  const response = await fetch(`${API_URL}/api/v1${path}`, {
    credentials: "include",
    ...init,
    headers: {
      Accept: "application/json",
      ...(locale ? { "X-Locale": locale } : {}),
      ...init.headers,
    },
  });

  if (!response.ok) {
    throw new Error(`API ${response.status} on ${path}`);
  }

  return response.json() as Promise<T>;
}
