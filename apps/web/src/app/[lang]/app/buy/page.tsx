import type { Metadata } from "next";

import { MemberBuy } from "@/components/member/member-buy";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/buy">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.memberApp.buy.title };
}

export default function Page() {
  return <MemberBuy />;
}
