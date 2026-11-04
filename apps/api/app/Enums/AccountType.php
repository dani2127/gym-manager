<?php

namespace App\Enums;

enum AccountType: string
{
    /** Organization-wide access to every branch. */
    case Owner = 'owner';

    /** Branch-scoped access through branch_user roles. */
    case Staff = 'staff';

    case Member = 'member';
}
