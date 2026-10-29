<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Member app routes: the login must belong to a member of this gym. */
class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Member::where('user_id', $request->user()?->id)->first();
        abort_unless($member, 403, __('members.no_member_profile'));

        $request->attributes->set('member', $member);

        return $next($request);
    }
}
