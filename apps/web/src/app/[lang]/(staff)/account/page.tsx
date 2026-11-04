import type { Metadata } from "next";

import { AccountPage } from "@/components/staff/account-page";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/account">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.account.title };
}

export default function Page() {
  return <AccountPage />;
}
