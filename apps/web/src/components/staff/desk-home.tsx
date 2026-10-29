"use client";

import { Plus } from "lucide-react";
import Link from "next/link";
import useSWR from "swr";

import { TakingsStrip } from "@/components/billing/takings";
import { DeskConsole } from "@/components/checkins/desk-console";
import { MemberQuickFind } from "@/components/members/member-quick-find";
import { BranchDayLanes } from "@/components/staff/branch-day-lanes";
import { Alert, Card, PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { canRegisterMembers, handlesMoneyAt, workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Branch } from "@/lib/types";

/** A banner when members have sent transfers for the desk to check. */
function TransfersWaiting({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const t = dict.transfers.queue;
  const { data } = useSWR<{ meta: { waiting: number } }>(`/payments/transfers?branch=${branchId}`, swrFetcher, { refreshInterval: 30_000 });
  const n = data?.meta.waiting ?? 0;
  if (n === 0) return null;

  return (
    <Link href="/payments" className="flex items-center justify-between gap-3 rounded-[6px] border border-amber/40 bg-amber-soft px-5 py-4 text-amber">
      <span className="font-semibold">{n === 1 ? t.deskCardOne : format(t.deskCard, { n: String(n) })}</span>
      <span className="text-sm font-semibold underline-offset-2 hover:underline">{t.check}</span>
    </Link>
  );
}

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
        {branch && handlesMoneyAt(me, branch.id) && <TransfersWaiting branchId={branch.id} />}
        {branch && workspaces(me).desk && handlesMoneyAt(me, branch.id) && <DeskConsole branchId={branch.id} />}

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
        </Card>

        {handlesMoneyAt(me, branch?.id) && branch && (
          <Card
            title={dict.billing.desk.takings}
            actions={
              <Link href="/invoices" className="text-sm font-semibold text-track-deep hover:underline">
                {dict.billing.desk.seeInvoices}
              </Link>
            }
          >
            <TakingsStrip branchId={branch.id} />
          </Card>
        )}

        <Card title={t.today}>
          {!data ? <div className="h-14 animate-pulse rounded-[6px] bg-surface-2" /> : <BranchDayLanes branches={[data.data]} />}
        </Card>
      </div>
    </>
  );
}
