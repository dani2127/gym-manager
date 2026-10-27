import Link from "next/link";

import { Logo } from "@/components/brand/logo";
import { LanguageSwitcher } from "@/components/language-switcher";
import { DictionaryProvider } from "@/i18n/dictionary-context";
import { loadLocale } from "@/i18n/dictionaries";

/** Sign-in, sign-up and password reset: the form on concrete, the track beside it. */
export default async function AuthLayout({ children, params }: LayoutProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <DictionaryProvider locale={locale} dict={dict}>
      <div className="grid min-h-full flex-1 lg:grid-cols-[1fr_minmax(0,34rem)]">
        <aside
          className="track-field relative hidden flex-col justify-between p-10 text-lane lg:flex"
          style={{ ["--lane-h" as string]: "88px" }}
          aria-hidden
        >
          <Link href="/" tabIndex={-1}>
            <Logo name={dict.meta.title} tone="lane" />
          </Link>
          {/* The headline breaks the lanes: a solid block of track, so no line runs through the type. */}
          <p className={`font-bib -mx-10 max-w-xl bg-track px-10 py-8 uppercase ${locale === "am" ? "text-5xl leading-tight" : "text-7xl"}`}>
            {dict.landing.headline1}
            <br />
            {dict.landing.headline2}
          </p>
        </aside>
        <div className="flex flex-col bg-ground px-4 py-8 sm:px-10">
          <div className="flex items-center justify-between">
            <Link href="/" className="lg:invisible" aria-label={dict.meta.title}>
              <Logo name={dict.meta.title} />
            </Link>
            <LanguageSwitcher locale={locale} label={dict.common.language} />
          </div>
          <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">{children}</div>
        </div>
      </div>
    </DictionaryProvider>
  );
}
