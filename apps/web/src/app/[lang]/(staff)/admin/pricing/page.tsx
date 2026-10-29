import type { Metadata } from "next";

import { PricingAdmin } from "@/components/billing/pricing-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/pricing">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.billing.pricing.title };
}

export default function Page() {
  return <PricingAdmin />;
}
