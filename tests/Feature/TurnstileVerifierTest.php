<?php

namespace Tests\Feature;

use App\Central\Registration\CaptchaVerifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnstileVerifierTest extends TestCase
{
    #[Test]
    public function it_sends_the_secret_token_and_ip_to_cloudflare(): void
    {
        config(['services.turnstile.secret_key' => 'the-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue(app(CaptchaVerifier::class)->verify('tok', '10.0.0.1'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $r['secret'] === 'the-secret' && $r['response'] === 'tok' && $r['remoteip'] === '10.0.0.1');
    }

    #[Test]
    public function it_fails_closed(): void
    {
        $verifier = app(CaptchaVerifier::class);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $this->assertFalse($verifier->verify('tok'));

        Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);
        $this->assertFalse($verifier->verify('tok'));

        $this->assertFalse($verifier->verify(''));
        $this->assertFalse($verifier->verify(null));
    }
}
