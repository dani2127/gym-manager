import { Logo } from "@/components/brand/logo";
import { LanguageSwitcher } from "@/components/language-switcher";
import { MemberTabs } from "@/components/member-tabs";
import { RegisterServiceWorker } from "@/components/register-service-worker";
import { DictionaryProvider } from "@/i18n/dictionary-context";
import { loadLocale } from "@/i18n/dictionaries";

/** Member PWA shell: mobile-first, bottom tab bar, installable (manifest scope /app). */
export default async function MemberLayout({ children, params }: LayoutProps<"/[lang]/app">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <DictionaryProvider locale={locale} dict={dict}>
      <div className="mx-auto flex min-h-dvh w-full max-w-md flex-1 flex-col bg-ground">
        <header className="flex items-center justify-between px-5 pt-[max(1rem,env(safe-area-inset-top))] pb-3">
          <Logo name={dict.meta.title} />
          <LanguageSwitcher locale={locale} label={dict.common.language} />
        </header>
        <main className="flex-1 px-5 pb-6">{children}</main>
        <MemberTabs labels={dict.member.tabs} />
        <RegisterServiceWorker />
      </div>
    </DictionaryProvider>
  );
}
