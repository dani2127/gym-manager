import type { Metadata } from "next";

import { StaffAdmin } from "@/components/staff/staff-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/staff">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staffPage.title };
}

export default function Page() {
  return <StaffAdmin />;
}
