<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Funder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner keeps the funders, the sponsor link and the disclosure threshold in the
 * admin. Editors cannot reach any of it, every write is audited, and /funding shows only
 * published rows (config/funding.php while the table is empty).
 */
class FundingAdminTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => [self::OWNER], 'funding.funders' => [], 'funding.sponsor_url' => null]);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => self::OWNER]);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => AdminRole::Editor])->save();

        return $user->refresh();
    }

    /** @return array<string, mixed> */
    private function funder(array $overrides = []): array
    {
        return $overrides + ['name' => 'Example Foundation', 'kind' => 'grant', 'amount_display' => '€30,000', 'period' => '2027', 'purpose' => 'Second reviewer and verification', 'url' => 'https://example.org/grants', 'published' => '1'];
    }

    public function test_only_the_owner_reaches_the_funding_admin(): void
    {
        $editor = $this->editor();
        $existing = Funder::create($this->funder(['published' => true]));

        $this->get('/backend/admin/funding')->assertRedirect('/login');
        $this->actingAs($editor)->get('/backend/admin/funding')->assertForbidden();
        $this->actingAs($editor)->post('/backend/admin/funding', $this->funder())->assertForbidden();
        $this->actingAs($editor)->put('/backend/admin/funding/'.$existing->id, $this->funder(['name' => 'Renamed']))->assertForbidden();
        $this->actingAs($editor)->post('/backend/admin/funding/'.$existing->id.'/publish', ['state' => 'off'])->assertForbidden();
        $this->actingAs($editor)->delete('/backend/admin/funding/'.$existing->id)->assertForbidden();
        $this->assertSame('Example Foundation', $existing->fresh()->name);
        $this->assertTrue($existing->fresh()->published);

        $this->actingAs($this->owner())->get('/backend/admin/funding')->assertOk()
            ->assertSee('Disclosure threshold: $1,000 a year.')
            ->assertSee('must be listed')
            ->assertSee('Example Foundation');
    }

    public function test_the_owner_adds_edits_publishes_reorders_and_deletes_funders_with_an_audit_row_each(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder())->assertRedirect(route('backend.admin.funding.index'));
        $first = Funder::firstOrFail();
        $this->assertTrue($first->published);
        $this->assertSame('€30,000', $first->amount_display);
        $this->assertSame(1, $first->sort);

        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['name' => 'Second Trust', 'kind' => 'sponsor', 'published' => '0', 'url' => '']))->assertRedirect();
        $second = Funder::where('name', 'Second Trust')->firstOrFail();
        $this->assertFalse($second->published);
        $this->assertNull($second->url);
        $this->assertSame(2, $second->sort);

        $this->actingAs($owner)->get('/backend/admin/funding?edit='.$second->id)->assertOk()->assertSee('Edit Second Trust');
        $this->actingAs($owner)->put('/backend/admin/funding/'.$second->id, $this->funder(['name' => 'Second Trust', 'kind' => 'sponsor', 'amount_display' => '$5,000', 'published' => '0']))->assertRedirect();
        $this->assertSame('$5,000', $second->fresh()->amount_display);

        $this->actingAs($owner)->post('/backend/admin/funding/'.$second->id.'/move', ['action' => 'up'])->assertRedirect();
        $this->assertSame(['Second Trust', 'Example Foundation'], Funder::ordered()->pluck('name')->all());

        $this->actingAs($owner)->post('/backend/admin/funding/'.$second->id.'/publish', ['state' => 'on'])->assertRedirect();
        $this->assertTrue($second->fresh()->published);

        $this->actingAs($owner)->delete('/backend/admin/funding/'.$first->id)->assertRedirect();
        $this->assertNull($first->fresh());

        $routes = AdminAuditLog::where('user_id', $owner->id)->orderBy('id')->pluck('route_name')->all();
        $this->assertSame([
            'backend.admin.funding.store', 'backend.admin.funding.store', 'backend.admin.funding.update',
            'backend.admin.funding.move', 'backend.admin.funding.publish', 'backend.admin.funding.destroy',
        ], $routes);
        $delete = AdminAuditLog::where('route_name', 'backend.admin.funding.destroy')->firstOrFail();
        $this->assertSame(['funder' => $first->id], $delete->route_params);
    }

    public function test_funder_input_is_validated(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['url' => 'http://example.org']))->assertSessionHasErrors('url');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['kind' => 'donor']))->assertSessionHasErrors('kind');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['name' => str_repeat('a', 161)]))->assertSessionHasErrors('name');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['amount_display' => '']))->assertSessionHasErrors('amount_display');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['purpose' => str_repeat('a', 2001)]))->assertSessionHasErrors('purpose');
        $this->actingAs($owner)->post('/backend/admin/funding', $this->funder(['starts_on' => '2027-06-01', 'ends_on' => '2027-01-01']))->assertSessionHasErrors('ends_on');
        $this->assertSame(0, Funder::count());

        // A refused form is audited as refused.
        $this->assertSame(422, AdminAuditLog::where('route_name', 'backend.admin.funding.store')->latest('id')->value('status'));
    }

    public function test_settings_set_the_sponsor_link_and_threshold_without_a_deploy(): void
    {
        $owner = $this->owner();
        config(['funding.sponsor_url' => 'https://github.com/sponsors/from-env']);

        $this->actingAs($owner)->post('/backend/admin/settings', ['sponsor_url' => 'http://insecure.example.org'])->assertSessionHasErrors('sponsor_url');
        $this->actingAs($owner)->post('/backend/admin/settings', ['funding_threshold' => '-5'])->assertSessionHasErrors('funding_threshold');
        $this->actingAs($owner)->post('/backend/admin/settings', ['funding_threshold' => 'lots'])->assertSessionHasErrors('funding_threshold');

        $this->actingAs($owner)->post('/backend/admin/settings', ['sponsor_url' => 'https://opencollective.com/example', 'funding_threshold' => '2500'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('https://opencollective.com/example', AppSetting::get('sponsor_url'));

        $this->get('/funding')->assertOk()
            ->assertSee('https://opencollective.com/example')->assertDontSee('https://github.com/sponsors/from-env')
            ->assertSee('Funders above $2,500 a year');
        $this->actingAs($owner)->get('/backend/admin/funding')->assertSee('Disclosure threshold: $2,500 a year.');
        $this->actingAs($owner)->get('/backend/admin/settings')->assertOk()->assertSee('Citation and funding')->assertSee('docs/reference/releases.md');

        // Cleared: the environment value is back.
        $this->actingAs($owner)->post('/backend/admin/settings', ['clear' => ['sponsor_url', 'funding_threshold']])->assertRedirect();
        $this->get('/funding')->assertOk()->assertSee('https://github.com/sponsors/from-env')->assertSee('Funders above $1,000 a year');
    }

    public function test_an_editor_cannot_change_the_citation_and_funding_settings(): void
    {
        $this->actingAs($this->editor())->post('/backend/admin/settings', ['sponsor_url' => 'https://evil.example.org', 'dataset_doi' => '10.5281/zenodo.1'])->assertForbidden();
        $this->assertNull(AppSetting::get('sponsor_url'));
        $this->assertNull(AppSetting::get('dataset_doi'));
    }
}
