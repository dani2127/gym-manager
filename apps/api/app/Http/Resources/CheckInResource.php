<?php

namespace App\Http\Resources;

use App\CheckIns\CheckInGate;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin CheckIn */
class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'result' => $this->result,
            'reason' => $this->denial_reason,
            'overridable' => $this->result === 'denied' && in_array($this->denial_reason, CheckInGate::OVERRIDABLE, true),
            'override_reason' => $this->override_reason,
            'method' => $this->method,
            'checked_in_at' => $this->checked_in_at,
            'checked_out_at' => $this->checked_out_at,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->ulid, 'name' => $this->branch->name]),
            'member' => $this->whenLoaded('member', fn () => self::member($this->member)),
            'membership' => $this->whenLoaded('membership', fn () => $this->membership ? self::membership($this->membership) : null),
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->name),
        ];
    }

    /** @return array<string, mixed> */
    public static function member(Member $member): array
    {
        return [
            'id' => $member->ulid,
            'member_no' => $member->code,
            'full_name' => $member->full_name,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'status' => $member->status,
            'photo_url' => $member->photo_path ? "/members/{$member->ulid}/photo?v=".substr(md5($member->photo_path), 0, 8) : null,
            'home_branch' => $member->relationLoaded('homeBranch') ? $member->homeBranch?->name : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function membership(Membership $membership): array
    {
        $today = Carbon::parse(Carbon::now(config('gym.timezone'))->toDateString());

        return [
            'id' => $membership->ulid,
            'plan' => $membership->planName(),
            'status' => $membership->status,
            'state' => $membership->stateOn($today),
            'starts_on' => $membership->starts_on->toDateString(),
            'ends_on' => $membership->ends_on?->toDateString(),
            'days_left' => $membership->ends_on && $membership->ends_on->gte($today) ? (int) $today->diffInDays($membership->ends_on) + 1 : null,
            'visits_total' => $membership->visits_total,
            'visits_remaining' => $membership->visits_remaining,
        ];
    }
}
