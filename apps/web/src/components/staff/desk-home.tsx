"use client";

import { ScanLine } from "lucide-react";
import useSWR from "swr";

import { BranchDayLanes } from "@/components/staff/branch-day-lanes";
import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { workspaces } from "@/lib/roles";
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
        <section className="grid place-items-center rounded-[6px] border-2 border-dashed border-line-strong bg-surface px-6 py-14 text-center">
          <span className="grid h-14 w-14 place-items-center rounded-full bg-track-soft text-track-deep">
            <ScanLine aria-hidden className="h-7 w-7" />
          </span>
          <h2 className="mt-4 text-xl font-bold">{t.scanTitle}</h2>
          <p className="mt-2 max-w-md text-graphite">{t.scanBody}</p>
          <p className="mt-4 text-sm font-medium text-graphite">{t.comingUp}</p>
        </section>

        <Card title={t.today}>
          {!data ? <div className="h-14 animate-pulse rounded-[6px] bg-surface-2" /> : <BranchDayLanes branches={[data.data]} />}
        </Card>
      </div>
    </>
  );
}
