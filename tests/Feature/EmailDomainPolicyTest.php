<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use App\Models\User;
use App\Services\Security\EmailDomainPolicy;
use App\Services\Security\MailDomainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A throwaway address breaks the product's only promise: that a reader hears
 * when a deadline moves. It also inflates the subscriber and download counts
 * the site publishes about itself, which is the same overclaim the rest of the
 * project is built to avoid.
 *
 * The rule has to cut exactly one way. Work addresses and personal addresses
 * are both welcome; a mailbox that expires is not. Half of what follows asserts
 * the refusals, and half asserts that the refusals stop where they should.
 */
class EmailDomainPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Answers from a table instead of the network, so the decision under test is
     * the policy's and not the day's DNS.
     *
     * @param  array<string,list<string>|null>  $map
     */
    private function resolver(array $map, ?array $default = ['mx.example-host.net']): void
    {
        $this->app->instance(MailDomainResolver::class, new class($map, $default) implements MailDomainResolver
        {
            public function __construct(private array $map, private ?array $default) {}

            public function mailHosts(string $domain): ?array
            {
                return array_key_exists($domain, $this->map) ? $this->map[$domain] : $this->default;
            }
        });
        $this->app->forgetInstance(EmailDomainPolicy::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Off by default under `testing` so every other suite stays about its own
        // subject; this one is about the policy, so it turns it on.
        config(['email.enforce' => true]);
        $this->resolver([]);
        Mail::fake();
    }

    private function policy(): EmailDomainPolicy
    {
        return $this->app->make(EmailDomainPolicy::class);
    }

    public function test_a_work_address_is_accepted(): void
    {
        $verdict = $this->policy()->inspect('r.patel@stmarys-trust.nhs.uk');

        $this->assertTrue($verdict->allowed);
        $this->assertSame('stmarys-trust.nhs.uk', $verdict->domain);
        $this->assertNull($verdict->reason);
    }

    public function test_a_personal_address_is_accepted_and_nothing_can_overrule_that(): void
    {
        // Freemail is how most readers arrive, and the trusted list is checked
        // before any heuristic. Even a resolver claiming a throwaway exchanger
        // must not take a fifteen-year-old mailbox away from somebody.
        $this->resolver(['gmail.com' => ['mail.mailinator.com']]);

        foreach (['ada@gmail.com', 'ada@outlook.com', 'ada@proton.me', 'ada@mail.yahoo.co.uk'] as $address) {
            $this->assertTrue($this->policy()->inspect($address)->allowed, $address);
        }
    }

    public function test_a_known_throwaway_domain_is_refused_including_a_subdomain_of_it(): void
    {
        foreach (['someone@mailinator.com', 'someone@team.mailinator.com', 'someone@yopmail.com'] as $address) {
            $verdict = $this->policy()->inspect($address);
            $this->assertFalse($verdict->allowed, $address);
            $this->assertSame('disposable_domain', $verdict->reason);
        }
    }

    public function test_a_lookalike_domain_is_not_caught_by_the_suffix_match(): void
    {
        // The match is on label boundaries. "notmailinator.com" is somebody's
        // real domain until proven otherwise.
        $this->assertTrue($this->policy()->inspect('someone@notmailinator.com')->allowed);
        $this->assertTrue($this->policy()->inspect('someone@mailinator.com.mycompany.co')->allowed);
    }

    public function test_a_domain_nobody_has_catalogued_is_refused_by_where_its_mail_goes(): void
    {
        // The case the blocklist cannot cover and the reason the mail-host check
        // exists: a throwaway service rotates domains but keeps its exchangers.
        $this->resolver(['qx7t2-inbox.click' => ['mail2.mailinator.com', 'mail.mailinator.com']]);

        $verdict = $this->policy()->inspect('someone@qx7t2-inbox.click');

        $this->assertFalse($verdict->allowed);
        $this->assertSame('disposable_mail_host', $verdict->reason);
        $this->assertStringContainsString('mailinator.com', (string) $verdict->evidence);
    }

    public function test_a_domain_that_cannot_receive_mail_at_all_is_refused(): void
    {
        $this->resolver(['no-mail-here.example-tld' => []]);

        $verdict = $this->policy()->inspect('someone@no-mail-here.example-tld');

        $this->assertFalse($verdict->allowed);
        $this->assertSame('undeliverable_domain', $verdict->reason);
    }

    public function test_an_unanswered_lookup_admits_the_address_rather_than_refusing_the_world(): void
    {
        // A resolver outage must never become a sign-up outage. Null is "not
        // known", and nothing unknown is ever refused.
        $this->resolver(['unknown-to-dns.co' => null]);

        $this->assertTrue($this->policy()->inspect('someone@unknown-to-dns.co')->allowed);
    }

    public function test_reserved_documentation_domains_are_refused(): void
    {
        foreach (['a@example.com', 'a@host.test', 'a@thing.invalid', 'a@box.localhost'] as $address) {
            $verdict = $this->policy()->inspect($address);
            $this->assertFalse($verdict->allowed, $address);
            $this->assertSame('reserved_domain', $verdict->reason);
        }
    }

    public function test_the_master_switch_turns_the_whole_policy_off(): void
    {
        config(['email.enforce' => false]);

        $this->assertTrue($this->policy()->inspect('someone@mailinator.com')->allowed);
    }

    public function test_the_curated_lists_are_well_formed_and_do_not_contradict_each_other(): void
    {
        $read = function (string $path): array {
            $out = [];
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line !== '' && ! str_starts_with($line, '#')) {
                    $out[] = $line;
                }
            }

            return $out;
        };

        $trusted = $read(config('email.lists.trusted'));
        $disposable = $read(config('email.lists.disposable'));
        $hosts = $read(config('email.lists.mail_hosts'));

        foreach (['trusted' => $trusted, 'disposable' => $disposable, 'mail hosts' => $hosts] as $label => $entries) {
            $this->assertNotEmpty($entries, "the {$label} list is empty");
            $this->assertSame(array_unique($entries), $entries, "the {$label} list repeats itself");
            foreach ($entries as $entry) {
                $this->assertMatchesRegularExpression('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $entry, "{$entry} in the {$label} list is not a domain");
            }
        }

        $this->assertSame([], array_intersect($trusted, $disposable), 'a domain is on both the trusted and the throwaway list');
        $this->assertSame([], array_intersect($trusted, $hosts), 'a trusted provider is listed as a throwaway exchanger');

        // Shared infrastructure that real organisations use must never appear as
        // a throwaway exchanger; a line here would refuse every business on it.
        foreach (['cloudflare.net', 'google.com', 'googlemail.com', 'outlook.com', 'amazonaws.com', 'mailgun.org', 'improvmx.com', 'forwardemail.net', 'zoho.com', 'pphosted.com', 'mimecast.com', 'messagelabs.com'] as $shared) {
            $this->assertNotContains($shared, $hosts, "{$shared} is shared infrastructure and cannot be treated as a throwaway exchanger");
        }
    }

    public function test_registration_refuses_a_throwaway_address_and_creates_nothing(): void
    {
        $payload = [
            'name' => 'Test User', 'password' => 'Password!123',
            'password_confirmation' => 'Password!123', 'terms_condition' => true,
        ];

        $this->post('/register', $payload + ['email' => 'burner@mailinator.com'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_accepts_a_work_address(): void
    {
        $this->post('/register', [
            'name' => 'Test User', 'email' => 'r.patel@stmarys-trust.nhs.uk', 'password' => 'Password!123',
            'password_confirmation' => 'Password!123', 'terms_condition' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(1, User::where('email', 'r.patel@stmarys-trust.nhs.uk')->count());
    }

    public function test_the_refusal_says_what_to_do_instead(): void
    {
        $this->post('/register', [
            'name' => 'Test User', 'email' => 'burner@yopmail.com', 'password' => 'Password!123',
            'password_confirmation' => 'Password!123', 'terms_condition' => true,
        ]);

        $message = session('errors')->first('email');
        $this->assertStringContainsString('work address', $message);
        $this->assertStringContainsString('personal address', $message, 'a reader must not read this as "company mail only"');
    }

    public function test_the_newsletter_refuses_a_throwaway_address_and_stores_no_subscriber(): void
    {
        $this->post(route('subscribe.store'), ['email' => 'burner@guerrillamail.com'])->assertSessionHasErrors('email');
        $this->assertSame(0, Subscriber::count());

        $this->post(route('subscribe.store'), ['email' => 'r.patel@stmarys-trust.nhs.uk'])->assertSessionHasNoErrors();
        $this->assertSame(1, Subscriber::count());
    }

    public function test_a_contribution_may_be_anonymous_but_not_contactable_at_a_throwaway_address(): void
    {
        $submission = ['type' => 'correction', 'summary' => 'The applies-from date looks wrong on this record.'];

        $this->post(route('contribute.store'), $submission + ['submitter_email' => 'burner@mailinator.com'])->assertSessionHasErrors('submitter_email');
        $this->assertSame(0, \App\Models\ContributorSubmission::count());

        // Leaving it blank is still fine: a correction is worth more than a lead.
        $this->post(route('contribute.store'), $submission)->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\ContributorSubmission::count());
    }

    public function test_changing_an_address_is_policed_but_an_account_already_on_a_refused_domain_is_not_locked_out(): void
    {
        $user = User::factory()->create(['email' => 'old@mailinator.com']);

        // Moving to another throwaway address is refused.
        $this->actingAs($user)->patch('/profile', ['name' => 'Ada', 'email' => 'new@yopmail.com'])->assertSessionHasErrors('email');
        $this->assertSame('old@mailinator.com', $user->fresh()->email);

        // Saving the form without touching the address still works, so a rule
        // added today cannot strand somebody who signed up before it.
        $this->actingAs($user)->patch('/profile', ['name' => 'Ada Example', 'email' => 'old@mailinator.com'])->assertSessionHasNoErrors();
        $this->assertSame('Ada Example', $user->fresh()->name);

        // And they can move to a real address.
        $this->actingAs($user)->patch('/profile', ['name' => 'Ada Example', 'email' => 'ada@stmarys-trust.nhs.uk'])->assertSessionHasNoErrors();
        $this->assertSame('ada@stmarys-trust.nhs.uk', $user->fresh()->email);
    }

    public function test_an_existing_account_can_still_ask_for_a_password_reset(): void
    {
        // Sign-in and reset are deliberately outside the policy. Refusing them
        // would lock out an account that was created before the rule existed.
        User::factory()->create(['email' => 'old@mailinator.com', 'password' => bcrypt('Password!123')]);

        $this->post('/forgot-password', ['email' => 'old@mailinator.com'])->assertSessionHasNoErrors();
        $this->post('/login', ['email' => 'old@mailinator.com', 'password' => 'Password!123'])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_the_operator_command_names_the_line_that_decided(): void
    {
        // Non-zero on a refusal, so the command is usable from a script.
        $this->artisan('email:check', ['address' => ['someone@mailinator.com']])
            ->expectsOutputToContain('disposable_domain')
            ->assertExitCode(1);

        $this->artisan('email:check', ['address' => ['r.patel@stmarys-trust.nhs.uk']])
            ->expectsOutputToContain('accepted')
            ->assertExitCode(0);
    }

    public function test_the_operator_overlay_adds_domains_without_a_deploy(): void
    {
        $path = storage_path('app/email/test-overlay.txt');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, "# added by the operator\nnew-burner-service.io\n");
        config(['email.overlay' => $path]);

        try {
            $verdict = $this->policy()->inspect('someone@new-burner-service.io');
            $this->assertFalse($verdict->allowed);
            $this->assertSame('disposable_domain', $verdict->reason);
        } finally {
            @unlink($path);
        }
    }
}
