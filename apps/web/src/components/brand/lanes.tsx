export type Lane = { name: string; value: number; max: number; detail: string };

/**
 * Branches as lanes on a tartan track. Each lane shows how full it is with a
 * runner marker and a text label, so the value never depends on colour alone.
 */
export function TrackLanes({ lanes, label, caption }: { lanes: Lane[]; label: string; caption?: string }) {
  return (
    <figure className="w-full">
      <div className="track-field rounded-[6px] px-4 py-1 text-lane sm:px-6" style={{ ["--lane-h" as string]: "64px" }} role="list" aria-label={label}>
        {lanes.map((lane, index) => {
          const pct = Math.round((lane.value / lane.max) * 100);
          return (
            <div key={lane.name} role="listitem" className="grid h-16 grid-cols-[2rem_7rem_1fr_auto] items-center gap-3 sm:grid-cols-[2rem_9rem_1fr_auto]">
              <span className="font-bib text-2xl opacity-80 tabular" aria-hidden>
                {index + 1}
              </span>
              <span className="truncate text-sm font-semibold uppercase tracking-wide">{lane.name}</span>
              <span className="relative h-2 rounded-full bg-white/25">
                <span className="lane-fill absolute inset-y-0 left-0 rounded-full bg-white" style={{ width: `${pct}%` }} />
                <span
                  aria-hidden
                  className="lane-runner absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-track bg-white"
                  style={{ insetInlineStart: `${pct}%` }}
                />
              </span>
              <span className="tabular text-right text-sm font-semibold">
                {lane.detail}
              </span>
            </div>
          );
        })}
      </div>
      {caption && <figcaption className="mt-2 text-xs text-graphite">{caption}</figcaption>}
    </figure>
  );
}
