/** Wordmark: a three-lane stadium track and the product name. */
export function Logo({ name, tone = "ink" }: { name: string; tone?: "ink" | "lane" }) {
  const color = tone === "lane" ? "text-lane" : "text-ink";

  return (
    <span className={`inline-flex items-center gap-2 ${color}`}>
      <svg viewBox="0 0 32 20" aria-hidden className="h-5 w-8 shrink-0">
        <rect x="1" y="1" width="30" height="18" rx="9" fill="none" stroke="currentColor" strokeWidth="2" />
        <rect x="5" y="5" width="22" height="10" rx="5" fill="none" stroke="currentColor" strokeWidth="1.5" opacity="0.7" />
        <rect x="9" y="8.5" width="14" height="3" rx="1.5" className="fill-track" />
      </svg>
      <span className="font-bib text-xl whitespace-nowrap uppercase tracking-tight">{name}</span>
    </span>
  );
}
