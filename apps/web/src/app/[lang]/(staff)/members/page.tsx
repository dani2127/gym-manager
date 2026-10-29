import type { Metadata } from "next";

import { MembersList } from "@/components/members/members-list";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/members">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.members.title };
}

export default function MembersPage() {
  return <MembersList />;
}
