<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Support\Audit;
use App\Support\MemberPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberPhotoController extends Controller
{
    public function __construct(private MemberPhotos $photos) {}

    public function show(Member $member): StreamedResponse
    {
        $this->authorize('view', $member);
        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            // Private: personal data, never cached by shared proxies.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function store(Request $request, Member $member): MemberResource
    {
        $this->authorize('update', $member);

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
        ]);

        try {
            $this->photos->store($member, $request->file('photo'));
        } catch (RuntimeException) {
            abort(422, __('members.photo_unreadable'));
        }

        Audit::record('member.photo_updated', $member);

        return new MemberResource($member->load('homeBranch', 'groups.members'));
    }

    public function destroy(Member $member): JsonResponse
    {
        $this->authorize('update', $member);

        $this->photos->delete($member);
        $member->forceFill(['photo_path' => null])->save();
        Audit::record('member.photo_removed', $member);

        return response()->json(status: 204);
    }
}
