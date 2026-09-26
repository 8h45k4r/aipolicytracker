<?php

namespace App\Services\ExternalData;

use App\Models\ExternalIncident;

/**
 * Which AI Incident Database records concern sexual imagery or sexual abuse,
 * and how to name them without repeating the headline.
 *
 * Search Console showed incident pages ranking for sexual searches about named
 * celebrities. Those visitors were not looking for AI policy, and the headline
 * that drew them was the database's, not ours. This decides, from the words on
 * the record, which pages need a neutral name instead.
 *
 * Deliberately a keyword list and not a model: it has to be explainable in one
 * sentence to anyone who asks why a page was treated this way. It was tuned by
 * reading every record it matched, not by its count.
 */
final class IncidentSensitivity
{
    /**
     * Sexual imagery or content, matched in the headline, because the headline
     * is what ranks. The terms are about imagery and material, not the word
     * "sexual" alone: an incident about harassment, assault or a sex-offender
     * registry is a different harm and keeps its own title.
     */
    private const IMAGERY = '/\b(porn\w*|nudes?|naked (?:photos?|pictures?|images?)|nude (?:photos?|pictures?|images?)|nudif\w*|nudification|sexually explicit|sexual(?:ised|ized)? (?:images?|imagery|content|deepfakes?|avatars?)|explicit (?:images?|imagery|photos?|pictures?|content|material|deepfakes?|activities)|intimate imag\w*|child (?:sex(?:ual)? abuse|pornography)|csam|sextortion|ncii|nsfw)\b/iu';

    /**
     * Terms specific enough to decide the matter from the description alone.
     * Broader words are not trusted there: a medical risk score whose
     * description mentions a patient's history is not a sexual-imagery
     * incident, and naming it as one would be a false statement about it.
     */
    private const UNAMBIGUOUS = '/\b(csam|child sexual abuse material|ncii|non-?consensual (?:intimate|sexual|explicit) (?:images?|imagery|deepfakes?|content)|nudify(?:ing)?|deepnude|deepfake (?:porn\w*|nudes?))\b/iu';

    /**
     * Headlines about a system wrongly flagging or removing content. "Twitter's
     * moderation tool misidentified rockets as pornography" mentions the word
     * but is a moderation failure, and a sexual-content label would misdescribe it.
     */
    private const MODERATION_ERROR = '/\b(misidentif\w*|mislabel\w*|mistook|mistaken(?:ly)?|erroneous(?:ly)?|wrongful(?:ly)?|flagged|censorship|banned|removal|detect(?:ion|ing)|moderation)\b/iu';

    /** What the record is marked as, or, before it has been marked, what the words say. */
    public static function isSensitive(ExternalIncident $incident): bool
    {
        if ($incident->sensitivity !== null) {
            return $incident->sensitivity === IncidentEnrichment::SENSITIVE;
        }

        return self::classify($incident);
    }

    /** The keyword classification alone, before any reviewer override. */
    public static function classify(ExternalIncident $incident): bool
    {
        $title = (string) $incident->title;
        if (preg_match(self::MODERATION_ERROR, $title)) {
            return false;
        }

        return (bool) (preg_match(self::IMAGERY, $title) || preg_match(self::UNAMBIGUOUS, (string) $incident->description));
    }

    /**
     * "AI incident: sexual content (Stability AI, Aug 2022)". One wording, broad
     * enough to be true of every record it is applied to. It names the
     * organisation that built the system, never the people depicted or harmed,
     * and never guesses a narrower harm the record does not state.
     */
    public static function neutralHeadline(ExternalIncident $incident): string
    {
        $detail = implode(', ', array_filter([self::organisation($incident), $incident->occurred_on?->format('M Y')]));

        return 'AI incident: sexual content'.($detail !== '' ? " ({$detail})" : '');
    }

    /** A neutral one-line description for the meta tag. */
    public static function neutralDescription(ExternalIncident $incident): string
    {
        $when = $incident->occurred_on?->format('j F Y');

        return 'An AI Incident Database record involving sexual content'.($when ? ", dated {$when}" : '')
            .'. Risk classification, related incidents and the laws that address this harm.';
    }

    /**
     * The developer of the system, from the database's own entity list. Not the
     * deployer: on these records the "deployer" is often the person who misused
     * the tool, or a group like "students", and that is not ours to name.
     */
    private static function organisation(ExternalIncident $incident): ?string
    {
        foreach ($incident->developers ?? [] as $name) {
            $name = trim(explode(',', (string) $name)[0]);
            if ($name !== '' && mb_strlen($name) <= 24
                && ! preg_match('/\b(unknown|unnamed|anonymous|creators?|developers?|users?|students?|individuals?|people|perpetrators?|scammers?|hackers?|predators?|men|women|man|woman|teen\w*|actors?)\b/i', $name)) {
                return $name;
            }
        }

        return null;
    }
}
