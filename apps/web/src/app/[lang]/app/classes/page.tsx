import type { Metadata } from "next";

import { MemberClasses } from "@/components/member/member-classes";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/classes">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.classes.title };
}

export default function Page() {
  return <MemberClasses />;
}
