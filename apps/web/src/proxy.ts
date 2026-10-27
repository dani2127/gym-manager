import { NextResponse, type NextRequest } from "next/server";

import {
  defaultLocale,
  hasLocale,
  LOCALE_COOKIE,
  matchAcceptLanguage,
} from "@/i18n/config";

/**
 * Keeps URLs locale-free (/app, /desk …) while every page lives under
 * app/[lang]: un-prefixed requests are rewritten to /{locale}/… using the
 * locale cookie, then Accept-Language, then the default.
 */
export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (hasLocale(pathname.split("/")[1])) return;

  const locale =
    [request.cookies.get(LOCALE_COOKIE)?.value].find(hasLocale) ??
    matchAcceptLanguage(request.headers.get("accept-language")) ??
    defaultLocale;

  const url = request.nextUrl.clone();
  url.pathname = `/${locale}${pathname === "/" ? "" : pathname}`;

  return NextResponse.rewrite(url);
}

export const config = {
  // Skip Next internals and any path with a file extension (icons, sw.js, manifest).
  matcher: ["/((?!_next|.*\\..*).*)"],
};
