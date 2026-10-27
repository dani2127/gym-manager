import type { Metadata } from "next";

import { BranchesAdmin } from "@/components/staff/branches-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/branches">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.branches.title };
}

export default function Page() {
  return <BranchesAdmin />;
}
