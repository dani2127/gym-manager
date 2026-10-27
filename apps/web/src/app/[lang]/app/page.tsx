import Image from "next/image";

import entrance from "@/assets/images/member-welcome.jpg";
import { Bib } from "@/components/brand/bib";
import { loadLocale } from "@/i18n/dictionaries";

export default async function MemberHomePage({ params }: PageProps<"/[lang]/app">) {
  const { dict } = await loadLocale(params);
  const t = dict.member;

  return (
    <div className="space-y-6">
      <div className="relative -mx-5 aspect-[4/3] overflow-hidden">
        <Image
          src={entrance}
          alt={t.photoAlt}
          fill
          sizes="(min-width: 448px) 448px, 100vw"
          placeholder="blur"
          loading="eager"
          className="object-cover object-[50%_58%]"
        />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/70 to-transparent" />
        <h1 className="font-bib absolute bottom-4 left-5 text-4xl uppercase text-white">{t.greeting}</h1>
      </div>
      <Bib
        gym="Lion Fitness"
        branch="Bole"
        number="0427"
        name="Hana Tesfaye"
        plan={dict.landing.bibPlan}
        validUntil={dict.landing.bibValid}
        qrValue="SAMPLE PASS: not a real member"
        caption={t.sampleNote}
      />
      <p className="rounded-[6px] border border-line bg-surface px-4 py-3 text-sm text-graphite">{t.installHint}</p>
    </div>
  );
}
