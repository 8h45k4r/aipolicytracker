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

    /**
     * Indexation threshold, the same one every other record type on this site
     * applies: a page is offered to an index when it says something of its own.
     *
     * A risk entry's page is the repository's description of that risk. Without
     * one there is nothing on the page but a category name and the taxonomy codes
     * already shown on the parent, and 844 of the 2,500 entries are in exactly
     * that position. The "Additional evidence" rows are worse: 94% of them carry
     * no description, and each hangs under a parent entry that does (01.01.00.a,
     * .b and .c under 01.01.00), so indexing them offers an engine three near-
     * empty variants of a page it already has.
     *
     * Failing this is not a reason to hide the page from people. It is served
     * noindex,follow: reachable, linked, crawled onward to the parent, and not
     * offered as a result in its own right.
     */
    public function isIndexable(): bool
    {
        return filled($this->description) && $this->level !== 'Additional evidence';
    }

    public function navigatorUrl(): string
    {
        return 'https://airisk.mit.edu/navigator#/risks/browse';
    }
}
