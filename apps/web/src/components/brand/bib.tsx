import QRCode from "qrcode";

type BibProps = {
  gym: string;
  branch: string;
  number: string;
  name: string;
  plan: string;
  validUntil: string;
  /** Text encoded in the QR. Sample passes encode a sample marker, never a real token. */
  qrValue: string;
  caption?: string;
  className?: string;
};

/**
 * The member pass drawn as a race bib: sponsor band, big number, pin holes,
 * and a real, scannable QR code. Server component (renders the QR as SVG).
 */
export async function Bib({ gym, branch, number, name, plan, validUntil, qrValue, caption, className = "" }: BibProps) {
  const qr = await QRCode.toString(qrValue, { type: "svg", margin: 0, errorCorrectionLevel: "M", color: { dark: "#141514", light: "#ffffff" } });

  return (
    <figure className={className}>
      <div className="relative overflow-hidden rounded-[12px] bg-white text-[#141514] shadow-[0_24px_48px_-24px_rgb(20_21_20/0.45),0_2px_6px_rgb(20_21_20/0.12)]">
        {/* Safety-pin holes */}
        {["left-3 top-3", "right-3 top-3", "left-3 bottom-3", "right-3 bottom-3"].map((pos) => (
          <span key={pos} aria-hidden className={`absolute ${pos} h-2.5 w-2.5 rounded-full bg-ground ring-1 ring-black/10`} />
        ))}
        <div className="flex items-center justify-between bg-track px-8 py-2.5 text-lane">
          <span className="font-bib text-lg uppercase">{gym}</span>
          <span className="text-xs font-semibold tracking-wide uppercase">{branch}</span>
        </div>
        <div className="grid grid-cols-[1fr_auto] items-end gap-4 px-8 pt-4 pb-7">
          <div className="min-w-0">
            <p className="font-bib tabular text-[clamp(4.5rem,9vw,7rem)]">{number}</p>
            <p className="mt-3 truncate text-base font-semibold">{name}</p>
            <p className="text-sm text-[#565a56]">
              {plan} · {validUntil}
            </p>
          </div>
          <div className="w-24 sm:w-28" role="img" aria-label="QR code" dangerouslySetInnerHTML={{ __html: qr }} />
        </div>
      </div>
      {caption && <figcaption className="mt-3 text-center text-xs text-graphite">{caption}</figcaption>}
    </figure>
  );
}
