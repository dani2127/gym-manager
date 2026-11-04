"use client";

import { MonitorSmartphone, Plus, Trash2 } from "lucide-react";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { useLockersEnabled } from "@/components/checkins/locker-picker";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import { managesBranch } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Kiosk, Locker, LockerZone } from "@/lib/types";

const TILE = {
  free: "border-line-strong bg-surface text-ink hover:border-ink",
  used: "border-track bg-track-soft text-track-deep",
  broken: "border-line bg-surface-2 text-graphite line-through",
} as const;

/** Lockers and entrance kiosks for one branch. Owners and branch managers. */
export function DoorAdmin() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.checkins.door;
  const branches = me.branches.filter((b) => managesBranch(me, b.id));
  const [branchId, setBranchId] = useState(branches.some((b) => b.id === branch?.id) ? branch?.id : branches[0]?.id);

  if (!branchId) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader
        title={t.title}
        description={t.description}
        actions={
          branches.length > 1 && (
            <label className="block w-56">
              <span className="sr-only">{t.branch}</span>
              <Select value={branchId} onChange={(e) => setBranchId(e.target.value)}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            </label>
          )
        }
      />
      <div className="space-y-6">
        <LockersCard key={`l-${branchId}`} branchId={branchId} />
        <KiosksCard key={`k-${branchId}`} branchId={branchId} />
      </div>
    </>
  );
}

function LockersCard({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const t = dict.checkins.door;
  const zones = dict.checkins.lockers.zones;
  const enabled = useLockersEnabled(branchId);
  const { data, mutate } = useSWR<{ data: Locker[]; meta: { total: number; in_use: number; free: number } }>(`/branches/${branchId}/lockers`, swrFetcher);
  const [selected, setSelected] = useState<Locker | null>(null);
  const add = useFormAction();
  const act = useFormAction();

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    add.run(async () => {
      const res = await apiFetch<{ data: Locker[]; meta: { total: number; in_use: number; free: number } }>(`/branches/${branchId}/lockers`, {
        json: { from: Number(form.get("from")), to: Number(form.get("to")), prefix: String(form.get("prefix") ?? "").trim() || null, zone: form.get("zone") },
      });
      await mutate(res, { revalidate: false });
    });
  }

  const byZone = (["female", "male", "unisex"] as LockerZone[]).map((zone) => [zone, data?.data.filter((l) => l.zone === zone) ?? []] as const).filter(([, list]) => list.length > 0);

  return (
    <Card title={t.lockersTitle} actions={data && data.meta.total > 0 && <span className="text-sm text-graphite">{format(t.summary, { free: String(data.meta.free), used: String(data.meta.in_use), total: String(data.meta.total) })}</span>}>
      <div className="space-y-5">
        {!enabled && <Alert>{t.lockersOff}</Alert>}
        {!data ? (
          <div className="h-24 animate-pulse rounded-[6px] bg-surface-2" />
        ) : data.data.length === 0 ? (
          <p className="text-sm text-graphite">{t.lockersEmpty}</p>
        ) : (
          <>
            <div className="flex flex-wrap gap-4 text-xs text-graphite">
              <Legend className={TILE.free} label={t.legendFree} />
              <Legend className={TILE.used} label={t.legendUsed} />
              <Legend className={TILE.broken} label={t.legendBroken} />
            </div>
            {byZone.map(([zone, list]) => (
              <div key={zone}>
                <h3 className="mb-2 text-xs font-semibold text-graphite">{zones[zone]}</h3>
                <div className="grid grid-cols-[repeat(auto-fill,minmax(3.25rem,1fr))] gap-1.5">
                  {list.map((l) => {
                    const state = l.status === "out_of_order" ? "broken" : l.in_use_by ? "used" : "free";
                    return (
                      <button
                        key={l.id}
                        type="button"
                        aria-pressed={selected?.id === l.id}
                        title={l.in_use_by ? format(t.usedBy, { name: l.in_use_by.member.full_name }) : undefined}
                        onClick={() => setSelected(selected?.id === l.id ? null : l)}
                        className={`tabular h-11 cursor-pointer rounded-[6px] border text-sm font-semibold aria-pressed:ring-2 aria-pressed:ring-ink ${TILE[state]}`}
                      >
                        {l.number}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </>
        )}

        {selected && (
          <div className="flex flex-wrap items-center gap-3 rounded-[6px] border border-line bg-surface-2 p-3 text-sm">
            <span className="font-bib tabular text-2xl">{selected.number}</span>
            <span className="text-graphite">
              {zones[selected.zone]}
              {selected.in_use_by && ` · ${format(t.usedBy, { name: selected.in_use_by.member.full_name })}`}
            </span>
            <span className="ml-auto flex gap-2">
              <Button
                variant="secondary"
                className="min-h-9 py-1"
                busy={act.busy}
                onClick={() =>
                  act.run(async () => {
                    await apiFetch(`/branches/${branchId}/lockers/${selected.id}`, { method: "PATCH", json: { status: selected.status === "available" ? "out_of_order" : "available" } });
                    setSelected(null);
                    await mutate();
                  })
                }
              >
                {selected.status === "available" ? t.markBroken : t.markFixed}
              </Button>
              <Button
                variant="danger"
                className="min-h-9 py-1"
                disabled={Boolean(selected.in_use_by)}
                busy={act.busy}
                onClick={() =>
                  act.run(async () => {
                    await apiFetch(`/branches/${branchId}/lockers/${selected.id}`, { method: "DELETE" });
                    setSelected(null);
                    await mutate();
                  })
                }
              >
                <Trash2 aria-hidden className="h-4 w-4" />
                {t.remove}
              </Button>
            </span>
            {(act.error?.field("locker") || act.general) && <p className="w-full text-xs font-medium text-critical">{act.error?.field("locker") ?? act.general}</p>}
          </div>
        )}

        <form onSubmit={submit} className="grid gap-3 border-t border-line pt-5 sm:grid-cols-[repeat(4,minmax(0,1fr))_auto] sm:items-start" noValidate>
          <Field label={t.from} error={add.error?.field("from")}>
            {(p) => <Input {...p} name="from" type="number" min={1} defaultValue={(data?.meta.total ?? 0) + 1} required />}
          </Field>
          <Field label={t.to} error={add.error?.field("to")}>
            {(p) => <Input {...p} name="to" type="number" min={1} defaultValue={(data?.meta.total ?? 0) + 20} required />}
          </Field>
          <Field label={t.prefix} hint={t.prefixHint} error={add.error?.field("prefix")}>
            {(p) => <Input {...p} name="prefix" maxLength={3} className="uppercase" />}
          </Field>
          <Field label={t.zone}>
            {(p) => (
              <Select {...p} name="zone" defaultValue="unisex">
                {(["female", "male", "unisex"] as const).map((z) => (
                  <option key={z} value={z}>
                    {zones[z]}
                  </option>
                ))}
              </Select>
            )}
          </Field>
          <Button type="submit" busy={add.busy} className="sm:mt-6">
            <Plus aria-hidden className="h-4 w-4" />
            {t.add}
          </Button>
          {add.general && <p className="text-xs font-medium text-critical sm:col-span-5">{add.general}</p>}
        </form>
      </div>
    </Card>
  );
}

function Legend({ className, label }: { className: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span aria-hidden className={`h-3.5 w-5 rounded-[3px] border ${className}`} />
      {label}
    </span>
  );
}

function KiosksCard({ branchId }: { branchId: string }) {
  const { locale, dict } = useDictionary();
  const t = dict.checkins.door;
  const { data, mutate } = useSWR<{ data: Kiosk[] }>(`/branches/${branchId}/kiosks`, swrFetcher, { refreshInterval: 10_000 });
  const [pairing, setPairing] = useState<Kiosk | null>(null);
  const [adding, setAdding] = useState(false);
  const [removing, setRemoving] = useState<string | null>(null);
  const { busy, error, general, run } = useFormAction();
  const kioskUrl = typeof window !== "undefined" ? `${window.location.origin}/kiosk` : "/kiosk";

  // Close the code once the tablet has paired.
  const paired = pairing && data?.data.find((k) => k.id === pairing.id)?.status === "paired";

  function add(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = String(new FormData(event.currentTarget).get("name") ?? "");
    run(async () => {
      const res = await apiFetch<{ data: Kiosk }>(`/branches/${branchId}/kiosks`, { json: { name } });
      setPairing(res.data);
      setAdding(false);
      await mutate();
    });
  }

  return (
    <Card
      title={t.kiosksTitle}
      actions={
        !adding && (
          <Button variant="secondary" className="min-h-9 py-1" onClick={() => setAdding(true)}>
            <Plus aria-hidden className="h-4 w-4" />
            {t.addKiosk}
          </Button>
        )
      }
    >
      <div className="space-y-4">
        <p className="text-sm text-graphite">{t.kiosksHint}</p>
        {general && <Alert tone="error">{general}</Alert>}

        {adding && (
          <form onSubmit={add} className="flex flex-wrap items-end gap-3" noValidate>
            <div className="min-w-56 flex-1">
              <Field label={t.kioskName} error={error?.field("name")}>
                {(p) => <Input {...p} name="name" placeholder={t.kioskNamePlaceholder} autoFocus required maxLength={80} />}
              </Field>
            </div>
            <Button type="submit" busy={busy} className={error?.field("name") ? "mb-5" : ""}>
              {t.addKiosk}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setAdding(false)}>
              {dict.common.cancel}
            </Button>
          </form>
        )}

        {pairing?.pairing_code && !paired && (
          <div className="rounded-[6px] border-2 border-track bg-track-soft p-5 text-center">
            <p className="text-sm font-semibold">{format(t.pairTitle, { name: pairing.name })}</p>
            <p className="font-bib tabular my-3 text-5xl tracking-wider">{pairing.pairing_code}</p>
            <p className="mx-auto max-w-md text-sm text-graphite">{format(t.pairSteps, { url: kioskUrl })}</p>
            <Button variant="secondary" className="mt-4" onClick={() => setPairing(null)}>
              {t.close}
            </Button>
          </div>
        )}

        {data && data.data.length === 0 && !adding ? (
          <p className="text-sm text-graphite">{t.kiosksEmpty}</p>
        ) : (
          <ul className="divide-y divide-line">
            {data?.data.map((k) => (
              <li key={k.id} className="flex flex-wrap items-center gap-3 py-3">
                <MonitorSmartphone aria-hidden className="h-5 w-5 text-graphite" />
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold">{k.name}</span>
                  <span className="block text-xs text-graphite">{k.last_seen_at ? format(t.lastSeen, { time: formatDateTime(k.last_seen_at, locale) }) : t.never}</span>
                </span>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${k.status === "paired" ? "bg-infield-soft text-infield" : "bg-amber-soft text-amber"}`}>{t.status[k.status]}</span>
                {removing === k.id ? (
                  <span className="flex w-full flex-wrap items-center justify-end gap-2 text-sm">
                    {t.removeConfirm}
                    <Button
                      variant="danger"
                      className="min-h-9 py-1"
                      busy={busy}
                      onClick={() =>
                        run(async () => {
                          await apiFetch(`/branches/${branchId}/kiosks/${k.id}`, { method: "DELETE" });
                          setRemoving(null);
                          await mutate();
                        })
                      }
                    >
                      {t.removeKiosk}
                    </Button>
                    <Button variant="secondary" className="min-h-9 py-1" onClick={() => setRemoving(null)}>
                      {dict.common.cancel}
                    </Button>
                  </span>
                ) : (
                  <span className="flex gap-2">
                    <Button
                      variant="secondary"
                      className="min-h-9 py-1"
                      busy={busy}
                      onClick={() =>
                        run(async () => {
                          const res = await apiFetch<{ data: Kiosk }>(`/branches/${branchId}/kiosks/${k.id}/pair`, { method: "POST" });
                          setPairing(res.data);
                          await mutate();
                        })
                      }
                    >
                      {t.repair}
                    </Button>
                    <Button variant="danger" className="min-h-9 py-1" onClick={() => setRemoving(k.id)}>
                      {t.removeKiosk}
                    </Button>
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </Card>
  );
}
