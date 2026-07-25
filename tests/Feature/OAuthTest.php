<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SallaService;
use App\Services\ZidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_salla_oauth_redirect_returns_redirect_url(): void
    {
        $response = $this->get('/auth/salla');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.salla.sa/oauth2/auth', $response->headers->get('Location'));
    }

    public function test_zid_oauth_redirect_returns_redirect_url(): void
    {
        $response = $this->get('/auth/zid');

        $response->assertRedirect();
        $this->assertStringContainsString('oauth.zid.sa/oauth/authorize', $response->headers->get('Location'));
    }

    public function test_salla_callback_missing_code_redirects_with_error(): void
    {
        $response = $this->get('/auth/salla/callback');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Authorization code missing.');
    }

    public function test_zid_callback_missing_code_redirects_with_error(): void
    {
        $response = $this->get('/auth/zid/callback');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Authorization code missing.');
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

        $response = $this->get('/auth/salla/callback?code=mock_authorization_code');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
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
                    'title' => 'Test Zid Store',
                ],
            ], 200),
        ]);

        $response = $this->get('/auth/zid/callback?code=mock_authorization_code');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'merchant@zid.sa']);
        $this->assertDatabaseHas('oauth_tokens', [
            'platform' => 'zid',
            'merchant' => '776655',
            'store_name' => 'Test Zid Store',
        ]);
    }
}
