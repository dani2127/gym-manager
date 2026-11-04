import type { Metadata } from "next";

import { InvoicesList } from "@/components/billing/invoices-list";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/invoices">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.billing.invoices.title };
}

export default function Page() {
  return <InvoicesList />;
}
