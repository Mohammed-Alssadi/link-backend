<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_salla_store_authorize_easy_mode_webhook_creates_user_and_token(): void
    {
        config(['services.salla.authorization_mode' => 'easy']);
        config(['services.salla.webhook_secret' => null]);
        config(['services.zid.webhook_secret' => null]);

        $payload = [
            'event' => 'app.store.authorize',
            'merchant' => '123456',
            'data' => [
                'access_token' => 'test_access_token_123',
                'refresh_token' => 'test_refresh_token_123',
                'expires' => now()->timestamp + 3600,
                'merchant_email' => 'store123456@salla.sa',
                'merchant_name' => 'Store Owner',
                'store_name' => 'My Salla Store',
            ],
        ];

        $response = $this->postJson('/webhook', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'email' => 'store123456@salla.sa',
        ]);
        $this->assertDatabaseHas('oauth_tokens', [
            'platform' => 'salla',
            'merchant' => '123456',
            'access_token' => 'test_access_token_123',
        ]);
    }

    public function test_webhook_with_unknown_event_returns_200_without_error(): void
    {
        config(['services.salla.webhook_secret' => null]);
        config(['services.zid.webhook_secret' => null]);

        $payload = [
            'event' => 'custom.nonexistent_event',
            'merchant' => '123456',
            'data' => [],
        ];

        $response = $this->postJson('/webhook', $payload);

        $response->assertStatus(200);
        $response->assertSee('Ok, but without process');
    }

    public function test_webhook_rejected_when_secret_is_configured_and_invalid(): void
    {
        config(['services.salla.webhook_secret' => 'valid_salla_secret']);

        $payload = [
            'event' => 'order.created',
            'merchant' => '123456',
            'data' => [],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'wrong_secret',
        ])->postJson('/webhook', $payload);

        $response->assertStatus(403);
    }
}
