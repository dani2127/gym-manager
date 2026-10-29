<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case PhoneVerify = 'phone_verify';
    case PasswordReset = 'password_reset';

    /** Given by the desk in person so a member can set their app password. */
    case AppSetup = 'app_setup';
}
