<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipResource;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MembershipController extends Controller
{
    /** A member's memberships, newest first. Every staff member may see them (check-in needs it). */
    public function index(Request $request, Member $member): AnonymousResourceCollection
    {
        $this->authorize('view', $member);

        $memberships = $member->memberships()
            ->with('branch', 'invoice', 'freezes.requester', 'freezes.reviewer')
            ->latest('starts_on')
            ->latest('id')
            ->limit(50)
            ->get();

        return MembershipResource::collection($memberships);
    }
}
