"use client";

import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";

import { MemberForm } from "@/components/members/member-form";
import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { canRegisterMembers } from "@/lib/roles";
import { useSession } from "@/lib/session";

export function NewMember() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const router = useRouter();
  const t = dict.members;

  if (!canRegisterMembers(me)) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <Link href="/members" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-graphite hover:text-ink">
        <ArrowLeft aria-hidden className="h-4 w-4" />
        {t.back}
      </Link>
      <PageHeader title={t.new} />
      <Card>
        <MemberForm onSaved={(member) => router.push(`/members/${member.id}`)} onCancel={() => router.push("/members")} />
      </Card>
    </>
  );
}
