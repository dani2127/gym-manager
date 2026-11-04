import type { MetadataRoute } from "next";

/** Install manifest for the member PWA. Scoped to /app so staff screens are not part of it. */
export default function manifest(): MetadataRoute.Manifest {
  return {
    id: "/app",
    name: "Gym Member",
    short_name: "Gym",
    description: "Your gym pass: QR check-in that works offline, membership status and receipts.",
    start_url: "/app",
    scope: "/app",
    display: "standalone",
    orientation: "portrait",
    background_color: "#e9eae7",
    theme_color: "#161816",
    icons: [
      { src: "/icons/icon-192.png", sizes: "192x192", type: "image/png" },
      { src: "/icons/icon-512.png", sizes: "512x512", type: "image/png" },
      { src: "/icons/icon-maskable-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
    ],
  };
}
