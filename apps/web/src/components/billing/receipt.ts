import { API_URL } from "@/lib/api";

/**
 * Opens the PDF receipt in a new tab. The window opens before the download
 * so pop-up blockers treat it as part of the click.
 */
export async function openReceipt(invoiceId: string, format: "a5" | "thermal" = "a5") {
  const tab = window.open("", "_blank");
  const response = await fetch(`${API_URL}/api/v1/invoices/${invoiceId}/receipt?format=${format}`, { credentials: "include", headers: { Accept: "application/pdf" } });
  if (!response.ok) {
    tab?.close();
    throw new Error(String(response.status));
  }
  const url = URL.createObjectURL(await response.blob());
  if (tab) tab.location.href = url;
  else window.location.href = url;
  setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
