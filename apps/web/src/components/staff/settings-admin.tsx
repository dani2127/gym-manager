"use client";

import { useState } from "react";
import useSWR from "swr";

import { Alert, Card, Field, Input, PageHeader, Select, Toggle } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { Settings } from "@/lib/types";

/** Organization-wide tax and feature settings. Each change saves immediately. */
export function SettingsAdmin() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const { data, mutate } = useSWR<{ settings: Settings }>("/settings", swrFetcher);
  const [error, setError] = useState<string | null>(null);
  const t = dict.settingsPage;
  const editable = me.abilities.manage_settings;

  if (!data) return <p className="text-sm text-muted">{dict.common.loading}</p>;

  const settings = data.settings;

  async function save(patch: Partial<Settings>) {
    setError(null);
    try {
      await mutate(apiFetch<{ settings: Settings }>("/settings", { method: "PATCH", json: { settings: patch } }), {
        optimisticData: { settings: { ...settings, ...patch } },
        rollbackOnError: true,
      });
    } catch (e) {
      setError(e instanceof ApiError ? (Object.values(e.errors)[0]?.[0] ?? e.message) : dict.common.error);
    }
  }

  return (
    <>
      <PageHeader title={t.title} description={editable ? undefined : t.ownerOnly} />
      <div className="grid gap-4 lg:grid-cols-2">
        {error && (
          <div className="lg:col-span-2">
            <Alert tone="error">{error}</Alert>
          </div>
        )}
        <Card title={t.tax}>
          <div className="divide-y divide-border">
            <Toggle label={t.taxEnabled} checked={settings["tax.enabled"]} disabled={!editable} onChange={(v) => save({ "tax.enabled": v })} />
            <div className="grid grid-cols-2 gap-4 py-3">
              <Field label={t.taxName}>
                {(p) => (
                  <Select
                    {...p}
                    value={settings["tax.name"]}
                    disabled={!editable}
                    onChange={(e) => save({ "tax.name": e.target.value as Settings["tax.name"] })}
                  >
                    <option value="VAT">VAT</option>
                    <option value="TOT">TOT</option>
                  </Select>
                )}
              </Field>
              <Field label={t.taxRate}>
                {(p) => (
                  <Input
                    {...p}
                    key={settings["tax.rate"]}
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    defaultValue={settings["tax.rate"]}
                    disabled={!editable}
                    onBlur={(e) => {
                      const rate = Number(e.target.value);
                      if (rate !== settings["tax.rate"]) save({ "tax.rate": rate });
                    }}
                  />
                )}
              </Field>
            </div>
            <Toggle
              label={t.pricesInclusive}
              description={t.pricesInclusiveHelp}
              checked={settings["tax.prices_inclusive"]}
              disabled={!editable}
              onChange={(v) => save({ "tax.prices_inclusive": v })}
            />
          </div>
        </Card>
        <Card title={t.features}>
          <div className="divide-y divide-border">
            <Toggle label={t.freeze} checked={settings["freeze.enabled"]} disabled={!editable} onChange={(v) => save({ "freeze.enabled": v })} />
            <Toggle label={t.lockers} checked={settings["lockers.enabled"]} disabled={!editable} onChange={(v) => save({ "lockers.enabled": v })} />
            <Toggle label={t.chapa} checked={settings["chapa.enabled"]} disabled={!editable} onChange={(v) => save({ "chapa.enabled": v })} />
          </div>
        </Card>
      </div>
    </>
  );
}
