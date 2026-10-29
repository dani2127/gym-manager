import Image from "next/image";
import Link from "next/link";

import deskPhoto from "@/assets/images/signin-gym-floor.jpg";

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
        <aside className="relative hidden flex-col justify-between overflow-hidden lg:flex">
          <Image
            src={deskPhoto}
            alt={dict.auth.photoAlt}
            fill
            sizes="(min-width: 1024px) 60vw, 0px"
            placeholder="blur"
            loading="eager"
            className="object-cover object-[center_60%]"
          />
          <Link href="/" tabIndex={-1} className="relative m-10 self-start rounded-[6px] bg-white/85 px-3 py-2 backdrop-blur-sm">
            <Logo name={dict.meta.title} />
          </Link>
          {/* The headline sits in its own lane of track at the foot of the photo. */}
          <p aria-hidden className={`track-band font-bib relative px-10 py-8 uppercase text-lane ${locale === "am" ? "text-5xl leading-tight" : "text-7xl"}`}>
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
