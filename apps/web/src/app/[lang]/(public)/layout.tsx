import Link from "next/link";

import { Logo } from "@/components/brand/logo";
import { LanguageSwitcher } from "@/components/language-switcher";
import { loadLocale } from "@/i18n/dictionaries";

export default async function PublicLayout({ children, params }: LayoutProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);
  const t = dict.landing;

  return (
    <div className="flex min-h-full flex-1 flex-col">
      <header className="border-b border-line bg-ground/90 backdrop-blur-sm">
        <div className="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-8">
          <Link href="/" aria-label={dict.meta.title}>
            <Logo name={dict.meta.title} />
          </Link>
          <nav className="hidden items-center gap-6 text-sm font-medium text-graphite md:flex">
            <a href="#how" className="hover:text-ink">
              {t.navFeatures}
            </a>
            <Link href="/app" className="hover:text-ink">
              {t.navMember}
            </Link>
          </nav>
          <div className="ml-auto flex items-center gap-3">
            <LanguageSwitcher locale={locale} label={dict.common.language} />
            <Link href="/login" className="text-sm font-medium whitespace-nowrap hover:underline">
              {t.navSignIn}
            </Link>
            <Link
              href="/register"
              className="hidden h-10 items-center whitespace-nowrap rounded-[6px] bg-ink px-4 text-sm font-semibold text-ground transition-opacity hover:opacity-85 sm:inline-flex"
            >
              {t.navStart}
            </Link>
          </div>
        </div>
      </header>
      {children}
      <footer className="border-t border-line">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-graphite sm:px-8">
          <Logo name={dict.meta.title} />
          <p>{t.footerNote}</p>
          <Link href="/login" className="hover:text-ink">
            {t.navSignIn}
          </Link>
        </div>
      </footer>
    </div>
  );
}
