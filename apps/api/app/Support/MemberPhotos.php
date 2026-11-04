<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Member photos: centre-cropped to a square, resized to 512px and re-encoded
 * as JPEG (which also strips camera metadata such as GPS). Stored on the
 * private disk; only served through the authorized photo endpoint.
 */
class MemberPhotos
{
    public const SIZE = 512;

    public function store(Member $member, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw new RuntimeException('Unreadable image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $jpeg = (string) ob_get_clean();

        $path = "members/{$member->organization_id}/{$member->ulid}-".Str::random(8).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $this->delete($member);
        $member->forceFill(['photo_path' => $path])->save();

        return $path;
    }

    public function delete(Member $member): void
    {
        if ($member->photo_path) {
            Storage::disk('local')->delete($member->photo_path);
        }
    }
}
