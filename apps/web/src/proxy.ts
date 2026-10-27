import { NextResponse, type NextRequest } from "next/server";

import {
  defaultLocale,
  hasLocale,
  LOCALE_COOKIE,
  matchAcceptLanguage,
} from "@/i18n/config";

/** Staff workspaces; visitors without a session cookie go straight to /login. */
const PROTECTED = ["/desk", "/admin", "/trainer", "/account"];

/** Must match the API's SESSION_COOKIE. */
const SESSION_COOKIE = process.env.SESSION_COOKIE_NAME ?? "gym_session";

/**
 * 1. Optimistic auth check: no session cookie on a staff page → /login.
 *    (Only a hint for fast redirects; the API checks every request.)
 * 2. Keeps URLs locale-free (/app, /desk …) while every page lives under
 *    app/[lang]: un-prefixed requests are rewritten to /{locale}/… using the
 *    locale cookie, then Accept-Language, then the default.
 */
export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const [, first, ...rest] = pathname.split("/");
  const prefixed = hasLocale(first);
  const path = prefixed ? `/${rest.join("/")}` : pathname;

  const isProtected = PROTECTED.some((p) => path === p || path.startsWith(`${p}/`));
  if (isProtected && !request.cookies.has(SESSION_COOKIE)) {
    const login = request.nextUrl.clone();
    login.pathname = prefixed ? `/${first}/login` : "/login";
    login.search = `?next=${encodeURIComponent(pathname)}`;
    return NextResponse.redirect(login);
  }

  if (prefixed) return;

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
