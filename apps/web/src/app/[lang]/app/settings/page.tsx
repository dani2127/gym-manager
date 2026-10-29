import type { Metadata } from "next";

import { MemberSettings } from "@/components/member/member-settings";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/settings">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.settings.title };
}

export default function Page() {
  return <MemberSettings />;
}
