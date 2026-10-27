import Link from "next/link";

import { LanguageSwitcher } from "@/components/language-switcher";
import { loadLocale } from "@/i18n/dictionaries";

export default async function PublicLayout({ children, params }: LayoutProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <div className="flex min-h-full flex-1 flex-col">
      <header className="flex items-center justify-between gap-4 px-6 py-4">
        <Link href="/" className="font-semibold">
          {dict.meta.title}
        </Link>
        <nav className="flex items-center gap-4 text-sm">
          <Link href="/app">{dict.public.nav.member}</Link>
          <Link href="/desk">{dict.public.nav.staff}</Link>
          <LanguageSwitcher locale={locale} label={dict.common.language} />
        </nav>
      </header>
      {children}
    </div>
  );
}
