"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

import { Alert } from "@/components/ui";
import { hasLocale } from "@/i18n/config";
import { useDictionary } from "@/i18n/dictionary-context";
import { workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";

/** Sub-navigation for /admin; tabs follow the user's abilities. */
export function AdminSection({ children }: { children: ReactNode }) {
  const { dict } = useDictionary();
  const { me } = useSession();
  const pathname = usePathname();
  const [, first, ...rest] = pathname.split("/");
  const current = "/" + (hasLocale(first) ? rest : [first, ...rest]).join("/");

  if (!workspaces(me).admin) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const tabs = [
    { href: "/admin", label: dict.nav.admin, show: true },
    { href: "/admin/branches", label: dict.nav.branches, show: true },
    { href: "/admin/staff", label: dict.nav.staff, show: me.abilities.manage_staff },
    { href: "/admin/settings", label: dict.nav.settings, show: true },
    { href: "/admin/activity", label: dict.nav.audit, show: me.abilities.view_audit_log },
  ].filter((tab) => tab.show);

  return (
    <div className="space-y-6">
      <nav className="-mx-1 flex gap-1 overflow-x-auto border-b border-border pb-px text-sm">
        {tabs.map((tab) => (
          <Link
            key={tab.href}
            href={tab.href}
            aria-current={current === tab.href ? "page" : undefined}
            className="shrink-0 border-b-2 border-transparent px-3 py-2 text-muted hover:text-foreground aria-[current=page]:border-brand aria-[current=page]:text-foreground"
          >
            {tab.label}
          </Link>
        ))}
      </nav>
      {children}
    </div>
  );
}
