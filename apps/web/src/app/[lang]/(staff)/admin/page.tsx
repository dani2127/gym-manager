import type { Metadata } from "next";

import { AdminHome } from "@/components/staff/admin-home";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.admin.title };
}

export default function AdminPage() {
  return <AdminHome />;
}
