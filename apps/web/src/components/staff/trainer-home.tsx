"use client";

import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";

export function TrainerHome() {
  const { dict } = useDictionary();
  const { me } = useSession();

  if (!workspaces(me).trainer) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const branches = me.branches.filter((b) => me.user.account_type === "owner" || b.role === "trainer");

  return (
    <>
      <PageHeader title={dict.staff.trainer.title} description={dict.staff.trainer.body} />
      <Card title={dict.nav.branches}>
        <ul className="list-inside list-disc text-sm">
          {branches.map((b) => (
            <li key={b.id}>{b.name}</li>
          ))}
        </ul>
        <p className="mt-4 text-sm text-muted">{dict.trainerPage.comingUp}</p>
      </Card>
    </>
  );
}
