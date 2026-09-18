<?php

namespace App\Services\Completeness;

/**
 * What a published record must carry to be worth publishing.
 *
 * Each check names one field or relationship, the record kind it applies to,
 * and a severity:
 *
 *   required  A record should not be published without it. Required gaps are
 *             what `policy:coverage` ratchets on, so the corpus cannot quietly
 *             get thinner.
 *   expected  The record works without it but is less useful. Counted and
 *             published, never gated, because gating it would push editors
 *             towards filling boxes rather than checking facts.
 *
 * `missing` returns true when the check fails. `applies` narrows a check to the
 * records it makes sense for; a check with no `applies` covers every published
 * record of its kind. Both receive the model.
 *
 * `field` must name a field the correction form accepts for that kind
 * (ContributeController::CORRECTABLE_FIELDS), or the "Fill this in" link on
 * /gaps silently drops it. A test asserts this for every check.
 *
 * This lives in app/ rather than config/ because the checks carry closures and
 * `php artisan config:cache` cannot serialize those. See VerificationRuleset
 * for what that cost in production. Anything in config/ must stay serializable.
 */
class CompletenessChecks
{
    /** @return list<array{id: string, kind: string, field: string, label: string, why: string, severity: string, missing: callable, applies?: callable}> */
    public static function all(): array
    {
        return [

            // ---- Policy instruments ------------------------------------------------
            [
                'id' => 'policy-source-url',
                'kind' => 'policy',
                'field' => 'official_source_url',
                'label' => 'Link to the official text',
                'why' => 'Without it a reader cannot check the record against the instrument itself.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->official_source_url),
            ],
            [
                'id' => 'policy-source-publisher',
                'kind' => 'policy',
                'field' => 'official_source_url',
                'label' => 'Name of the body that published the source',
                'why' => 'A link can move or die; the publisher and document date let a reader find the text again.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->source_publisher),
            ],
            [
                'id' => 'policy-source-date',
                'kind' => 'policy',
                'field' => 'official_source_url',
                'label' => 'Date of the source document',
                'why' => 'Tells a reader which version of the text the record describes.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->source_document_date),
            ],
            [
                'id' => 'policy-summary',
                'kind' => 'policy',
                'field' => 'summary_plain',
                'label' => 'Plain-language summary',
                'why' => 'A title alone does not say what the instrument does.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->summary_plain),
            ],
            [
                'id' => 'policy-scope',
                'kind' => 'policy',
                'field' => 'who_it_applies_to',
                'label' => 'Who it applies to',
                'why' => 'The first question a reader asks is whether it applies to them.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->who_it_applies_to),
            ],
            [
                'id' => 'policy-dates',
                'kind' => 'policy',
                'field' => 'applies_from',
                'label' => 'At least one dated milestone',
                'why' => 'An instrument with no adoption, force or application date cannot be planned around.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->adopted_on) && blank($r->in_force_on) && blank($r->applies_from) && blank($r->published_on),
            ],
            [
                'id' => 'policy-obligations',
                'kind' => 'policy',
                'field' => 'what_organizations_must_do',
                'label' => 'Duties mapped out of a binding instrument',
                'why' => 'Binding law that carries no mapped obligation cannot be turned into anything a team can act on.',
                'severity' => 'expected',
                'applies' => fn ($r) => (bool) $r->is_binding && in_array($r->status, ['in_force', 'partially_in_force'], true),
                'missing' => fn ($r) => ! $r->obligations()->published()->exists(),
            ],

            // ---- Obligations -------------------------------------------------------
            [
                'id' => 'obligation-reference',
                'kind' => 'obligation',
                'field' => 'source_reference',
                'label' => 'The article or section the duty comes from',
                'why' => 'A duty that does not name its provision cannot be traced back to the law.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->source_reference),
            ],
            [
                'id' => 'obligation-summary',
                'kind' => 'obligation',
                'field' => 'summary',
                'label' => 'What the duty requires',
                'why' => 'A category and a title are not a duty.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->summary),
            ],
            [
                'id' => 'obligation-action',
                'kind' => 'obligation',
                'field' => 'practical_action',
                'label' => 'What an organisation actually does about it',
                'why' => 'This is the line that turns a requirement into work; without it the record is a restatement of the text.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->practical_action),
            ],

            // ---- Change log entries ------------------------------------------------
            [
                'id' => 'change-source-url',
                'kind' => 'change',
                'field' => 'official_source_url',
                'label' => 'Link to the official announcement',
                'why' => 'A change nobody can verify is a rumour.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->official_source_url),
            ],
            [
                'id' => 'change-impact',
                'kind' => 'change',
                'field' => 'practical_impact',
                'label' => 'What the change means in practice',
                'why' => 'Without it the entry says something moved but not what to do about it.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->practical_impact),
            ],

            // ---- Jurisdiction profiles ---------------------------------------------
            [
                'id' => 'jurisdiction-source-url',
                'kind' => 'jurisdiction',
                'field' => 'official_source_url',
                'label' => 'Link to an official source for the profile',
                'why' => 'A country summary with no source is an opinion.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->official_source_url),
            ],
            [
                'id' => 'jurisdiction-status',
                'kind' => 'jurisdiction',
                'field' => 'regulatory_status_summary',
                'label' => 'Where the jurisdiction currently stands',
                'why' => 'The profile exists to answer this.',
                'severity' => 'required',
                'missing' => fn ($r) => blank($r->regulatory_status_summary),
            ],
            [
                'id' => 'jurisdiction-regulators',
                'kind' => 'jurisdiction',
                'field' => 'regulators',
                'label' => 'Who regulates AI there',
                'why' => 'A reader needs to know which body to read and who enforces.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->regulators),
            ],
            [
                'id' => 'jurisdiction-binding-split',
                'kind' => 'jurisdiction',
                'field' => 'binding_vs_guidance',
                'label' => 'What is binding and what is only guidance',
                'why' => 'Conflating the two is the most common and most expensive mistake in this field.',
                'severity' => 'expected',
                'missing' => fn ($r) => blank($r->binding_vs_guidance),
            ],
        ];
    }
}
