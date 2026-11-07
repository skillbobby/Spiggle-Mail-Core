<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GetStartedChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Newsletter $newsletter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'admin@spiggle-mail.local']);
        $this->account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Demo Workspace',
            'status' => 'active',
        ]);

        $this->newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Weekly Pulse',
            'slug' => 'weekly-pulse',
            'from_name' => 'Editor',
            'from_email' => 'editor@spiggle-mail.local',
            'postal_address' => '123 Main St, Springfield, IL',
        ]);
    }

    public function test_get_started_checklist_shared_with_empty_state(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Overview', false)
            ->has('getStarted', fn (Assert $getStarted) => $getStarted
                ->where('completedCount', 0)
                ->where('totalCount', 5)
                ->where('isFinished', false)
                ->has('steps', 5)
                ->where('steps.0.id', 'write')
                ->where('steps.0.isDone', false)
                ->where('steps.1.id', 'subscribers')
                ->where('steps.1.isDone', false)
                ->where('steps.2.id', 'domain')
                ->where('steps.2.isDone', false)
                ->where('steps.3.id', 'send')
                ->where('steps.3.isDone', false)
                ->where('steps.4.id', 'agent')
                ->where('steps.4.isDone', false)
            )
        );
    }

    public function test_get_started_checklist_progressively_completes_steps(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        // 1. Create a draft campaign
        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Hello World',
            'html' => '<p>Hello</p>',
            'status' => 'draft',
        ]);

        // 2. Add a subscriber
        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'subscriber@spiggle-mail.local',
            'status' => 'confirmed',
        ]);

        // 3. Add a verified domain
        Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'domain' => 'example.com',
            'status' => 'verified',
        ]);

        // 4. Update campaign to sent
        $campaign->update(['status' => 'sent']);

        // 5. Add an API key
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Agent Key',
            'key_hash' => hash('sha256', 'spiggle_test_key_12345'),
            'key_prefix' => 'spiggle_test',
        ]);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Overview', false)
            ->has('getStarted', fn (Assert $getStarted) => $getStarted
                ->where('completedCount', 5)
                ->where('totalCount', 5)
                ->where('isFinished', true)
                ->where('steps.0.id', 'write')
                ->where('steps.0.isDone', true)
                ->where('steps.1.id', 'subscribers')
                ->where('steps.1.isDone', true)
                ->where('steps.2.id', 'domain')
                ->where('steps.2.isDone', true)
                ->where('steps.3.id', 'send')
                ->where('steps.3.isDone', true)
                ->where('steps.4.id', 'agent')
                ->where('steps.4.isDone', true)
            )
        );
    }
}
