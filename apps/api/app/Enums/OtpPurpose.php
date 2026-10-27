<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case PhoneVerify = 'phone_verify';
    case PasswordReset = 'password_reset';
}
