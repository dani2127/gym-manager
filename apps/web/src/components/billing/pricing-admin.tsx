"use client";

import { Pencil, Plus } from "lucide-react";
import { useState, type FormEvent, type ReactNode } from "react";
import useSWR from "swr";

import { planSummary } from "@/components/billing/plan-summary";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select, Toggle } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { birrToCents, centsToBirr, formatDate, formatMoney } from "@/lib/format";
import { isOwner } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { AccessScope, DiscountKind, DiscountRule, Fee, FeeKind, Plan, PlanType, Settings } from "@/lib/types";

type Editing<T> = T | "new" | null;

/** Plans, fees and discounts. Owners edit; managers see what the desk sells. */
export function PricingAdmin() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const t = dict.billing.pricing;
  const owner = isOwner(me);

  return (
    <>
      <PageHeader title={t.title} description={t.description} />
      <div className="space-y-6">
        {!owner && <Alert>{t.readOnly}</Alert>}
        <PlansCard editable={owner} />
        <FeesCard editable={owner} />
        {owner && <DiscountsCard />}
      </div>
    </>
  );
}

function AddButton({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <Button variant="secondary" onClick={onClick} className="min-h-9 py-1">
      <Plus aria-hidden className="h-4 w-4" />
      {label}
    </Button>
  );
}

function EditButton({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className="grid h-9 w-9 shrink-0 cursor-pointer place-items-center rounded-[6px] text-graphite hover:bg-surface-2 hover:text-ink"
    >
      <Pencil aria-hidden className="h-4 w-4" />
    </button>
  );
}

function OffBadge({ label }: { label: string }) {
  return <span className="rounded-full bg-surface-2 px-2 py-0.5 text-xs font-semibold text-graphite ring-1 ring-line ring-inset">{label}</span>;
}

/** English name required, Amharic optional. */
function NameFields({ value, error }: { value?: { en: string; am: string | null }; error?: string }) {
  const { dict } = useDictionary();
  const t = dict.billing.pricing;

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field label={t.nameEn} error={error}>
        {(p) => <Input {...p} name="name_en" defaultValue={value?.en} required maxLength={80} />}
      </Field>
      <Field label={t.nameAm} hint={t.amHint}>
        {(p) => <Input {...p} name="name_am" defaultValue={value?.am ?? ""} maxLength={80} lang="am" />}
      </Field>
    </div>
  );
}

function names(form: FormData) {
  const am = String(form.get("name_am") ?? "").trim();
  return { en: String(form.get("name_en") ?? "").trim(), am: am || null };
}

function FormActions({ busy, onCancel, extra }: { busy: boolean; onCancel: () => void; extra?: ReactNode }) {
  const { dict } = useDictionary();

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Button type="submit" busy={busy}>
        {busy ? dict.common.saving : dict.common.save}
      </Button>
      <Button type="button" variant="secondary" onClick={onCancel}>
        {dict.common.cancel}
      </Button>
      {extra && <span className="ml-auto">{extra}</span>}
    </div>
  );
}

/* ------------------------------------------------------------------ Plans */

function PlansCard({ editable }: { editable: boolean }) {
  const { locale, dict } = useDictionary();
  const t = dict.billing.pricing;
  const { data, mutate } = useSWR<{ data: Plan[] }>("/plans", swrFetcher);
  const [editing, setEditing] = useState<Editing<Plan>>(null);
  const done = () => {
    setEditing(null);
    mutate();
  };

  return (
    <Card title={t.plansTitle} actions={editable && editing === null && <AddButton label={t.addPlan} onClick={() => setEditing("new")} />}>
      {editing === "new" && <PlanForm onDone={done} onCancel={() => setEditing(null)} />}
      {!data ? (
        <div className="h-32 animate-pulse rounded-[6px] bg-surface-2" />
      ) : data.data.length === 0 && editing !== "new" ? (
        <p className="text-sm text-graphite">{t.plansEmpty}</p>
      ) : (
        <ul className="divide-y divide-line">
          {data.data.map((plan) =>
            editing !== "new" && editing?.id === plan.id ? (
              <li key={plan.id} className="py-4">
                <PlanForm plan={plan} onDone={done} onCancel={() => setEditing(null)} />
              </li>
            ) : (
              <li key={plan.id} className="flex items-center gap-4 py-3">
                <span aria-hidden className={`h-10 w-1.5 shrink-0 rounded-full ${plan.is_active ? "bg-track" : "bg-line"}`} />
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">
                    {plan.name}
                    {locale === "en" && plan.name_translations.am && <span className="ml-2 text-sm font-normal text-graphite">{plan.name_translations.am}</span>}
                    {!plan.is_active && <span className="ml-2"><OffBadge label={t.notOnSale} /></span>}
                  </p>
                  <p className="text-xs text-graphite">
                    {planSummary(plan, dict)} · {t.accessTypes[plan.access_scope]}
                    {plan.access_scope === "selected" && plan.branches?.length ? ` (${plan.branches.map((b) => b.name).join(", ")})` : ""}
                  </p>
                </div>
                <span className="font-bib tabular text-xl">{formatMoney(plan.price_cents, locale).replace(/\.00/, "")}</span>
                {editable && editing === null && <EditButton label={`${t.editPlan}: ${plan.name}`} onClick={() => setEditing(plan)} />}
              </li>
            ),
          )}
        </ul>
      )}
    </Card>
  );
}

function TaxHint() {
  const { dict } = useDictionary();
  const t = dict.billing.pricing;
  const { data } = useSWR<{ settings: Settings }>("/settings", swrFetcher);
  if (!data) return null;
  const s = data.settings;
  const values = { name: dict.billing.taxNames[s["tax.name"]], rate: String(s["tax.rate"]) };
  return <>{!s["tax.enabled"] ? t.taxOff : s["tax.prices_inclusive"] ? format(t.taxInclusive, values) : format(t.taxExclusive, values)}</>;
}

function PlanForm({ plan, onDone, onCancel }: { plan?: Plan; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const { me } = useSession();
  const t = dict.billing.pricing;
  const { busy, error, general, run } = useFormAction();
  const [type, setType] = useState<PlanType>(plan?.type ?? "time");
  const [scope, setScope] = useState<AccessScope>(plan?.access_scope ?? "home_branch");
  const [freeze, setFreeze] = useState(plan?.freeze_allowed ?? false);
  const [active, setActive] = useState(plan?.is_active ?? true);
  const [confirmArchive, setConfirmArchive] = useState(false);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const number = (name: string) => {
      const v = String(form.get(name) ?? "").trim();
      return v === "" ? null : Number(v);
    };

    run(async () => {
      await apiFetch(plan ? `/plans/${plan.id}` : "/plans", {
        method: plan ? "PATCH" : "POST",
        json: {
          name: names(form),
          type,
          duration_days: number("duration_days"),
          visit_count: type === "time" ? null : number("visit_count"),
          price_cents: birrToCents(String(form.get("price") ?? "")),
          access_scope: scope,
          ...(scope === "selected" && { branches: form.getAll("branches").map(String) }),
          freeze_allowed: freeze,
          max_freeze_days: freeze ? number("max_freeze_days") : null,
          max_freezes: freeze ? number("max_freezes") : null,
          is_active: active,
        },
      });
      onDone();
    });
  }

  function archive() {
    if (!plan) return;
    run(async () => {
      await apiFetch(`/plans/${plan.id}`, { method: "DELETE" });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mb-4 space-y-5 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      <h3 className="font-semibold">{plan ? t.editPlan : t.addPlan}</h3>
      {general && <Alert tone="error">{general}</Alert>}
      <NameFields value={plan?.name_translations} error={error?.field("name.en") ?? error?.field("name")} />

      <fieldset>
        <legend className="mb-1.5 text-sm font-semibold">{t.type}</legend>
        <div role="radiogroup" className="grid gap-2 sm:grid-cols-3">
          {(["time", "visits", "time_visits"] as const).map((option) => (
            <button
              key={option}
              type="button"
              role="radio"
              aria-checked={type === option}
              onClick={() => setType(option)}
              className="cursor-pointer rounded-[6px] border border-line bg-surface p-3 text-left text-sm transition-colors hover:border-ink aria-checked:border-ink aria-checked:ring-2 aria-checked:ring-ink"
            >
              <span className="block font-semibold">{t.types[option]}</span>
              <span className="mt-0.5 block text-xs text-graphite">{t.typeHints[option]}</span>
            </button>
          ))}
        </div>
      </fieldset>

      <div className="grid gap-4 sm:grid-cols-3">
        <Field label={t.durationDays} error={error?.field("duration_days")}>
          {(p) => <Input {...p} name="duration_days" type="number" min={1} max={1095} inputMode="numeric" defaultValue={plan?.duration_days ?? (type === "visits" ? "" : 30)} />}
        </Field>
        {type !== "time" && (
          <Field label={t.visitCount} error={error?.field("visit_count")}>
            {(p) => <Input {...p} name="visit_count" type="number" min={1} max={1000} inputMode="numeric" defaultValue={plan?.visit_count ?? 10} />}
          </Field>
        )}
        <Field label={t.price} error={error?.field("price_cents")}>
          {(p) => <Input {...p} name="price" inputMode="decimal" className="tabular" defaultValue={plan ? centsToBirr(plan.price_cents) : ""} required />}
        </Field>
      </div>
      <p className="-mt-2 text-xs text-graphite">
        <TaxHint />
      </p>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t.access}>
          {(p) => (
            <Select {...p} value={scope} onChange={(e) => setScope(e.target.value as AccessScope)}>
              {(["home_branch", "all_branches", "selected"] as const).map((s) => (
                <option key={s} value={s}>
                  {t.accessTypes[s]}
                </option>
              ))}
            </Select>
          )}
        </Field>
        {scope === "selected" && (
          <fieldset>
            <legend className="mb-1 text-sm font-semibold">{dict.nav.branches}</legend>
            <div className="flex flex-wrap gap-x-4 gap-y-2 pt-1.5">
              {me.branches.map((b) => (
                <label key={b.id} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" name="branches" value={b.id} defaultChecked={plan?.branches?.some((x) => x.id === b.id)} className="h-4 w-4 accent-[var(--track)]" />
                  {b.name}
                </label>
              ))}
            </div>
            {error?.field("branches") && <p className="mt-1 text-xs font-medium text-critical">{error.field("branches")}</p>}
          </fieldset>
        )}
      </div>

      <div className="divide-y divide-line rounded-[6px] border border-line bg-surface px-4">
        <Toggle label={t.freezeAllowed} description={t.freezeHint} checked={freeze} onChange={setFreeze} />
        {freeze && (
          <div className="grid gap-4 py-3 sm:grid-cols-2">
            <Field label={t.maxFreezeDays} error={error?.field("max_freeze_days")}>
              {(p) => <Input {...p} name="max_freeze_days" type="number" min={1} max={365} defaultValue={plan?.max_freeze_days ?? 7} />}
            </Field>
            <Field label={t.maxFreezes} error={error?.field("max_freezes")}>
              {(p) => <Input {...p} name="max_freezes" type="number" min={1} max={12} defaultValue={plan?.max_freezes ?? 1} />}
            </Field>
          </div>
        )}
        <Toggle label={t.onSale} checked={active} onChange={setActive} />
      </div>

      <FormActions
        busy={busy}
        onCancel={onCancel}
        extra={
          plan &&
          (confirmArchive ? (
            <span className="flex flex-wrap items-center gap-2 text-sm">
              {format(t.archiveConfirm, { name: plan.name })}
              <Button type="button" variant="danger" onClick={archive} busy={busy}>
                {t.archive}
              </Button>
            </span>
          ) : (
            <Button type="button" variant="danger" onClick={() => setConfirmArchive(true)}>
              {t.archive}
            </Button>
          ))
        }
      />
    </form>
  );
}

/* ------------------------------------------------------------------- Fees */

function FeesCard({ editable }: { editable: boolean }) {
  const { locale, dict } = useDictionary();
  const t = dict.billing.pricing;
  const { data, mutate } = useSWR<{ data: Fee[] }>("/fees", swrFetcher);
  const [editing, setEditing] = useState<Editing<Fee>>(null);
  const done = () => {
    setEditing(null);
    mutate();
  };

  return (
    <Card title={t.feesTitle} actions={editable && editing === null && <AddButton label={t.addFee} onClick={() => setEditing("new")} />}>
      {editing === "new" && <FeeForm onDone={done} onCancel={() => setEditing(null)} />}
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : data.data.length === 0 && editing !== "new" ? (
        <p className="text-sm text-graphite">{t.feesEmpty}</p>
      ) : (
        <ul className="divide-y divide-line">
          {data.data.map((fee) =>
            editing !== "new" && editing?.id === fee.id ? (
              <li key={fee.id} className="py-4">
                <FeeForm fee={fee} onDone={done} onCancel={() => setEditing(null)} />
              </li>
            ) : (
              <li key={fee.id} className="flex items-center gap-4 py-3">
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">
                    {fee.name}
                    {!fee.is_active && <span className="ml-2"><OffBadge label={t.inactive} /></span>}
                  </p>
                  <p className="text-xs text-graphite">
                    {[fee.name !== t.feeKinds[fee.kind] ? t.feeKinds[fee.kind] : null, fee.kind === "registration" ? t.registrationHint : null].filter(Boolean).join(" · ")}
                  </p>
                </div>
                <span className="tabular font-semibold">{formatMoney(fee.amount_cents, locale)}</span>
                {editable && editing === null && <EditButton label={`${dict.common.edit}: ${fee.name}`} onClick={() => setEditing(fee)} />}
              </li>
            ),
          )}
        </ul>
      )}
    </Card>
  );
}

function FeeForm({ fee, onDone, onCancel }: { fee?: Fee; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const t = dict.billing.pricing;
  const { busy, error, general, run } = useFormAction();
  const [active, setActive] = useState(fee?.is_active ?? true);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    run(async () => {
      await apiFetch(fee ? `/fees/${fee.id}` : "/fees", {
        method: fee ? "PATCH" : "POST",
        json: { name: names(form), kind: form.get("kind"), amount_cents: birrToCents(String(form.get("amount") ?? "")), is_active: active },
      });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mb-4 space-y-4 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <NameFields value={fee?.name_translations} error={error?.field("name.en") ?? error?.field("name")} />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={dict.billing.pricing.type}>
          {(p) => (
            <Select {...p} name="kind" defaultValue={fee?.kind ?? "registration"}>
              {(["registration", "card_replacement", "other"] as FeeKind[]).map((k) => (
                <option key={k} value={k}>
                  {t.feeKinds[k]}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.amount} error={error?.field("amount_cents")}>
          {(p) => <Input {...p} name="amount" inputMode="decimal" className="tabular" defaultValue={fee ? centsToBirr(fee.amount_cents) : ""} required />}
        </Field>
      </div>
      <div className="rounded-[6px] border border-line bg-surface px-4">
        <Toggle label={t.active} checked={active} onChange={setActive} />
      </div>
      <FormActions busy={busy} onCancel={onCancel} />
    </form>
  );
}

/* -------------------------------------------------------------- Discounts */

function discountValue(rule: Pick<DiscountRule, "value_type" | "value">, dict: ReturnType<typeof useDictionary>["dict"], locale: "en" | "am") {
  const t = dict.billing.pricing;
  return rule.value_type === "percent" ? format(t.percentOff, { value: String(rule.value) }) : format(t.fixedOff, { amount: formatMoney(rule.value, locale) });
}

function DiscountsCard() {
  const { locale, dict } = useDictionary();
  const t = dict.billing.pricing;
  const { data, mutate } = useSWR<{ data: DiscountRule[] }>("/discount-rules", swrFetcher);
  const [editing, setEditing] = useState<Editing<DiscountRule>>(null);
  const done = () => {
    setEditing(null);
    mutate();
  };

  return (
    <Card title={t.discountsTitle} actions={editing === null && <AddButton label={t.addDiscount} onClick={() => setEditing("new")} />}>
      {editing === "new" && <DiscountForm onDone={done} onCancel={() => setEditing(null)} />}
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : data.data.length === 0 && editing !== "new" ? (
        <p className="text-sm text-graphite">{t.discountsEmpty}</p>
      ) : (
        <ul className="divide-y divide-line">
          {data.data.map((rule) =>
            editing !== "new" && editing?.id === rule.id ? (
              <li key={rule.id} className="py-4">
                <DiscountForm rule={rule} onDone={done} onCancel={() => setEditing(null)} />
              </li>
            ) : (
              <li key={rule.id} className="flex items-center gap-4 py-3">
                <span className="w-24 shrink-0 rounded-full bg-track-soft px-2 py-0.5 text-center text-xs font-semibold text-track-deep">{t.discountKinds[rule.kind]}</span>
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">
                    {rule.name}
                    {rule.promo_code && <span className="ml-2 font-mono text-xs text-graphite">{rule.promo_code}</span>}
                    {!rule.is_active && <span className="ml-2"><OffBadge label={t.inactive} /></span>}
                  </p>
                  <p className="text-xs text-graphite">
                    {rule.plan ? rule.plan.name : t.allPlans}
                    {rule.valid_until && ` · ${format(t.expires, { date: formatDate(rule.valid_until, locale) })}`}
                    {rule.requires_proof && ` · ${dict.billing.sale.needsId}`}
                  </p>
                </div>
                <span className="tabular font-semibold text-infield">{discountValue(rule, dict, locale)}</span>
                <EditButton label={`${dict.common.edit}: ${rule.name}`} onClick={() => setEditing(rule)} />
              </li>
            ),
          )}
        </ul>
      )}
    </Card>
  );
}

function DiscountForm({ rule, onDone, onCancel }: { rule?: DiscountRule; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const t = dict.billing.pricing;
  const { busy, error, general, run } = useFormAction();
  const { data: plans } = useSWR<{ data: Plan[] }>("/plans", swrFetcher);
  const [kind, setKind] = useState<DiscountKind>(rule?.kind ?? "student");
  const [valueType, setValueType] = useState<"percent" | "fixed">(rule?.value_type ?? "percent");
  const [proof, setProof] = useState(rule?.requires_proof ?? true);
  const [active, setActive] = useState(rule?.is_active ?? true);
  const group = kind === "couple" || kind === "family" || kind === "corporate";

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const text = (name: string) => {
      const v = String(form.get(name) ?? "").trim();
      return v === "" ? null : v;
    };
    const raw = String(form.get("value") ?? "");

    run(async () => {
      await apiFetch(rule ? `/discount-rules/${rule.id}` : "/discount-rules", {
        method: rule ? "PATCH" : "POST",
        json: {
          name: names(form),
          kind,
          value_type: valueType,
          value: valueType === "percent" ? Number(raw) || null : birrToCents(raw),
          min_group_size: group && text("min_group_size") ? Number(text("min_group_size")) : null,
          requires_proof: kind === "student" ? proof : false,
          promo_code: kind === "promo" ? text("promo_code") : null,
          plan: text("plan"),
          valid_from: text("valid_from"),
          valid_until: text("valid_until"),
          is_active: active,
        },
      });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mb-4 space-y-4 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <NameFields value={rule?.name_translations} error={error?.field("name.en") ?? error?.field("name")} />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={dict.billing.pricing.type} hint={t.kindHints[kind]}>
          {(p) => (
            <Select {...p} value={kind} onChange={(e) => setKind(e.target.value as DiscountKind)}>
              {(["student", "couple", "family", "corporate", "promo"] as const).map((k) => (
                <option key={k} value={k}>
                  {t.discountKinds[k]}
                </option>
              ))}
            </Select>
          )}
        </Field>
        {kind === "promo" && (
          <Field label={t.promoCode} error={error?.field("promo_code")}>
            {(p) => <Input {...p} name="promo_code" defaultValue={rule?.promo_code ?? ""} className="font-mono uppercase" maxLength={40} />}
          </Field>
        )}
        {group && (
          <Field label={t.minGroupSize} error={error?.field("min_group_size")}>
            {(p) => <Input {...p} name="min_group_size" type="number" min={2} max={500} defaultValue={rule?.min_group_size ?? ""} />}
          </Field>
        )}
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t.valueType}>
          {(p) => (
            <Select {...p} value={valueType} onChange={(e) => setValueType(e.target.value as "percent" | "fixed")}>
              <option value="percent">{t.percent}</option>
              <option value="fixed">{t.fixed}</option>
            </Select>
          )}
        </Field>
        <Field label={valueType === "percent" ? t.percentValue : t.fixedValue} error={error?.field("value")}>
          {(p) => (
            <Input
              {...p}
              key={valueType}
              name="value"
              inputMode="decimal"
              className="tabular"
              defaultValue={rule && rule.value_type === valueType ? (valueType === "percent" ? String(rule.value) : centsToBirr(rule.value)) : ""}
              required
            />
          )}
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label={t.appliesTo}>
          {(p) => (
            <Select {...p} name="plan" defaultValue={rule?.plan?.id ?? ""}>
              <option value="">{t.allPlans}</option>
              {plans?.data.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.validFrom} error={error?.field("valid_from")}>
          {(p) => <Input {...p} name="valid_from" type="date" defaultValue={rule?.valid_from ?? ""} />}
        </Field>
        <Field label={t.validUntil} error={error?.field("valid_until")}>
          {(p) => <Input {...p} name="valid_until" type="date" defaultValue={rule?.valid_until ?? ""} />}
        </Field>
      </div>
      <div className="divide-y divide-line rounded-[6px] border border-line bg-surface px-4">
        {kind === "student" && <Toggle label={t.requiresProof} checked={proof} onChange={setProof} />}
        <Toggle label={t.active} checked={active} onChange={setActive} />
      </div>
      <FormActions busy={busy} onCancel={onCancel} />
    </form>
  );
}
