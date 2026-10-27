"use client";

import Link from "next/link";

import { PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { useSession } from "@/lib/session";

export function AdminHome() {
  const { dict } = useDictionary();
  const { me } = useSession();

  const cards = [
    { href: "/admin/branches", title: dict.nav.branches, body: `${me.branches.length}` },
    me.abilities.manage_staff && { href: "/admin/staff", title: dict.nav.staff, body: dict.staffPage.add },
    { href: "/admin/settings", title: dict.nav.settings, body: dict.settingsPage.tax },
    me.abilities.view_audit_log && { href: "/admin/activity", title: dict.nav.audit, body: dict.audit.title },
  ].filter((card): card is { href: string; title: string; body: string } => Boolean(card));

  return (
    <>
      <PageHeader title={dict.staff.admin.title} description={format(dict.adminPage.intro, { organization: me.organization.name })} />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => (
          <Link key={card.href} href={card.href} className="rounded-2xl border border-border bg-surface p-5 hover:border-brand">
            <span className="block font-semibold">{card.title}</span>
            <span className="mt-1 block text-sm text-muted">{card.body}</span>
          </Link>
        ))}
      </div>
    </>
  );
}
