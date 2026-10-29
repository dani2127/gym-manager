/**
 * The door QR code, made on the phone so it works without internet. It
 * mirrors App\CheckIns\MemberCodes on the API: G1.{member}.{window}.{sig},
 * where sig is the first 16 hex digits (upper case) of
 * HMAC-SHA256("{member}|phone|{window}", secret) and window = 30-second slot.
 */

const STORAGE_KEY = "gym.member.pass";

export type StoredPass = {
  member_id: string;
  member_no: string;
  secret: string;
  window_seconds: number;
  /** Seconds to add to this phone's clock to match the gym's server. */
  clock_offset: number;
  first_name: string;
  full_name: string;
  gym: string;
  /** Last known membership summary, shown when offline. */
  summary: string | null;
  saved_at: number;
};

export function loadPass(): StoredPass | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as StoredPass) : null;
  } catch {
    return null;
  }
}

export function savePass(pass: StoredPass) {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(pass));
  } catch {
    // Private mode: the pass works while the app stays open.
  }
}

export function clearPass() {
  try {
    window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Nothing stored.
  }
}

export function currentWindow(pass: StoredPass, nowMs = Date.now()) {
  const seconds = nowMs / 1000 + pass.clock_offset;
  const window = Math.floor(seconds / pass.window_seconds);
  const secondsLeft = Math.ceil((window + 1) * pass.window_seconds - seconds);
  return { window, secondsLeft };
}

export async function passCode(pass: StoredPass, window: number): Promise<string> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", encoder.encode(pass.secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(`${pass.member_id}|phone|${window}`));
  const hex = Array.from(new Uint8Array(signature), (b) => b.toString(16).padStart(2, "0")).join("");
  return `G1.${pass.member_id}.${window}.${hex.slice(0, 16).toUpperCase()}`;
}
