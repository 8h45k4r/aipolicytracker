<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateBundle;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** /templates/all.zip: every template in one archive, for a plan that includes it, only while selling is on. */
class TemplateBundleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TemplateBuilder::DISK);
        $this->artisan('policy:import');
    }

    private function enable(): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month', 'billing.plans.pro_yearly.product_id' => 'pdt_year']);
    }

    private function pro(): User
    {
        $user = User::factory()->create();
        Subscription::forceCreate([
            'user_id' => $user->id, 'provider' => 'dodo', 'provider_subscription_id' => 'sub_'.$user->id,
            'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active',
            'current_period_end' => now()->addMonth(), 'last_event_at' => now()->subDay(), 'last_event_type' => 'subscription.active',
        ]);

        return $user;
    }

    public function test_the_bundle_does_not_exist_while_selling_is_off(): void
    {
        $this->actingAs(User::factory()->create())->get('/templates/all.zip')->assertNotFound();
        $this->get(route('templates.index'))->assertOk()->assertDontSee('every template', false)->assertDontSee('data-template-bundle', false);
    }

    public function test_guests_sign_in_and_free_accounts_are_sent_to_pricing(): void
    {
        $this->enable();
        $this->get('/templates/all.zip')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/templates/all.zip')->assertRedirect(route('pricing'));
        $this->actingAs(User::factory()->create())->get(route('templates.index'))->assertOk()->assertSee('is part of')->assertDontSee(route('templates.bundle'));
    }

    public function test_a_paid_account_gets_every_template_in_every_format_with_a_readme(): void
    {
        $this->enable();
        $user = $this->pro();
        $this->actingAs($user)->get(route('templates.index'))->assertOk()->assertSee(route('templates.bundle'));

        $response = $this->actingAs($user)->get('/templates/all.zip')->assertOk()->assertHeader('Content-Type', 'application/zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $readme = $zip->getFromName('README.txt');
        $zip->close();

        $this->assertStringContainsString('CC BY 4.0', $readme);
        $this->assertStringContainsString('not legal advice', $readme);
        foreach (TemplateCatalog::all() as $slug => $meta) {
            $this->assertTrue($names->contains(fn ($n) => str_starts_with($n, $slug.'/')), "{$slug} is missing from the bundle");
            $this->assertStringContainsString(TemplateCatalog::url($slug), $readme);
        }
    }

    public function test_the_archive_is_reused_until_a_template_changes(): void
    {
        $bundle = app(TemplateBundle::class);
        $first = $bundle->build();
        $this->assertSame($first['path'], $bundle->build()['path']);
        $slug = TemplateCatalog::all()->keys()->first();
        app(TemplateBuilder::class)->build($slug, true);
        $second = $bundle->build()['path'];
        $this->assertNotSame($first['path'], $second);
        $this->assertSame([$second], Storage::disk(TemplateBuilder::DISK)->files(TemplateBundle::DIR), 'the superseded archive is removed');
    }
}
