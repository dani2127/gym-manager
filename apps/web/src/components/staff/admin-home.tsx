"use client";

import { ArrowRight, Circle, CircleCheck } from "lucide-react";
import Link from "next/link";
import useSWR from "swr";

import { BranchDayLanes } from "@/components/staff/branch-day-lanes";
import { Card, PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { Branch, StaffMember } from "@/lib/types";

export function AdminHome() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const t = dict.adminPage;
  const { data: branches } = useSWR<{ data: Branch[] }>("/branches", swrFetcher);
  const { data: staff } = useSWR<{ data: StaffMember[] }>(me.abilities.manage_staff ? "/staff" : null, swrFetcher);

  const team = staff?.data.filter((p) => p.account_type === "staff") ?? [];
  const roleCounts = (["manager", "receptionist", "trainer"] as const).map((role) => ({
    role,
    count: team.filter((p) => p.assignments.some((a) => a.role === role)).length,
  }));

  const checklist = [
    { done: me.user.telegram_linked, label: t.setupTelegram, href: "/account" },
    // Hours and VAT have no "done" signal; they stay as links to review.
    { done: null, label: t.setupHours, href: "/admin/branches" },
    ...(me.abilities.manage_staff ? [{ done: team.length > 0, label: t.setupStaff, href: "/admin/staff" }] : []),
    ...(me.abilities.manage_settings ? [{ done: null, label: t.setupTax, href: "/admin/settings" }] : []),
  ];

  return (
    <>
      <PageHeader title={me.organization.name} description={format(t.branchesCount, { count: String(me.branches.length) })} />

      <div className="space-y-6">
        <Card title={t.lanesTitle} actions={<span className="text-xs text-graphite">{t.lanesHint}</span>}>
          {!branches ? <div className="h-40 animate-pulse rounded-[6px] bg-surface-2" /> : <BranchDayLanes branches={branches.data} />}
        </Card>

        <div className="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
          <Card title={t.setupTitle}>
            <ul className="divide-y divide-line">
              {checklist.map((item) => (
                <li key={item.label}>
                  <Link href={item.href} className="flex min-h-12 items-center gap-3 py-2 text-sm hover:text-track-deep">
                    {item.done === true ? (
                      <CircleCheck aria-label={t.done} className="h-5 w-5 shrink-0 text-infield" />
                    ) : item.done === false ? (
                      <Circle aria-hidden className="h-5 w-5 shrink-0 text-line-strong" />
                    ) : (
                      <ArrowRight aria-hidden className="h-5 w-5 shrink-0 text-graphite" />
                    )}
                    <span className={item.done ? "text-graphite line-through decoration-line-strong" : ""}>{item.label}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </Card>

          {me.abilities.manage_staff && (
            <Card title={t.teamTitle} actions={<Link href="/admin/staff" className="text-sm font-semibold text-track-deep hover:underline">{dict.staffPage.add}</Link>}>
              {!staff ? (
                <div className="h-24 animate-pulse rounded-[6px] bg-surface-2" />
              ) : team.length === 0 ? (
                <p className="text-sm text-graphite">{t.teamEmpty}</p>
              ) : (
                <dl className="grid grid-cols-3 divide-x divide-line">
                  {roleCounts.map(({ role, count }) => (
                    <div key={role} className="px-3 first:pl-0">
                      <dt className="text-xs text-graphite">{dict.staffPage.roles[role]}</dt>
                      <dd className="font-bib mt-1 text-4xl tabular">{count}</dd>
                    </div>
                  ))}
                </dl>
              )}
            </Card>
          )}
        </div>
      </div>
    </>
  );
}
