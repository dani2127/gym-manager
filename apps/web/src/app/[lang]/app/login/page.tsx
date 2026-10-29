import type { Metadata } from "next";

import { MemberLogin } from "@/components/member/member-auth";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/login">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.login.title };
}

export default function Page() {
  return <MemberLogin />;
}
