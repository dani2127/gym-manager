import type { Metadata } from "next";

import { FreezeQueue } from "@/components/memberships/freeze-queue";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/freezes">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.freezes.title };
}

export default function Page() {
  return <FreezeQueue />;
}
