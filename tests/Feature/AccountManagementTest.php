<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUserAndAccount(string $email = 'user@example.com'): array
    {
        $user = User::create([
            'name' => 'Jane Founder',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Jane Newsletter Group',
            'status' => 'active',
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Founders Journal',
            'slug' => 'founders-journal-' . Str::random(4),
            'from_name' => 'Jane',
            'from_email' => 'jane@example.com',
            'postal_address' => '100 Market St',
        ]);

        return [$user, $account, $newsletter];
    }

    public function test_can_view_account_page(): void
    {
        [$user] = $this->createTestUserAndAccount();

        $response = $this->withSession(['authenticated_user_id' => $user->id])
            ->get('/account');

        $response->assertStatus(200);
        $response->assertSee('Jane Founder');
        $response->assertSee($user->email);
    }

    public function test_can_update_profile_name(): void
    {
        [$user] = $this->createTestUserAndAccount();

        $response = $this->withSession(['authenticated_user_id' => $user->id])
            ->post('/account/profile', [
                'name' => 'Jane Chief Executive',
            ]);

        $response->assertRedirect();
        $this->assertEquals('Jane Chief Executive', $user->fresh()->name);
    }

    public function test_cannot_delete_account_without_matching_email(): void
    {
        [$user] = $this->createTestUserAndAccount('founder@company.com');

        $response = $this->withSession(['authenticated_user_id' => $user->id])
            ->post('/account/delete', [
                'email' => 'wrong@company.com',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_cannot_delete_account_with_in_flight_campaigns(): void
    {
        [$user, $account, $newsletter] = $this->createTestUserAndAccount('safe@company.com');

        // Create an in-flight campaign
        Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Urgent Dispatch',
            'format' => 'rich',
            'html' => '<p>In flight</p>',
            'status' => 'sending',
        ]);

        $response = $this->withSession(['authenticated_user_id' => $user->id])
            ->post('/account/delete', [
                'email' => 'safe@company.com',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertEquals('active', $account->fresh()->status);
    }

    public function test_can_schedule_account_deletion_when_safe(): void
    {
        [$user, $account] = $this->createTestUserAndAccount('clean@company.com');

        $response = $this->withSession(['authenticated_user_id' => $user->id])
            ->post('/account/delete', [
                'email' => 'clean@company.com',
            ]);

        $response->assertRedirect('/signin');
        $this->assertEquals('paused', $account->fresh()->status);
        $this->assertStringContainsString('deletion', $account->fresh()->pause_reason);
    }
}
