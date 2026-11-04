<?php

namespace App\Billing;

use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Screenshots of Telebirr or bank transfers. Re-encoded as JPEG (no larger
 * than 1600px, camera metadata stripped) and kept on the private disk; only
 * desk staff of the branch can open them.
 */
class TransferProofs
{
    public const MAX_SIDE = 1600;

    public function store(int $organizationId, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['proof' => __('billing.proof_unreadable')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $image = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $width, $height);

        ob_start();
        imagejpeg($image, null, 85);
        $path = "transfers/{$organizationId}/".Str::ulid().'.jpg';
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    }

    public function path(Payment $payment): ?string
    {
        return $payment->proof_path && Storage::disk('local')->exists($payment->proof_path) ? Storage::disk('local')->path($payment->proof_path) : null;
    }
}
