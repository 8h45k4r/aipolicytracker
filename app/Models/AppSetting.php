<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Operator-managed settings. Values are encrypted at rest with APP_KEY;
 * secrets are never returned to the browser, only a masked hint.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'secret', 'updated_by'];

    /**
     * The groups of the settings page, in page order. Each group is its own card with its
     * own form and Save button, and a save writes only the keys of the group it names.
     * "live" holds the switches that change what visitors or customers meet at once.
     */
    public const GROUPS = [
        'email' => ['label' => 'Email', 'intro' => 'How the site sends mail: confirmations, digests, alerts and invitations.'],
        'digest' => ['label' => 'Scheduled jobs', 'intro' => 'The shared secret the scheduled workflows send when they call the site.'],
        'bot' => ['label' => 'Bot protection', 'intro' => 'Cloudflare Turnstile adds an invisible human check to the template download, contribute, subscribe and sign-up forms.'],
        'billing' => ['label' => 'Billing', 'intro' => 'Keys and product ids from Dodo Payments.'],
        'citation' => ['label' => 'Citation and funding', 'intro' => 'The dataset DOI, the sponsor link and the funding disclosure threshold.'],
        'site' => ['label' => 'Site', 'intro' => 'Contact details and site behaviour. Each value overrides the environment variable of the same meaning.'],
        'analytics' => ['label' => 'Analytics and search engines', 'intro' => 'Analytics tags and search engine ownership tokens. An empty field keeps the environment value.'],
        'live' => ['label' => 'Live switches', 'intro' => 'Each of these changes what visitors or customers meet on the next request. Turning one towards its live setting asks first.'],
    ];

    /**
     * Keys the admin settings page may manage. "group" places the key on the page (GROUPS);
     * "options" makes it a choice; "live" names, per value, what choosing it does at once,
     * and the page asks for confirmation before that value is saved.
     */
    public const KEYS = [
        'mail_mailer' => ['label' => 'Mail transport', 'secret' => false, 'group' => 'email', 'hint' => 'log keeps mail in the log; resend and smtp deliver it.', 'options' => ['log' => 'log (no delivery)', 'resend' => 'resend', 'smtp' => 'smtp']],
        'resend_key' => ['label' => 'Resend API key', 'secret' => true, 'group' => 'email', 'hint' => 'From resend.com/api-keys ("Sending access"). Starts with re_'],
        'mail_from_address' => ['label' => 'From address', 'secret' => false, 'group' => 'email', 'hint' => 'Must belong to a domain verified in Resend'],
        'mail_from_name' => ['label' => 'From name', 'secret' => false, 'group' => 'email', 'hint' => 'e.g. AI Policy Tracker'],
        'cron_token' => ['label' => 'Cron token', 'secret' => true, 'group' => 'digest', 'hint' => 'Shared secret for the digest and alert triggers. The same value goes into the repository secret CRON_TOKEN. At least 24 characters.'],
        'billing_enabled' => ['label' => 'Checkout', 'secret' => false, 'group' => 'live', 'hint' => 'On opens the pricing page for purchase in the selected Dodo environment; off shows the plans as not yet available.', 'options' => ['on' => 'On (sell plans)', 'off' => 'Off'], 'live' => ['on' => 'The pricing page starts taking orders, and the free plan limits apply to every account without a subscription.']],
        'dodo_environment' => ['label' => 'Dodo environment', 'secret' => false, 'group' => 'live', 'hint' => 'test_mode is the sandbox; live_mode charges real cards.', 'options' => ['test_mode' => 'test_mode (sandbox)', 'live_mode' => 'live_mode'], 'live' => ['live_mode' => 'Checkout charges real cards, and the key, webhook secret and product ids must be the live ones.']],
        'dodo_api_key' => ['label' => 'Dodo API key', 'secret' => true, 'group' => 'billing', 'hint' => 'From Dodo Payments → Developer → API keys (test keys start with dodo_test_)'],
        'dodo_webhook_secret' => ['label' => 'Dodo webhook secret', 'secret' => true, 'group' => 'billing', 'hint' => 'Signing secret of the webhook endpoint (starts with whsec_)'],
        'dodo_product_pro_monthly' => ['label' => 'Product id: Pro monthly', 'secret' => false, 'group' => 'billing', 'hint' => 'pdt_… id of the monthly subscription product'],
        'dodo_product_pro_yearly' => ['label' => 'Product id: Pro annual', 'secret' => false, 'group' => 'billing', 'hint' => 'pdt_… id of the annual subscription product'],
        // Site and features. Each overrides the matching environment value; empty falls back.
        'contact_email' => ['label' => 'Official contact address', 'secret' => false, 'group' => 'site', 'hint' => 'Shown on About, in the footer, structured data, security.txt and the API description'],
        'x_handle' => ['label' => 'X handle', 'secret' => false, 'group' => 'site', 'hint' => 'For the twitter:site card attribution, e.g. @aipolicytracker'],
        'newsletter_url' => ['label' => 'External newsletter URL', 'secret' => false, 'group' => 'site', 'hint' => 'Optional; leave empty to use the built-in subscribe page'],
        'google_analytics_id' => ['label' => 'Google Analytics 4 id', 'secret' => false, 'group' => 'analytics', 'hint' => 'G-XXXXXXXX; empty disables the tag'],
        'cloudflare_analytics_token' => ['label' => 'Cloudflare Web Analytics token', 'secret' => true, 'group' => 'analytics', 'hint' => 'Empty disables the beacon'],
        'analytics_require_consent' => ['label' => 'Ask for analytics consent', 'secret' => false, 'group' => 'live', 'hint' => 'On shows the consent banner before any analytics script loads.', 'options' => ['on' => 'On', 'off' => 'Off'], 'live' => ['off' => 'Analytics scripts load for every visitor without asking first.']],
        'social_cards_enabled' => ['label' => 'Drawn social cards', 'secret' => false, 'group' => 'site', 'hint' => 'On draws a preview image per record; off serves the static og-default.png everywhere', 'options' => ['on' => 'On', 'off' => 'Off']],
        'email_domain_enforcement' => ['label' => 'Refuse throwaway mailboxes', 'secret' => false, 'group' => 'live', 'hint' => 'On refuses disposable addresses on sign-up, subscribe and contribute.', 'options' => ['on' => 'On', 'off' => 'Off'], 'live' => ['on' => 'Sign-up, subscribe and contribute refuse every address on the disposable-mailbox list.']],
        'stale_after_days' => ['label' => 'Stale after (days)', 'secret' => false, 'group' => 'site', 'hint' => 'A record not verified within this many days is flagged stale. 30 to 730; default 180.'],
        'turnstile_site_key' => ['label' => 'Turnstile site key', 'secret' => false, 'group' => 'bot', 'hint' => 'Cloudflare dashboard → Turnstile → Add site (widget mode Managed). Public: it is placed in the page.'],
        'turnstile_secret_key' => ['label' => 'Turnstile secret key', 'secret' => true, 'group' => 'bot', 'hint' => 'Shown beside the site key in Cloudflare. Used only by the server to confirm each check.'],
        // Citation and funding. Each overrides the environment or config/funding.php; empty falls back.
        'dataset_doi' => ['label' => 'Dataset DOI', 'secret' => false, 'group' => 'citation', 'hint' => 'The Zenodo concept DOI, e.g. 10.5281/zenodo.1234567 or its https://doi.org/ URL. Shown in citations and the /open-data structured data. Only a DOI Zenodo has minted.'],
        'sponsor_url' => ['label' => 'Sponsor link', 'secret' => false, 'group' => 'citation', 'hint' => 'https link to the sponsorship page (GitHub Sponsors or a fiscal host). Shown on /funding.'],
        'funding_threshold' => ['label' => 'Disclosure threshold (USD a year)', 'secret' => false, 'group' => 'citation', 'hint' => 'Funders giving more than this in a year must be listed on /funding. Whole dollars.'],
        'google_site_verification' => ['label' => 'Google Search Console token', 'secret' => false, 'group' => 'analytics', 'hint' => 'The HTML-tag verification value'],
        'bing_site_verification' => ['label' => 'Bing Webmaster token', 'secret' => false, 'group' => 'analytics', 'hint' => 'The msvalidate.01 value'],
    ];

    /** @return array<string, array<string, mixed>> The keys of one group, in registry order. */
    public static function keysIn(string $group): array
    {
        return array_filter(self::KEYS, fn (array $meta) => $meta['group'] === $group);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::query()->find($key);
        if (! $row || $row->value === null) {
            return $default;
        }
        try {
            return Crypt::decryptString($row->value);
        } catch (DecryptException) {
            return $default;
        }
    }

    public static function put(string $key, ?string $value, ?int $userId = null): void
    {
        static::query()->updateOrCreate(['key' => $key], [
            'value' => $value === null || $value === '' ? null : Crypt::encryptString($value),
            'secret' => (bool) (self::KEYS[$key]['secret'] ?? false),
            'updated_by' => $userId,
        ]);
    }

    public static function mask(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return substr($value, 0, 4).str_repeat('•', 8).substr($value, -4);
    }
}
