"use client";

import { Plus } from "lucide-react";
import Link from "next/link";
import useSWR from "swr";

import { MemberQuickFind } from "@/components/members/member-quick-find";
import { BranchDayLanes } from "@/components/staff/branch-day-lanes";
import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { canRegisterMembers, workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Branch } from "@/lib/types";

export function DeskHome() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.deskPage;
  const { data } = useSWR<{ data: Branch }>(branch ? `/branches/${branch.id}` : null, swrFetcher);

  if (!workspaces(me).desk) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={dict.staff.desk.title} description={branch?.name} />
      <div className="space-y-6">
        <Card
          title={t.findTitle}
          actions={
            canRegisterMembers(me) && (
              <Link href="/members/new" className="inline-flex items-center gap-1.5 text-sm font-semibold text-track-deep hover:underline">
                <Plus aria-hidden className="h-4 w-4" />
                {t.registerNew}
              </Link>
            )
          }
        >
          <MemberQuickFind />
          <p className="mt-3 text-xs text-graphite">{t.comingUp}</p>
        </Card>

        <Card title={t.today}>
          {!data ? <div className="h-14 animate-pulse rounded-[6px] bg-surface-2" /> : <BranchDayLanes branches={[data.data]} />}
        </Card>
      </div>
    </>
  );
}
