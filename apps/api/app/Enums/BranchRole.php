<?php

namespace App\Enums;

enum BranchRole: string
{
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case Trainer = 'trainer';
}
