<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Live desk: every screen working the branch's desk hears its check-ins.
Broadcast::channel('branch.{ulid}.desk', function (User $user, string $ulid) {
    $branch = Branch::withoutGlobalScopes()->where('ulid', $ulid)->first();

    return $branch !== null && $user->worksDeskAt($branch);
});
