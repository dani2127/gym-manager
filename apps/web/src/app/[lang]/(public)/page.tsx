import { Check, CircleCheck, Clock } from "lucide-react";
import Image from "next/image";
import Link from "next/link";

import dawnRun from "@/assets/images/dawn-run.jpg";
import gymTraining from "@/assets/images/addis-gym-training.jpg";

import { Bib } from "@/components/brand/bib";
import { TrackLanes } from "@/components/brand/lanes";
import { StartForm } from "@/components/marketing/start-form";
import { loadLocale } from "@/i18n/dictionaries";

export default async function HomePage({ params }: PageProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);
  const t = dict.landing;
  const am = locale === "am";

  return (
    <main className="flex-1">
      {/* First viewport: the offer, the working action, and the bib. */}
      <section className="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-14 pb-12 sm:px-8 lg:grid-cols-[1.15fr_0.85fr] lg:pt-20">
        <div>
          <h1 className={`font-bib uppercase ${am ? "text-[clamp(3rem,7vw,5.5rem)] leading-[1.05]" : "text-[clamp(3.5rem,9vw,7.5rem)]"}`}>
            <span className="block">{t.headline1}</span>
            <span className="block text-track">{t.headline2}</span>
          </h1>
          <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-graphite">{t.lede}</p>
          <div className="mt-8">
            <StartForm label={t.gymNameLabel} placeholder={t.gymNamePlaceholder} cta={t.startCta} note={t.startNote} />
          </div>
        </div>
        <div className="mx-auto w-full max-w-md lg:rotate-[1.5deg]">
          <Bib
            gym="Lion Fitness"
            branch="Bole"
            number="0427"
            name="Hana Tesfaye"
            plan={t.bibPlan}
            validUntil={t.bibValid}
            qrValue="SAMPLE PASS: not a real member"
            caption={t.bibCaption}
          />
        </div>
      </section>

      {/* The track: one lane per branch. */}
      <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-8">
        <h2 className="mb-3 text-sm font-semibold text-graphite">{t.lanesLabel}</h2>
        <TrackLanes
          label={t.lanesLabel}
          caption={t.lanesCaption}
          lanes={[
            { name: "Bole", value: 41, max: 60, detail: `41 ${t.inside}` },
            { name: "Piassa", value: 18, max: 45, detail: `18 ${t.inside}` },
            { name: "Megenagna", value: 27, max: 50, detail: `27 ${t.inside}` },
            { name: "Sarbet", value: 9, max: 40, detail: `9 ${t.inside}` },
          ]}
        />
      </section>

      {/* Dawn on Entoto: the hour this product is built for. */}
      <section className="relative">
        <div className="relative h-[clamp(260px,42vw,640px)] overflow-hidden">
          <Image
            src={dawnRun}
            alt={t.dawnAlt}
            fill
            sizes="100vw"
            placeholder="blur"
            className="object-cover object-[70%_center]"
          />
          {/* Mist-coloured scrim keeps the headline readable over the trees. */}
          <div aria-hidden className="absolute inset-y-0 left-0 hidden w-1/2 bg-gradient-to-r from-[#e9eae7]/85 via-[#e9eae7]/45 to-transparent lg:block" />
          <div className="absolute inset-0 mx-auto hidden max-w-7xl items-center px-8 lg:flex">
            <p className="font-bib max-w-md text-6xl uppercase text-[#141514]">{t.dawnTitle}</p>
          </div>
        </div>
        <p className="font-bib mx-auto max-w-7xl px-4 pt-8 text-4xl uppercase sm:px-8 lg:hidden">{t.dawnTitle}</p>
      </section>

      {/* Three moments, each shown as the product doing the job. */}
      <section id="how" className="border-y border-line bg-surface">
        <div className="mx-auto max-w-7xl px-4 py-20 sm:px-8">
          <h2 className={`font-bib max-w-3xl uppercase ${am ? "text-4xl leading-tight" : "text-5xl sm:text-6xl"}`}>{t.momentsTitle}</h2>

          <div className="mt-14 grid gap-x-12 gap-y-16 lg:grid-cols-12">
            <Moment className="lg:col-span-5" title={t.doorTitle} body={t.doorBody}>
              <div className="rounded-[6px] border border-line bg-ground p-4">
                <div className="flex items-center gap-4">
                  <span className="grid h-14 w-14 place-items-center rounded-[6px] bg-ink font-bib text-2xl text-ground">HT</span>
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold">Hana Tesfaye</p>
                    <p className="text-sm text-graphite tabular">#0427 · {t.bibPlan}</p>
                  </div>
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-infield-soft px-3 py-1 text-sm font-semibold text-infield">
                    <CircleCheck aria-hidden className="h-4 w-4" />
                    {t.doorAllowed}
                  </span>
                </div>
                <div className="mt-4 flex items-center justify-between border-t border-line pt-4">
                  <span className="text-sm text-graphite">{t.doorDaysLeft}</span>
                  <span className="rounded-[6px] bg-ink px-4 py-2 text-sm font-semibold text-ground">{t.doorLetIn}</span>
                </div>
              </div>
            </Moment>

            <Moment className="lg:col-span-7" title={t.payTitle} body={t.payBody}>
              <div className="divide-y divide-line rounded-[6px] border border-line">
                <div className="grid grid-cols-[1fr_auto] items-center gap-3 p-4 sm:grid-cols-[1fr_auto_auto]">
                  <div>
                    <p className="font-semibold">Dawit Bekele · Telebirr</p>
                    <p className="text-sm text-graphite tabular">FT26301K7Q2M · ETB 2,500.00</p>
                  </div>
                  <span className="hidden items-center gap-1.5 text-sm text-amber sm:inline-flex">
                    <Clock aria-hidden className="h-4 w-4" />
                    {t.payPending}
                  </span>
                  <span className="inline-flex items-center gap-1.5 rounded-[6px] bg-infield px-3 py-2 text-sm font-semibold text-white">
                    <Check aria-hidden className="h-4 w-4" />
                    {t.payApprove}
                  </span>
                </div>
                <div className="grid grid-cols-[1fr_auto] items-center gap-3 bg-critical-soft/60 p-4">
                  <div>
                    <p className="font-semibold">Meron Alemu · CBE</p>
                    <p className="text-sm text-graphite tabular">FT26301K7Q2M · ETB 2,500.00</p>
                  </div>
                  <span className="text-sm font-semibold text-critical">{t.payDuplicate}</span>
                </div>
              </div>
            </Moment>

            <div className="grid gap-6 border-t border-line pt-12 lg:col-span-12 lg:grid-cols-12 lg:gap-12">
              <div className="lg:col-span-5">
                <h3 className="text-xl font-semibold">{t.tgTitle}</h3>
                <p className="mt-2 max-w-prose text-graphite">{t.tgBody}</p>
              </div>
              <div className="rounded-[6px] bg-[#e7ebe4] p-4 sm:p-6 lg:col-span-7 dark:bg-asphalt-2">
                <div className="max-w-sm rounded-[14px] rounded-bl-[4px] bg-white p-3 text-sm leading-relaxed text-[#141514] shadow-sm">
                  <p className="mb-1 text-xs font-semibold text-[#9c3320]">Lion Fitness</p>
                  {t.tgMessage}
                  <p className="mt-1 text-right text-[11px] text-[#565a56] tabular">07:02</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Local truths, set as a results board rather than icon cards. */}
      <section className="mx-auto max-w-7xl px-4 py-20 sm:px-8">
        <div className="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
          <div className="relative aspect-[3/2] overflow-hidden rounded-[6px]">
            <Image
              src={gymTraining}
              alt={t.trainingAlt}
              fill
              sizes="(min-width: 1024px) 50vw, 100vw"
              placeholder="blur"
              className="object-cover"
            />
          </div>
          <div>
            <h2 className={`font-bib mb-8 uppercase ${am ? "text-4xl leading-tight" : "text-5xl sm:text-6xl"}`}>{t.localTitle}</h2>
            <ul className="border-t-2 border-ink">
              {[t.localBirr, t.localLang, t.localPhone, t.localBranches].map((item) => (
                <li key={item} className="grid grid-cols-[2.5rem_1fr] items-start gap-4 border-b border-line py-5">
                  <span className="mt-3 h-1 w-8 rounded-full bg-track" aria-hidden />
                  <span className="text-lg">{item}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* Close on the track. */}
      <section className="track-band text-lane">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-8 px-4 py-16 sm:px-8">
          <div>
            <h2 className={`font-bib uppercase ${am ? "text-4xl leading-tight" : "text-5xl sm:text-7xl"}`}>{t.closeTitle}</h2>
            <p className="mt-3 text-lg">{t.closeBody}</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Link href="/register" className="inline-flex h-12 items-center rounded-[6px] bg-white px-6 font-semibold text-track-deep transition-opacity hover:opacity-90">
              {t.startCta}
            </Link>
            <Link href="/login" className="inline-flex h-12 items-center rounded-[6px] border-2 border-white px-6 font-semibold text-white transition-colors hover:bg-white/10">
              {t.navSignIn}
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}

function Moment({ title, body, className = "", children }: { title: string; body: string; className?: string; children: React.ReactNode }) {
  return (
    <div className={className}>
      <h3 className="text-xl font-semibold">{title}</h3>
      <p className="mt-2 max-w-prose text-graphite">{body}</p>
      <div className="mt-6">{children}</div>
    </div>
  );
}
