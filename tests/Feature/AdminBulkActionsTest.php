<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmMail;
use App\Models\Subscriber;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The other admin tables act on a selection too, with the same rules as their single-row actions. */
class AdminBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['owner@example.test']]);
        $this->owner = User::factory()->create(['email' => 'owner@example.test', 'name' => 'Owner']);
    }

    public function test_confirmation_is_resent_only_to_the_unconfirmed_among_the_selection(): void
    {
        Mail::fake();
        $waiting = Subscriber::create(['email' => 'waiting@example.test', 'token' => 'a', 'topics' => ['all']]);
        $confirmed = Subscriber::create(['email' => 'done@example.test', 'token' => 'b', 'topics' => ['all'], 'confirmed_at' => now()]);
        $gone = Subscriber::create(['email' => 'gone@example.test', 'token' => 'c', 'topics' => ['all'], 'unsubscribed_at' => now()]);

        $this->actingAs($this->owner)
            ->post('/backend/admin/subscribers/resend-many', ['ids' => [$waiting->id, $confirmed->id, $gone->id]])
            ->assertRedirect()->assertSessionHas('success', 'Confirmation re-sent to 1 address. 2 already confirmed or unsubscribed, not sent.');

        Mail::assertSent(SubscriptionConfirmMail::class, 1);
        Mail::assertSent(SubscriptionConfirmMail::class, fn ($mail) => $mail->hasTo('waiting@example.test'));
    }

    public function test_many_subscribers_can_be_removed_at_once(): void
    {
        $ids = collect(['a', 'b', 'c'])->map(fn ($k) => Subscriber::create(['email' => $k.'@example.test', 'token' => $k, 'topics' => ['all'], 'confirmed_at' => now()])->id);
        $kept = Subscriber::create(['email' => 'kept@example.test', 'token' => 'k', 'topics' => ['all'], 'confirmed_at' => now()]);

        $this->actingAs($this->owner)->post('/backend/admin/subscribers/delete-many', ['ids' => $ids->all()])->assertRedirect()->assertSessionHas('success', '3 subscribers removed.');

        $this->assertSame(1, Subscriber::count());
        $this->assertNotNull($kept->fresh());
        $this->get('/backend/admin/subscribers')->assertOk()->assertSee('Act on the selection')->assertSee('data-confirm="Delete {n} subscribers permanently?', false);
    }

    public function test_setting_a_status_on_many_tools_keeps_the_no_empty_publish_rule(): void
    {
        $make = fn (string $slug) => Tool::create(['title' => ucfirst($slug), 'slug' => $slug, 'type' => array_key_first(Tool::TYPES), 'status' => 'draft', 'short' => 'Short.', 'version' => '1.0', 'updated_on' => now()->toDateString(), 'fields' => [], 'instructions' => [], 'frameworks' => [], 'topics' => [], 'related_guides' => [], 'related_policies' => []]);
        $withFile = $make('with-file');
        $withFile->files()->create(['file_name' => 'a.csv', 'label' => 'CSV', 'disk_path' => 'tools/with-file/a.csv', 'mime' => 'text/csv', 'size' => 1, 'checksum' => 'x', 'version' => '1.0', 'is_active' => true, 'sort_order' => 10]);
        $empty = $make('empty');

        $this->actingAs($this->owner)
            ->post('/backend/admin/tools/status-many', ['ids' => [$withFile->id, $empty->id], 'status' => 'published'])
            ->assertRedirect(route('backend.admin.tools.index'))
            ->assertSessionHas('success', '1 tool set to Published. Not published, no active file: Empty.');

        $this->assertSame('published', $withFile->fresh()->status);
        $this->assertSame('draft', $empty->fresh()->status);

        $this->post('/backend/admin/tools/status-many', ['ids' => [$withFile->id, $empty->id], 'status' => 'archived'])->assertSessionHas('success', '2 tools set to Archived.');
        $this->get('/backend/admin/tools')->assertOk()->assertSee('Set the status of the selection');
    }

    public function test_the_user_search_ignores_case(): void
    {
        User::factory()->create(['email' => 'someone@example.test', 'name' => 'Priya Natarajan']);

        $this->actingAs($this->owner)->get('/backend/admin/users?q=priya')->assertOk()->assertSee('Priya Natarajan');
        $this->get('/backend/admin/users?q=NATARAJAN')->assertOk()->assertSee('Priya Natarajan');
        $this->get('/backend/admin/users?q=nobody-here')->assertOk()->assertDontSee('Priya Natarajan');
    }

    public function test_destructive_admin_forms_carry_a_confirmation_the_page_script_can_run(): void
    {
        $other = User::factory()->create(['email' => 'other@example.test', 'name' => 'Other']);

        $html = $this->actingAs($this->owner)->get('/backend/admin/users')->assertOk()->getContent();
        $this->assertStringContainsString('data-confirm="Delete other@example.test permanently?', $html);
        // Inline handlers never ran under the page's Content-Security-Policy, so none may remain.
        $this->assertStringNotContainsString('onsubmit=', $html);
        $this->get('/backend/admin/settings')->assertOk();
    }
}
