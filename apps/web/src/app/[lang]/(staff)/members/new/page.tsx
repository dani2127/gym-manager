import type { Metadata } from "next";

import { NewMember } from "@/components/members/new-member";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/members/new">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.members.new };
}

export default function NewMemberPage() {
  return <NewMember />;
}
