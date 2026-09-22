<?php

namespace App\Support;

/**
 * Human labels for the record fields a reader can single out when reporting a
 * correction. Shared by the contribute form, which shows the current value of
 * the field, and the public corrections log, which names the field an entry
 * concerned.
 *
 * It lives here rather than in either of them because a field named one way on
 * the form and another way in the log would read as two different things.
 */
class SubmissionFieldLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'title' => 'Title', 'name' => 'Name', 'status' => 'Status', 'instrument_type' => 'Instrument type', 'is_binding' => 'Binding or non-binding',
        'issuing_body' => 'Issuing body', 'adopted_on' => 'Adopted on', 'in_force_on' => 'In force on', 'applies_from' => 'Applies from',
        'summary_plain' => 'Plain-language summary', 'scope_summary' => 'Scope',
        'who_it_applies_to' => 'Who it applies to', 'what_organizations_must_do' => 'What organisations must do', 'key_dates_summary' => 'Key dates', 'penalties_summary' => 'Penalties',
        'official_source_url' => 'Official source URL', 'regulatory_status_summary' => 'Regulatory status', 'binding_vs_guidance' => 'Binding vs guidance',
        'current_priorities' => 'Current priorities', 'regulators' => 'Regulators', 'category' => 'Category', 'summary' => 'Summary',
        'practical_action' => 'Practical action', 'controls' => 'Controls that meet the duty', 'kind' => 'Kind of control', 'purpose' => 'Purpose', 'owner_role' => 'Owner role', 'frequency' => 'Frequency', 'source_reference' => 'Source reference', 'occurred_on' => 'Date of change',
        'what_changed' => 'What changed', 'description' => 'Description', 'deployers' => 'Alleged deployer', 'developers' => 'Alleged developer',
        'harmed' => 'Alleged harmed party', 'mit_domain' => 'Risk domain', 'mit_subdomain' => 'Risk subdomain', 'entity' => 'Causal entity',
        'intent' => 'Intent', 'timing' => 'Timing', 'harm_level' => 'Harm level', 'countries' => 'Countries', 'risk_category' => 'Risk category',
        'risk_subcategory' => 'Risk subcategory', 'domain' => 'Domain', 'subdomain' => 'Subdomain', 'paper_title' => 'Source paper',
        'practical_impact' => 'Practical impact', 'impact_level' => 'Impact level', 'status_after' => 'Status after change',
    ];

    /**
     * The label for a field name.
     *
     * An unknown name falls back to the name itself, humanised, rather than to
     * nothing: a field that is missing from the map should look untidy in the
     * log so somebody adds it, not silently vanish from the entry.
     */
    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
