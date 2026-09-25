<?php

namespace App\Central\Registration;

/** Server-side check of the captcha token posted by the registration form. */
interface CaptchaVerifier
{
    public function verify(?string $token, ?string $ip = null): bool;

    /** Public key rendered into the form widget. */
    public function siteKey(): string;
}
