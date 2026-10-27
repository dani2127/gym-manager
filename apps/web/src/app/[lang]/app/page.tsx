import { Bib } from "@/components/brand/bib";
import { loadLocale } from "@/i18n/dictionaries";

export default async function MemberHomePage({ params }: PageProps<"/[lang]/app">) {
  const { dict } = await loadLocale(params);
  const t = dict.member;

  return (
    <div className="space-y-6">
      <h1 className="font-bib text-4xl uppercase">{t.greeting}</h1>
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
