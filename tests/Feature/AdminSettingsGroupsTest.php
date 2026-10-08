<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Funder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings, Billing and Funding and funders after the hands-on audit: one form per settings
 * group that saves only that group, typed values and inline errors kept after a refused
 * save, where each value comes from, live switches that ask first, the reason the billing
 * probe is off, and funder forms in side panels with a page fallback.
 */
class AdminSettingsGroupsTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => [self::OWNER], 'funding.funders' => []]);
    }

    private function owner(): User
    {
        return User::firstWhere('email', self::OWNER) ?? User::factory()->create(['email' => self::OWNER]);
    }

    public function test_every_group_has_its_own_form_save_button_and_palette_action(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.settings'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="text-2xl font-semibold tracking-tight text-brand-navy">Settings and API keys</h1>', $html);
        foreach (AppSetting::GROUPS as $id => $group) {
            $this->assertStringContainsString('id="group-'.$id.'"', $html);
            $this->assertStringContainsString('href="#group-'.$id.'" data-command="Settings: '.e($group['label']).'"', $html);
        }
        foreach (array_keys(AppSetting::GROUPS) as $id) {
            if ($id !== 'live') {
                $this->assertStringContainsString('data-settings-form="'.$id.'"', $html);
                $this->assertStringContainsString('<input type="hidden" name="group" value="'.$id.'">', $html);
            }
        }
        // Every managed key is in exactly one group and on the page.
        foreach (AppSetting::KEYS as $key => $meta) {
            $this->assertArrayHasKey($meta['group'], AppSetting::GROUPS, $key);
            $this->assertStringContainsString('name="'.$key.'"', $html);
        }
        // The tests sit inside their groups.
        $this->assertMatchesRegularExpression('/id="group-email".*Send test email.*id="group-digest"/s', $html);
        $this->assertMatchesRegularExpression('/id="group-billing".*Can we sell right now\?.*id="group-citation"/s', $html);
    }

    public function test_a_group_save_changes_only_that_group(): void
    {
        $owner = $this->owner();
        AppSetting::put('contact_email', 'kept@example.org');
        AppSetting::put('mail_from_name', 'Old Name');
        AppSetting::put('resend_key', 're_STORED_1234567890');
        AppSetting::put('mail_from_address', 'old@example.org');

        $this->actingAs($owner)->post(route('backend.admin.settings.save'), [
            'group' => 'email',
            'mail_from_name' => 'New Name',
            'mail_from_address' => '',          // emptied: falls back to the environment
            'resend_key' => '',                 // a secret left empty keeps the stored one
            'contact_email' => 'evil@example.org', // another group's key: ignored
            'billing_enabled' => 'on',
        ])->assertRedirect(route('backend.admin.settings').'#group-email')->assertSessionHasNoErrors();

        $this->assertSame('New Name', AppSetting::get('mail_from_name'));
        $this->assertNull(AppSetting::get('mail_from_address'));
        $this->assertSame('re_STORED_1234567890', AppSetting::get('resend_key'));
        $this->assertSame('kept@example.org', AppSetting::get('contact_email'));
        $this->assertNull(AppSetting::get('billing_enabled'));

        // Another group's invalid value does not block or fail this group's save.
        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['group' => 'site', 'contact_email' => 'hello@example.org', 'mail_from_address' => 'not an address'])
            ->assertSessionHasNoErrors();
        $this->assertSame('hello@example.org', AppSetting::get('contact_email'));

        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['group' => 'nonsense', 'contact_email' => 'x@example.org'])->assertStatus(422);
    }

    public function test_a_refused_save_keeps_typed_values_shows_inline_errors_and_saves_nothing(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('backend.admin.settings.save'), [
            'group' => 'email',
            'mail_from_name' => 'Typed Name',
            'mail_from_address' => 'not-an-address',
            'resend_key' => 're_TYPED_SECRET_123',
        ])->assertRedirect(route('backend.admin.settings').'#group-email')->assertSessionHasErrors('mail_from_address');
        $this->assertNull(AppSetting::get('mail_from_name'), 'nothing in the group is saved');
        $this->assertNull(session()->getOldInput('resend_key'), 'a secret is not kept in the session');

        $html = $this->get(route('backend.admin.settings'))->assertOk()->getContent();
        $this->assertStringContainsString('value="Typed Name"', $html);
        $this->assertStringContainsString('value="not-an-address"', $html);
        $this->assertStringContainsString('id="f-mail_from_address-error"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('Nothing in this group was saved.', $html);
        $this->assertStringNotContainsString('re_TYPED_SECRET_123', $html);

        // The citation group's https rule says what to type.
        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['group' => 'citation', 'sponsor_url' => 'http://example.org'])
            ->assertSessionHasErrors(['sponsor_url' => 'Use a full https:// address, for example https://example.org/page.']);
    }

    public function test_each_field_says_whether_its_value_is_saved_here_from_the_environment_or_the_default(): void
    {
        AppSetting::put('contact_email', 'saved@example.org');
        $html = $this->actingAs($this->owner())->get(route('backend.admin.settings'))->assertOk()->getContent();

        $badge = function (string $key) use ($html): string {
            $this->assertSame(1, preg_match('/data-setting="'.$key.'".*?data-source="([a-z]+)"/s', $html, $m), $key);

            return $m[1];
        };
        $this->assertSame('saved', $badge('contact_email'));
        $this->assertSame('environment', $badge('mail_mailer')); // MAIL_MAILER=array in phpunit.xml
        $this->assertSame('default', $badge('bing_site_verification'));
        $this->assertStringContainsString('Saved here', $html);
        $this->assertStringContainsString('From environment', $html);
        $this->assertStringContainsString('>Default<', $html);
    }

    public function test_live_switches_sit_apart_and_ask_before_going_live(): void
    {
        config(['billing.enabled' => false]);
        $owner = $this->owner();
        $html = $this->actingAs($owner)->get(route('backend.admin.settings'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="group-live".*data-live-switch="billing_enabled"/s', $html);
        $this->assertDoesNotMatchRegularExpression('/data-settings-form="billing".*name="billing_enabled".*id="group-citation"/s', $html, 'Checkout is not in the ordinary billing form');
        $this->assertMatchesRegularExpression('/name="billing_enabled" value="on" class="btn-danger" data-confirm="Turn on: Checkout\? The pricing page starts taking orders[^"]*" data-confirm-danger data-confirm-label="Yes, turn on"/', $html);
        $this->assertMatchesRegularExpression('/name="dodo_environment" value="live_mode" class="btn-danger" data-confirm="Switch to live_mode: Dodo environment\? Checkout charges real cards[^"]*" data-confirm-danger/', $html);
        $this->assertMatchesRegularExpression('/name="email_domain_enforcement" value="on" class="btn-danger" data-confirm="[^"]*refuse every address[^"]*" data-confirm-danger/', $html);

        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['group' => 'live', 'billing_enabled' => 'on'])->assertRedirect(route('backend.admin.settings').'#group-live');
        $this->assertSame('on', AppSetting::get('billing_enabled'));
        $this->assertNull(AppSetting::get('dodo_environment'), 'only the pressed switch changes');

        // Now on: the switch offers "Turn off" (no confirmation) and a way back to the environment.
        $html = $this->actingAs($owner)->get(route('backend.admin.settings'))->getContent();
        $this->assertStringContainsString('name="billing_enabled" value="off" class="btn-secondary">Turn off', $html);
        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['group' => 'live', 'clear' => ['billing_enabled']]);
        $this->assertNull(AppSetting::get('billing_enabled'));
    }

    public function test_the_billing_probe_says_why_it_is_off_and_links_to_the_fix(): void
    {
        config(['billing.api_key' => null, 'billing.webhook_secret' => null, 'billing.plans.pro_monthly.product_id' => null, 'billing.plans.pro_yearly.product_id' => null]);
        $owner = $this->owner();
        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="text-2xl font-semibold tracking-tight text-brand-navy">Billing</h1>', $html);
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn-secondary"\s+disabled\s+aria-describedby="probe-blocked"\s*>Can we sell right now\?/', $html);
        $this->assertStringContainsString('No Dodo API key is stored or set in the environment.', $html);
        $this->assertStringContainsString('href="'.route('backend.admin.settings').'#f-dodo_api_key"', $html);
        $this->assertStringContainsString('The products are not provisioned', $html);

        // With a key but no products, the fix is provisioning on this page.
        AppSetting::put('dodo_api_key', 'dodo_test_abcdefghijklmnop');
        AppSetting::put('dodo_webhook_secret', 'whsec_abcdefghijklmnopqrst');
        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index'))->getContent();
        $this->assertStringNotContainsString('No Dodo API key', $html);
        $this->assertStringContainsString('Use &quot;Provision webhook and products&quot;', $html);

        AppSetting::put('dodo_product_pro_monthly', 'pdt_month');
        $html = $this->actingAs($owner)->get(route('backend.admin.billing.index'))->getContent();
        $this->assertStringNotContainsString('data-probe-blocked', $html);
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn-secondary"\s*>Can we sell right now\?/', $html);
    }

    public function test_funders_are_added_and_edited_in_side_panels_with_a_page_fallback(): void
    {
        $owner = $this->owner();
        $funder = Funder::create(['name' => 'Example & Trust', 'kind' => 'grant', 'amount_display' => '€5,000', 'purpose' => 'Hosting', 'published' => true, 'sort' => 1]);

        $html = $this->actingAs($owner)->get(route('backend.admin.funding.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="text-2xl font-semibold tracking-tight text-brand-navy">Funding and funders</h1>', $html);
        $this->assertStringContainsString('data-drawer-open="funder-add" data-command="Add a funder"', $html);
        $this->assertStringContainsString('<dialog id="funder-add" class="adm-drawer"', $html);
        $this->assertStringContainsString('data-drawer-open="funder-edit-'.$funder->id.'"', $html);
        $this->assertStringContainsString('<dialog id="funder-edit-'.$funder->id.'" class="adm-drawer"', $html);
        $this->assertStringContainsString('data-confirm="Delete Example &amp; Trust? It disappears from /funding at once', $html);
        $this->assertStringContainsString('data-confirm-danger data-confirm-label="Delete Example &amp; Trust"', $html);
        $this->assertStringNotContainsString('id="funder-form"', $html, 'no form on the page until asked for');
        $this->assertStringNotContainsString('data-open-on-load', $html);

        // Without JavaScript the links lead to the form on the page.
        $page = $this->actingAs($owner)->get(route('backend.admin.funding.index', ['new' => 1]))->getContent();
        $this->assertStringContainsString('id="funder-form"', $page);
        $this->assertStringNotContainsString('<dialog id="funder-add"', $page, 'the same form is not on the page twice');
        $this->actingAs($owner)->get(route('backend.admin.funding.index', ['edit' => $funder->id]))->assertSee('Edit Example &amp; Trust', false)->assertSee('id="funder-form"', false);

        // A refused link says what to type, and the panel that was sent reopens with what was typed.
        $this->actingAs($owner)->post(route('backend.admin.funding.store'), ['_drawer' => 'funder-add', 'name' => 'Typed Funder', 'kind' => 'grant', 'amount_display' => '$1', 'purpose' => 'Typed purpose', 'url' => 'http://example.org'])
            ->assertSessionHasErrors(['url' => 'Use a full https:// address, for example https://example.org/grants.']);
        $html = $this->actingAs($owner)->get(route('backend.admin.funding.index'))->getContent();
        $this->assertMatchesRegularExpression('/<dialog id="funder-add" class="adm-drawer"[^>]*data-open-on-load/', $html);
        $this->assertStringContainsString('value="Typed Funder"', $html);
        $this->assertStringContainsString('Use a full https:// address', $html);
        $this->assertDoesNotMatchRegularExpression('/<dialog id="funder-edit-'.$funder->id.'"[^>]*data-open-on-load/', $html);
        $this->assertSame(1, Funder::count());
    }
}
