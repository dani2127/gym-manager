"use client";

import jsQR from "jsqr";
import { Camera, CameraOff, Keyboard } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { useDictionary } from "@/i18n/dictionary-context";

const SAME_CODE_PAUSE_MS = 4000;

/**
 * Reads member QR codes three ways: the webcam (decoded in the browser), a
 * USB scanner (it types the code and presses Enter), or pasting the code.
 * The same code is ignored for a few seconds so a held-up phone counts once.
 */
export function QrScanner({
  onCode,
  busy,
  variant = "desk",
}: {
  onCode: (code: string) => void;
  busy: boolean;
  /** kiosk: front camera that starts by itself, no paste box or desk hints. */
  variant?: "desk" | "kiosk";
}) {
  const { dict } = useDictionary();
  const t = dict.checkins.scanner;
  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const last = useRef<{ code: string; at: number }>({ code: "", at: 0 });
  const onCodeRef = useRef(onCode);
  const kiosk = variant === "kiosk";
  const [camera, setCamera] = useState<"off" | "starting" | "on" | "denied" | "missing">(kiosk ? "starting" : "off");
  const [manual, setManual] = useState("");

  useEffect(() => {
    onCodeRef.current = onCode;
  }, [onCode]);

  const emit = (raw: string) => {
    const code = raw.trim();
    if (code.length < 8) return;
    const now = Date.now();
    if (code === last.current.code && now - last.current.at < SAME_CODE_PAUSE_MS) return;
    last.current = { code, at: now };
    onCodeRef.current(code);
  };
  const emitRef = useRef(emit);
  useEffect(() => {
    emitRef.current = emit;
  });

  // USB scanners type very fast and end with Enter; people type slowly.
  useEffect(() => {
    let buffer = "";
    let lastKey = 0;
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null;
      if (target && ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName) && target.dataset.scanInput === undefined) return;
      const now = Date.now();
      if (now - lastKey > 80) buffer = "";
      lastKey = now;
      if (event.key === "Enter") {
        if (buffer.length >= 12) {
          event.preventDefault();
          emitRef.current(buffer);
        }
        buffer = "";
      } else if (event.key.length === 1) {
        buffer += event.key;
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Webcam: decode a frame every 150 ms while the camera is on.
  useEffect(() => {
    if (camera !== "starting" && camera !== "on") return;
    let stream: MediaStream | null = null;
    let timer: ReturnType<typeof setInterval> | undefined;
    let cancelled = false;

    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: kiosk ? "user" : "environment", width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false })
      .then((s) => {
        if (cancelled) return s.getTracks().forEach((track) => track.stop());
        stream = s;
        const video = videoRef.current;
        if (!video) return;
        video.srcObject = s;
        void video.play();
        setCamera("on");
        timer = setInterval(() => {
          const canvas = canvasRef.current;
          if (!canvas || video.readyState < 2) return;
          const width = 640;
          const height = Math.round((video.videoHeight / video.videoWidth) * width) || 360;
          canvas.width = width;
          canvas.height = height;
          const context = canvas.getContext("2d", { willReadFrequently: true });
          if (!context) return;
          context.drawImage(video, 0, 0, width, height);
          const found = jsQR(context.getImageData(0, 0, width, height).data, width, height, { inversionAttempts: "dontInvert" });
          if (found?.data) emitRef.current(found.data);
        }, 150);
      })
      .catch((error: DOMException) => setCamera(error?.name === "NotFoundError" ? "missing" : "denied"));

    return () => {
      cancelled = true;
      if (timer) clearInterval(timer);
      stream?.getTracks().forEach((track) => track.stop());
    };
  }, [camera === "off" ? "off" : "active"]); // eslint-disable-line react-hooks/exhaustive-deps -- restart only when switched on or off

  return (
    <div className="space-y-3">
      <div className="relative aspect-video overflow-hidden rounded-[6px] bg-asphalt">
        {/* Live camera preview, muted. */}
        <video ref={videoRef} muted playsInline className={`h-full w-full scale-x-[-1] object-cover ${camera === "on" ? "" : "hidden"}`} />
        <canvas ref={canvasRef} className="hidden" />
        {camera === "on" ? (
          <span aria-hidden className="pointer-events-none absolute inset-[18%] rounded-[10px] border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.25)]" />
        ) : (
          <div className="absolute inset-0 grid place-items-center p-6 text-center text-asphalt-ink">
            <div className="space-y-3">
              <CameraOff aria-hidden className="mx-auto h-8 w-8 text-asphalt-muted" />
              <p className="text-sm text-asphalt-muted">{camera === "denied" ? t.cameraDenied : camera === "missing" ? t.cameraMissing : t.cameraOff}</p>
              {camera !== "missing" && (
                <button
                  type="button"
                  onClick={() => setCamera(navigator.mediaDevices ? "starting" : "missing")}
                  className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-[6px] bg-track px-4 text-sm font-semibold text-lane hover:bg-track-deep"
                >
                  <Camera aria-hidden className="h-4 w-4" />
                  {t.startCamera}
                </button>
              )}
            </div>
          </div>
        )}
        {busy && <span className="absolute top-3 right-3 rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">{t.checking}</span>}
      </div>
      {!kiosk && (
      <>
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-graphite">
        <span className="inline-flex items-center gap-1.5">
          <Keyboard aria-hidden className="h-4 w-4" />
          {t.usbReady}
        </span>
        {camera === "on" && (
          <button type="button" onClick={() => setCamera("off")} className="cursor-pointer font-semibold hover:text-ink">
            {t.stopCamera}
          </button>
        )}
      </div>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          last.current = { code: "", at: 0 };
          emit(manual);
          setManual("");
        }}
        className="flex gap-2"
      >
        <input
          data-scan-input
          value={manual}
          onChange={(e) => setManual(e.target.value)}
          placeholder={t.pastePlaceholder}
          aria-label={t.pasteLabel}
          className="h-10 min-w-0 flex-1 rounded-[6px] border border-line-strong bg-surface px-3 font-mono text-xs outline-none focus:border-ink"
        />
        <button type="submit" className="min-h-10 cursor-pointer rounded-[6px] border border-line-strong bg-surface px-3 text-sm font-semibold hover:border-ink">
          {t.check}
        </button>
      </form>
      </>
      )}
    </div>
  );
}
