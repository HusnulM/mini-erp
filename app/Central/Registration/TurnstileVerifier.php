<?php

namespace App\Central\Registration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile. Fails closed: a network error rejects the form, so
 * registration never runs without a captcha check.
 */
class TurnstileVerifier implements CaptchaVerifier
{
    public function __construct(private readonly array $config) {}

    public function verify(?string $token, ?string $ip = null): bool
    {
        if ($token === null || $token === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post($this->config['verify_url'], array_filter([
                'secret' => $this->config['secret_key'],
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (ConnectionException) {
            return false;
        }

        return $response->ok() && $response->json('success') === true;
    }

    public function siteKey(): string
    {
        return (string) $this->config['site_key'];
    }
}
