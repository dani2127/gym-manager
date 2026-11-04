import { API_URL } from "@/lib/api";
import type { Member } from "@/lib/types";

const SIZES = { sm: "h-9 w-9 text-sm", md: "h-12 w-12 text-lg", lg: "h-28 w-28 text-4xl" } as const;

/** Member photo (served by the API with the staff session) or initials on ink. */
export function MemberAvatar({ member, size = "md" }: { member: Pick<Member, "first_name" | "last_name" | "photo_url">; size?: keyof typeof SIZES }) {
  const initials = `${member.first_name[0] ?? ""}${member.last_name[0] ?? ""}`.toUpperCase();

  if (member.photo_url) {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- private, cookie-authenticated API image; next/image cannot forward the session
      <img src={`${API_URL}/api/v1${member.photo_url}`} alt="" className={`${SIZES[size]} shrink-0 rounded-[6px] bg-surface-2 object-cover`} />
    );
  }

  return (
    <span aria-hidden className={`${SIZES[size]} font-bib grid shrink-0 place-items-center rounded-[6px] bg-ink text-ground`}>
      {initials}
    </span>
  );
}

export function StatusBadge({ status, label }: { status: Member["status"]; label: string }) {
  const tone = {
    active: "bg-infield-soft text-infield",
    inactive: "bg-surface-2 text-graphite ring-1 ring-line ring-inset",
    banned: "bg-critical-soft text-critical",
  }[status];

  return <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone}`}>{label}</span>;
}
