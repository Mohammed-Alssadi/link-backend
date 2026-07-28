<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_salla_oauth_redirect_returns_redirect_url(): void
    {
        $response = $this->get('/api/auth/salla/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.salla.sa/oauth2/auth', $response->headers->get('Location'));
    }

    public function test_zid_oauth_redirect_returns_redirect_url(): void
    {
        $response = $this->get('/api/auth/zid/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('oauth.zid.sa/oauth/authorize', $response->headers->get('Location'));
    }

    public function test_salla_callback_missing_code_redirects_with_error(): void
    {
        $response = $this->get('/api/auth/salla/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?error=code_missing', $response->headers->get('Location'));
    }

    public function test_zid_callback_missing_code_redirects_with_error(): void
    {
        $response = $this->get('/api/auth/zid/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?error=code_missing', $response->headers->get('Location'));
    }

    public function test_salla_callback_successful_authentication(): void
    {
        Http::fake([
            'https://accounts.salla.sa/oauth2/token' => Http::response([
                'access_token' => 'mock_salla_access_token',
                'refresh_token' => 'mock_salla_refresh_token',
                'expires_in' => 2592000,
            ], 200),
            'https://accounts.salla.sa/oauth2/user/info' => Http::response([
                'data' => [
                    'id' => 12345,
                    'name' => 'Salla Merchant',
                    'email' => 'merchant@salla.sa',
                    'merchant' => [
                        'id' => '998877',
                        'name' => 'Test Salla Store',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get('/api/auth/salla/callback?code=mock_authorization_code');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
        $this->assertDatabaseHas('users', ['email' => 'merchant@salla.sa']);
        $this->assertDatabaseHas('oauth_tokens', [
            'platform' => 'salla',
            'merchant' => '998877',
            'store_name' => 'Test Salla Store',
        ]);
    }

    public function test_zid_callback_successful_authentication(): void
    {
        Http::fake([
            'https://oauth.zid.sa/oauth/token' => Http::response([
                'access_token' => 'mock_zid_manager_token',
                'authorization' => 'mock_zid_auth_token',
                'refresh_token' => 'mock_zid_refresh_token',
                'expires_in' => 2592000,
            ], 200),
            'https://api.zid.sa/v1/managers/account/profile' => Http::response([
                'user' => [
                    'id' => '776655',
                    'name' => 'Zid Merchant',
                    'email' => 'merchant@zid.sa',
                ],
                'store' => [
                    'id' => '776655',
                    'title' => 'Test Zid Store',
                ],
            ], 200),
            'https://api.zid.sa/v1/managers/account/store' => Http::response([
                'store' => [
                    'id' => '776655',
                    'title' => 'Test Zid Store',
                ],
            ], 200),
        ]);

        $response = $this->get('/api/auth/zid/callback?code=mock_authorization_code');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?token=', $response->headers->get('Location'));
        $this->assertDatabaseHas('users', ['email' => 'merchant@zid.sa']);
        $this->assertDatabaseHas('oauth_tokens', [
            'platform' => 'zid',
            'merchant' => '776655',
            'store_name' => 'Test Zid Store',
        ]);
    }
}
