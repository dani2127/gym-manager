import { useState } from "react";

import { useDictionary } from "@/i18n/dictionary-context";
import { ApiError } from "@/lib/api";

/** Busy flag, API error and "saved" flash around an async form action. */
export function useFormAction() {
  const { dict } = useDictionary();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [saved, setSaved] = useState(false);

  async function run(action: () => Promise<unknown>): Promise<boolean> {
    setBusy(true);
    setError(null);
    setSaved(false);
    try {
      await action();
      setSaved(true);
      return true;
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, dict.common.error));
      return false;
    } finally {
      setBusy(false);
    }
  }

  /** Message for errors not tied to a visible field. */
  const general = error && Object.keys(error.errors).length === 0 ? error.message : null;

  return { busy, error, saved, general, run };
}
