<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalRisk extends Model
{
    protected $primaryKey = 'ev_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const LEVELS = ['Risk Category' => 'Risk category', 'Risk Sub-Category' => 'Risk subcategory', 'Additional evidence' => 'Additional evidence'];

    public const CAUSAL = ['entity' => ['Human', 'AI', 'Other', 'Not coded'], 'intent' => ['Intentional', 'Unintentional', 'Other', 'Not coded'], 'timing' => ['Pre-deployment', 'Post-deployment', 'Other', 'Not coded']];

    /** Profile URL; "#n" de-duplication suffixes are written as "--n" so the id is URL-safe. */
    public function url(): string
    {
        return route('risk.risks.show', str_replace('#', '--', $this->ev_id));
    }

    public function navigatorUrl(): string
    {
        return 'https://airisk.mit.edu/navigator#/risks/browse';
    }
}
