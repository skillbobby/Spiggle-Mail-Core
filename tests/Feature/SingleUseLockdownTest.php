<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleUseLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_edition_blocks_public_signups_when_owner_email_is_configured(): void
    {
        config([
            'spiggle.edition' => 'core',
            'spiggle.owner_email' => 'owner@mynewsletter.com',
        ]);

        // Public stranger cannot request OTP
        $strangerRes = $this->post('/signin/send-otp', [
            'email' => 'stranger@attacker.org',
        ]);
        $strangerRes->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('login_otps', ['email' => 'stranger@attacker.org']);

        // Configured OWNER_EMAIL succeeds
        $ownerRes = $this->post('/signin/send-otp', [
            'email' => 'owner@mynewsletter.com',
        ]);
        $ownerRes->assertOk();
        $this->assertDatabaseHas('login_otps', ['email' => 'owner@mynewsletter.com']);
    }

    public function test_core_edition_allows_initial_operator_claim_then_locks_out_all_subsequent_signups(): void
    {
        config([
            'spiggle.edition' => 'core',
            'spiggle.owner_email' => null,
        ]);

        $this->assertSame(0, User::count());

        // First operator claims the fresh instance
        $code = '123456';
        DB::table('login_otps')->insert([
            'id' => '01J00000000000000000000001',
            'email' => 'first-owner@domain.org',
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verifyRes = $this->post('/signin/verify-otp', [
            'email' => 'first-owner@domain.org',
            'code' => $code,
        ]);
        $verifyRes->assertRedirect('/dashboard');
        $this->assertSame(1, User::count());

        // Log out and attempt public signup from a second email -> strictly blocked!
        $this->post('/logout');

        $secondOtpRes = $this->post('/signin/send-otp', [
            'email' => 'second-person@domain.org',
        ]);
        $secondOtpRes->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('login_otps', ['email' => 'second-person@domain.org']);

        // Original claimed operator can still request OTPs anytime
        $returningRes = $this->post('/signin/send-otp', [
            'email' => 'first-owner@domain.org',
        ]);
        $returningRes->assertOk();
    }
}
