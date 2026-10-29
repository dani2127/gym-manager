import { format } from "@/i18n/dictionary-context";
import type { Dictionary } from "@/i18n/dictionaries";
import type { Plan } from "@/lib/types";

/** "30 days", "10 visits", "10 visits in 60 days". */
export function planSummary(plan: Pick<Plan, "type" | "duration_days" | "visit_count">, dict: Dictionary): string {
  const t = dict.billing.pricing;
  const days = plan.duration_days === 1 ? t.oneDay : format(t.days, { n: String(plan.duration_days ?? 0) });
  const visits = plan.visit_count === 1 ? t.oneVisit : format(t.visits, { n: String(plan.visit_count ?? 0) });

  if (plan.type === "time") return days;
  if (plan.type === "visits" || !plan.duration_days) return visits;
  return format(t.visitsWithin, { visits, days });
}
