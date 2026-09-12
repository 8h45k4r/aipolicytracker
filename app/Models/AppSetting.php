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

    /** Keys the admin settings page may manage. */
    public const KEYS = [
        'mail_mailer' => ['label' => 'Mail transport', 'secret' => false, 'hint' => 'log, resend, smtp'],
        'resend_key' => ['label' => 'Resend API key', 'secret' => true, 'hint' => 'From resend.com/api-keys ("Sending access")'],
        'mail_from_address' => ['label' => 'From address', 'secret' => false, 'hint' => 'Must belong to a domain verified in Resend'],
        'mail_from_name' => ['label' => 'From name', 'secret' => false, 'hint' => 'e.g. AI Policy Tracker'],
        'cron_token' => ['label' => 'Cron token', 'secret' => true, 'hint' => 'Shared secret for the weekly digest trigger'],
        'dodo_environment' => ['label' => 'Dodo environment', 'secret' => false, 'hint' => 'test_mode or live_mode'],
        'dodo_api_key' => ['label' => 'Dodo API key', 'secret' => true, 'hint' => 'From Dodo Payments → Developer → API keys (test keys start with dodo_test_)'],
        'dodo_webhook_secret' => ['label' => 'Dodo webhook secret', 'secret' => true, 'hint' => 'Signing secret of the webhook endpoint (starts with whsec_)'],
        'dodo_product_pro_monthly' => ['label' => 'Product id: Pro monthly', 'secret' => false, 'hint' => 'pdt_… id of the monthly subscription product'],
        'dodo_product_pro_yearly' => ['label' => 'Product id: Pro annual', 'secret' => false, 'hint' => 'pdt_… id of the annual subscription product'],
    ];

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
