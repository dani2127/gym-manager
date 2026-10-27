import Link from "next/link";

import { loadLocale } from "@/i18n/dictionaries";

const SECTIONS = ["app", "desk", "admin", "trainer"] as const;

export default async function HomePage({ params }: PageProps<"/[lang]">) {
  const { dict } = await loadLocale(params);
  const t = dict.public;

  return (
    <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-16">
      <h1 className="max-w-2xl text-4xl font-semibold tracking-tight">{t.heroTitle}</h1>
      <p className="mt-4 max-w-2xl text-lg text-muted">{t.heroBody}</p>
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href="/app" className="rounded-lg bg-brand px-5 py-3 font-medium text-brand-foreground">
          {t.ctaMember}
        </Link>
        <Link href="/desk" className="rounded-lg border border-border px-5 py-3 font-medium">
          {t.ctaStaff}
        </Link>
      </div>

      <h2 className="mt-16 text-sm font-semibold uppercase tracking-wide text-muted">{t.sectionsTitle}</h2>
      <ul className="mt-4 grid gap-3 sm:grid-cols-2">
        {SECTIONS.map((section) => (
          <li key={section}>
            <Link
              href={`/${section}`}
              className="block rounded-xl border border-border bg-surface p-4 hover:border-brand"
            >
              <span className="font-mono text-xs text-muted">/{section}</span>
              <span className="mt-1 block font-medium">{t.sections[section]}</span>
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}
