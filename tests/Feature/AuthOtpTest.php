<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_otp_code_and_verify_login(): void
    {
        // 1. Request code
        $response = $this->post('/signin/send-otp', [
            'email' => 'dev.lead@techcorp.io',
        ]);
        $response->assertStatus(200);

        // Verify DB record
        $otp = DB::table('login_otps')->where('email', 'dev.lead@techcorp.io')->first();
        $this->assertNotNull($otp);
        $this->assertNull($otp->used_at);

        // 2. Extract code passed to Inertia view
        $props = $response->viewData('page')['props'];
        $devCode = $props['initialDevCode'] ?? null;
        $this->assertNotNull($devCode);
        $this->assertEquals(6, strlen($devCode));
        $this->assertEquals($devCode, $props['devCode']);
        $this->assertTrue($props['isLocal']);

        // 3. Verify code
        $verifyResponse = $this->post('/signin/verify-otp', [
            'email' => 'dev.lead@techcorp.io',
            'code' => $devCode,
        ]);

        $verifyResponse->assertRedirect('/dashboard');
        $this->assertEquals(session('authenticated_user_email'), 'dev.lead@techcorp.io');

        // Check marked used
        $updatedOtp = DB::table('login_otps')->where('id', $otp->id)->first();
        $this->assertNotNull($updatedOtp->used_at);
    }

    public function test_invalid_otp_code_is_rejected(): void
    {
        $this->post('/signin/send-otp', [
            'email' => 'dev.lead@techcorp.io',
        ]);

        $verifyResponse = $this->post('/signin/verify-otp', [
            'email' => 'dev.lead@techcorp.io',
            'code' => '000000',
        ]);

        $verifyResponse->assertSessionHasErrors(['code']);
    }
}
