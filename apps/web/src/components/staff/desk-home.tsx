"use client";

import useSWR from "swr";

import { Alert, Card, PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Branch } from "@/lib/types";

export function DeskHome() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.deskPage;

  if (!workspaces(me).desk) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={dict.staff.desk.title} description={branch?.name} />
      <div className="grid gap-4 md:grid-cols-2">
        {branch && <TodayCard branchId={branch.id} />}
        <Card>
          <p className="text-sm text-muted">{t.comingUp}</p>
        </Card>
      </div>
    </>
  );
}

function TodayCard({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const { data } = useSWR<{ data: Branch }>(`/branches/${branchId}`, swrFetcher);
  const today = data?.data.opening_hours?.find((h) => h.weekday === weekdayInAddis());
  const t = dict.deskPage;

  return (
    <Card title={t.today}>
      {!today ? (
        <p className="text-sm text-muted">{dict.common.loading}</p>
      ) : today.is_closed ? (
        <p className="text-lg font-medium">{t.closedToday}</p>
      ) : (
        <p className="text-lg font-medium">{format(t.openToday, { opens: today.opens_at ?? "", closes: today.closes_at ?? "" })}</p>
      )}
    </Card>
  );
}

/** 0 = Sunday … 6 = Saturday in the gym's timezone, whatever the device's clock zone. */
function weekdayInAddis(): number {
  const name = new Intl.DateTimeFormat("en-US", { weekday: "short", timeZone: "Africa/Addis_Ababa" }).format(new Date());
  return ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(name);
}
