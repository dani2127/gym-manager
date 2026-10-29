import type { Metadata } from "next";

import { DeskHome } from "@/components/staff/desk-home";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/desk">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.desk.title };
}

export default function DeskPage() {
  return <DeskHome />;
}
