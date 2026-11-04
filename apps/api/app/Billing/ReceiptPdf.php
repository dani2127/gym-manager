<?php

namespace App\Billing;

use App\Models\Invoice;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

/**
 * Bilingual (English / Amharic) PDF receipt. mPDF ships Abyssinica SIL, so
 * Ethiopic text renders without extra fonts. Two paper sizes: A5 for office
 * printers and 80 mm rolls for thermal receipt printers.
 */
class ReceiptPdf
{
    public const FORMATS = ['a5', 'thermal'];

    public function render(Invoice $invoice, string $format = 'a5'): string
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $thermal = $format === 'thermal';
        $mpdf = new Mpdf([
            'tempDir' => $tempDir,
            'mode' => 'utf-8',
            // A roll has no fixed length; size it to the content.
            'format' => $thermal ? [80, 110 + 12 * ($invoice->items->count() + $invoice->payments->count())] : 'A5',
            'margin_left' => $thermal ? 4 : 12,
            'margin_right' => $thermal ? 4 : 12,
            'margin_top' => $thermal ? 4 : 12,
            'margin_bottom' => $thermal ? 4 : 12,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetTitle($invoice->number);
        $mpdf->SetAuthor($invoice->organization->name);
        $mpdf->WriteHTML(view('receipts.invoice', [
            'invoice' => $invoice,
            'thermal' => $thermal,
            'timezone' => $invoice->organization->timezone ?: config('gym.timezone'),
        ])->render());

        return $mpdf->Output('', 'S');
    }
}
