import Link from "next/link";

import { LanguageSwitcher } from "@/components/language-switcher";
import { DictionaryProvider } from "@/i18n/dictionary-context";
import { loadLocale } from "@/i18n/dictionaries";

export default async function AuthLayout({ children, params }: LayoutProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <DictionaryProvider locale={locale} dict={dict}>
      <div className="flex min-h-full flex-1 flex-col items-center justify-center px-4 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-6 flex items-center justify-between">
            <Link href="/" className="font-semibold">
              {dict.meta.title}
            </Link>
            <LanguageSwitcher locale={locale} label={dict.common.language} />
          </div>
          <div className="rounded-2xl border border-border bg-surface p-6 shadow-sm">{children}</div>
        </div>
      </div>
    </DictionaryProvider>
  );
}
