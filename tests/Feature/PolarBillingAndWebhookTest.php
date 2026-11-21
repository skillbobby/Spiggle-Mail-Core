<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Newsletter;
use App\Models\User;
use App\Services\Billing\PolarBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolarBillingAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_polar_service_generates_checkout_session(): void
    {
        config(['services.polar.token' => 'test_token']);
        config(['services.polar.products.starter_month' => 'prod_test_123']);

        Http::fake([
            'https://api.polar.sh/v1/checkouts/' => Http::response([
                'id' => 'chk_test_abc',
                'url' => 'https://polar.sh/checkout/chk_test_abc',
            ], 200),
        ]);

        $user = User::factory()->create(['email' => 'author@example.com']);
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Author Pub',
            'status' => 'active',
            'settings' => [],
        ]);

        $service = new PolarBillingService();
        $res = $service->createCheckoutSession($account, 'starter', 'month');

        $this->assertTrue($res['ok']);
        $this->assertEquals('https://polar.sh/checkout/chk_test_abc', $res['url']);
        $this->assertEquals('chk_test_abc', $res['checkout_id']);
    }

    public function test_refund_webhook_immediately_revokes_quota_and_halts_campaigns(): void
    {
        config(['services.polar.webhook_secret' => 'whsec_d6ImrTDK/riAbz+l8wSAeJvS+PDk8az3We+87bTh6CQ=']);

        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Refund Tester',
            'status' => 'active',
            'settings' => [
                'plan' => 'growth',
                'monthly_sends_limit' => 100000,
                'subscription_status' => 'active',
            ],
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Tech Dispatch',
            'slug' => 'tech-dispatch',
            'from_name' => 'Editor',
            'from_email' => 'editor@example.com',
            'postal_address' => '123 Test St',
        ]);

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Issue 1',
            'html_body' => '<p>Hello</p>',
            'status' => 'sending',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_refund',
            'type' => 'refund.created',
            'data' => [
                'metadata' => [
                    'account_id' => $account->id,
                ],
            ],
        ]);

        $webhookId = 'msg_test_123';
        $timestamp = (string) time();
        $secretKey = base64_decode('d6ImrTDK/riAbz+l8wSAeJvS+PDk8az3We+87bTh6CQ=');
        $sig = base64_encode(hash_hmac('sha256', "{$webhookId}.{$timestamp}.{$payload}", $secretKey, true));

        $response = $this->call(
            'POST',
            '/api/webhooks/polar',
            [],
            [],
            [],
            [
                'HTTP_WEBHOOK_ID' => $webhookId,
                'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' => "v1,{$sig}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $account->refresh();
        $this->assertEquals('paused', $account->status);
        $this->assertEquals('refund_issued', $account->pause_reason);
        $this->assertEquals('refunded', $account->settings['subscription_status']);
        $this->assertEquals(0, $account->settings['monthly_sends_limit']);

        $campaign->refresh();
        $this->assertEquals('paused_refund_revoked', $campaign->status);
    }

    public function test_meter_usage_records_scaled_units_to_polar(): void
    {
        config(['services.polar.token' => 'test_token']);

        Http::fake([
            'https://api.polar.sh/v1/events/ingest' => function (\Illuminate\Http\Client\Request $request) {
                $events = $request['events'] ?? [];
                $first = $events[0] ?? [];
                $metadata = $first['metadata'] ?? [];

                // 25,000 raw emails must scale to 25.0 units for Polar's meter
                if (($metadata['units'] ?? null) == 25.0 && ($metadata['raw_emails'] ?? null) === 25000) {
                    return Http::response(['ingested' => 1], 200);
                }

                return Http::response(['error' => 'invalid unit scaling'], 400);
            },
        ]);

        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Scaler Test',
            'status' => 'active',
            'settings' => ['polar_customer_id' => 'cust_uuid_123'],
        ]);

        $service = new PolarBillingService();
        $recorded = $service->recordMeterUsage($account, 25000);

        $this->assertTrue($recorded);
    }

    public function test_current_workspace_get_resolves_account(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Workspace Test',
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $resolved = \App\Support\CurrentWorkspace::get();

        $this->assertNotNull($resolved);
        $this->assertEquals($account->id, $resolved->id);
    }
}
