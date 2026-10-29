import type { Metadata } from "next";

import { MemberSetup } from "@/components/member/member-auth";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/setup">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.setup.title };
}

export default function Page() {
  return <MemberSetup />;
}
