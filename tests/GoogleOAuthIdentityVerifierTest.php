<?php

declare(strict_types=1);

namespace Alma\Auth\Tests;

use Alma\Auth\Services\GoogleOAuthIdentityVerifier;
use Illuminate\Support\Facades\Http;

final class GoogleOAuthIdentityVerifierTest extends TestCase
{
    private GoogleOAuthIdentityVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'alma-auth.oauth_google.client_id' => 'test-client-id.apps.googleusercontent.com',
        ]);

        $this->verifier = new GoogleOAuthIdentityVerifier;
    }

    public function test_access_token_returns_sub_and_email(): void
    {
        Http::fake([
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-sub-99',
                'email' => 'ada@example.com',
            ], 200),
        ]);

        $info = $this->verifier->verify('google', [
            'access_token' => 'ya29.fake-access',
        ]);

        $this->assertSame('google', $info->provider);
        $this->assertSame('google-sub-99', $info->providerUserId);
        $this->assertSame('ada@example.com', $info->email);
    }

    public function test_id_token_validates_audience(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-sub-42',
                'aud' => 'test-client-id.apps.googleusercontent.com',
                'email' => 'ada@example.com',
                'exp' => (string) (time() + 3600),
            ], 200),
        ]);

        $info = $this->verifier->verify('google', [
            'id_token' => 'eyJ.fake.id.token',
        ]);

        $this->assertSame('google-sub-42', $info->providerUserId);
    }

    public function test_id_token_rejects_wrong_audience(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-sub-42',
                'aud' => 'other-client.apps.googleusercontent.com',
                'exp' => (string) (time() + 3600),
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oauth_token_audience_mismatch');

        $this->verifier->verify('google', [
            'id_token' => 'eyJ.fake.id.token',
        ]);
    }

    public function test_non_google_provider_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oauth_provider_unsupported');

        $this->verifier->verify('apple', [
            'access_token' => 'x',
        ]);
    }

    public function test_invalid_access_token_rejected(): void
    {
        Http::fake([
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['error' => 'invalid'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oauth_token_invalid');

        $this->verifier->verify('google', [
            'access_token' => 'bad',
        ]);
    }

    public function test_prefers_id_token_when_both_present(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'from-id-token',
                'aud' => 'test-client-id.apps.googleusercontent.com',
                'exp' => (string) (time() + 3600),
            ], 200),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'from-access-token',
            ], 200),
        ]);

        $info = $this->verifier->verify('google', [
            'id_token' => 'eyJ.id',
            'access_token' => 'ya29.access',
        ]);

        $this->assertSame('from-id-token', $info->providerUserId);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'userinfo'));
    }
}
