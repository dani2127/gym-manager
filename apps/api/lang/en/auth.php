<?php

return [

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'logged_in' => 'Signed in.',
    'logged_out' => 'Signed out.',
    'device_name_required' => 'Non-browser clients must send a device_name to receive a token.',
    'account_disabled' => 'This account has been disabled. Please contact your gym.',
    'organization_suspended' => 'This gym account is suspended.',

    'reset_code_sent' => 'If this account exists and has Telegram linked, we have sent a code. Otherwise please ask the front desk to reset your password.',
    'code_invalid' => 'The code is invalid or has expired.',
    'password_reset_done' => 'Your password has been reset. Please sign in.',
    'password_changed' => 'Your password has been changed.',
    'profile_updated' => 'Profile updated.',

    'telegram_not_configured' => 'Telegram is not set up for this gym yet.',
    'telegram_required' => 'Link your Telegram account first to receive codes.',
    'telegram_unlinked' => 'Telegram disconnected.',
    'verification_code_sent' => 'We sent a verification code to your Telegram.',
    'phone_verified' => 'Phone number verified.',

    'branch_not_manageable' => 'You cannot assign staff to this branch.',
    'role_not_assignable' => 'Only the owner can assign the manager role.',

    'telegram_link_first' => 'Connect Telegram first: tap the button and press Start in the bot.',
    'two_factor_expired' => 'The sign-in took too long. Enter your phone and password again.',
    'two_factor_invalid' => 'That code is not right. Check the code in your authenticator app and try again.',
    'two_factor_already_on' => 'Two-step login is already on.',
    'two_factor_required_cannot_disable' => 'Your gym requires two-step login for owners and managers, so it can\'t be turned off.',
    'two_factor_setup_required' => 'Your gym requires two-step login. Turn it on in My account to continue.',
];
