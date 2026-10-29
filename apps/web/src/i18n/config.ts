export const locales = ["en", "am"] as const;

export type Locale = (typeof locales)[number];

export const defaultLocale: Locale = "en";

/** Cookie the proxy reads to pick the locale for un-prefixed URLs. */
export const LOCALE_COOKIE = "NEXT_LOCALE";

export function hasLocale(value: string | undefined): value is Locale {
  return (locales as readonly string[]).includes(value ?? "");
}

/** First supported locale in an Accept-Language header, in the client's order. */
export function matchAcceptLanguage(header: string | null): Locale | undefined {
  if (!header) return undefined;

  return header
    .split(",")
    .map((part) => {
      const [tag, q] = part.trim().split(";q=");
      return { lang: tag.split("-")[0].toLowerCase(), q: q ? Number(q) : 1 };
    })
    .sort((a, b) => b.q - a.q)
    .map(({ lang }) => lang)
    .find(hasLocale);
}
