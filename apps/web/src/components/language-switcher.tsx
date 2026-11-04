"use client";

import { usePathname } from "next/navigation";

import { hasLocale, LOCALE_COOKIE, type Locale } from "@/i18n/config";

const LABELS: Record<Locale, string> = { en: "English", am: "አማርኛ" };

export function LanguageSwitcher({ locale, label }: { locale: Locale; label: string }) {
  const pathname = usePathname();

  function change(next: Locale) {
    document.cookie = `${LOCALE_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;

    // The locale is the root layout segment, so switching needs a full document
    // load (a soft refresh would keep the old root mounted). Prefixed URLs
    // (/am/…) swap their prefix; clean URLs reload and the proxy picks it up.
    const [, first, ...rest] = pathname.split("/");
    if (hasLocale(first)) {
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load is required, see above
      window.location.assign(`/${next}/${rest.join("/")}`);
    } else {
      window.location.reload();
    }
  }

  return (
    <select
      aria-label={label}
      value={locale}
      onChange={(event) => change(event.target.value as Locale)}
      className="rounded-md border border-border bg-surface px-2 py-1 text-sm"
    >
      {(Object.keys(LABELS) as Locale[]).map((code) => (
        <option key={code} value={code}>
          {LABELS[code]}
        </option>
      ))}
    </select>
  );
}
