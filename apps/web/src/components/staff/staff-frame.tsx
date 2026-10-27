"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

import { LanguageSwitcher } from "@/components/language-switcher";
import { Alert, Button } from "@/components/ui";
import { hasLocale } from "@/i18n/config";
import { useDictionary } from "@/i18n/dictionary-context";
import { workspaces } from "@/lib/roles";
import { SessionProvider, useSession } from "@/lib/session";

/** Signed-in chrome for /desk, /admin, /trainer and /account. */
export function StaffFrame({ children }: { children: ReactNode }) {
  const { locale, dict } = useDictionary();

  return (
    <SessionProvider
      locale={locale}
      loading={<p className="p-10 text-center text-sm text-muted">{dict.common.loading}</p>}
      forbidden={(signOut) => (
        <div className="mx-auto max-w-md space-y-4 p-10">
          <Alert tone="error">{dict.common.notAllowed}</Alert>
          <Button variant="secondary" onClick={signOut}>
            {dict.common.signOut}
          </Button>
        </div>
      )}
    >
      <Chrome>{children}</Chrome>
    </SessionProvider>
  );
}

function Chrome({ children }: { children: ReactNode }) {
  const { locale, dict } = useDictionary();
  const { me, branch, setBranch, signOut } = useSession();
  const pathname = usePathname();
  const [, first, ...rest] = pathname.split("/");
  const current = "/" + (hasLocale(first) ? rest : [first, ...rest]).join("/");
  const access = workspaces(me);

  const links = [
    access.desk && { href: "/desk", label: dict.nav.desk },
    access.trainer && { href: "/trainer", label: dict.nav.trainer },
    access.admin && { href: "/admin", label: dict.nav.admin },
    { href: "/account", label: dict.nav.account },
  ].filter((link): link is { href: string; label: string } => Boolean(link));

  return (
    <div className="flex min-h-full flex-1 flex-col">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
          <span className="font-semibold">{me.organization.name}</span>
          <nav className="flex flex-wrap gap-1 text-sm">
            {links.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                aria-current={current.startsWith(link.href) ? "page" : undefined}
                className="rounded-md px-3 py-1.5 text-muted hover:text-foreground aria-[current=page]:bg-brand-soft aria-[current=page]:text-brand"
              >
                {link.label}
              </Link>
            ))}
          </nav>
          <div className="ml-auto flex flex-wrap items-center gap-2">
            {me.branches.length > 0 && (
              <select
                aria-label={dict.nav.branch}
                value={branch?.id}
                onChange={(event) => setBranch(event.target.value)}
                className="rounded-md border border-border bg-surface px-2 py-1 text-sm"
              >
                {me.branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </select>
            )}
            <LanguageSwitcher locale={locale} label={dict.common.language} />
            <Button variant="secondary" onClick={signOut} className="px-3 py-1">
              {dict.common.signOut}
            </Button>
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">{children}</main>
    </div>
  );
}
