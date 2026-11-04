import type { Metadata } from "next";

import { MemberMembership } from "@/components/member/member-membership";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/membership">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.membership.title };
}

export default function Page() {
  return <MemberMembership />;
}
