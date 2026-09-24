<?php

namespace App\Services\Reviewers;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyDataRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Reads the reviewer roster from data/reviewers and joins it to what each
 * reviewer has actually verified.
 *
 * Two decisions worth keeping:
 *
 * The roster lives in version control, not a database, because a declaration of
 * interest is only worth something if every version of it is auditable. Editing
 * one means a pull request with a date, a diff and a second pair of eyes.
 *
 * The count beside each name comes from the records themselves, not from the
 * file. A roster that lists what someone is responsible for, with no way to see
 * what they have done, is a credentials page. The two numbers are allowed to
 * disagree, and when they do the honest one wins.
 */
class ReviewerRoster
{
    /**
     * Record kinds that carry `reviewed_by`, so a verification can be attributed to a person.
     *
     * Obligations are deliberately absent. They carry `review_status`,
     * `confidence_level` and `last_verified_at` but no reviewer name: the data
     * schema does not define one, the table has no column and the importer maps
     * none, because an obligation's verification belongs to the instrument it was
     * read out of. Querying them for `reviewed_by` is what took /reviewers down.
     */
    private const ATTRIBUTABLE = [
        'policy' => PolicyInstrument::class,
        'change' => ChangeEvent::class,
        'jurisdiction' => Jurisdiction::class,
    ];

    /** Every published kind, for the corpus total. Matches what /coverage counts. */
    private const ALL_KINDS = self::ATTRIBUTABLE + ['obligation' => Obligation::class];

    /** @var Collection<int, array>|null */
    private ?Collection $published = null;

    public function __construct(private readonly PolicyDataRepository $repository) {}

    /**
     * Published roster entries with their verification counts, ordered by role
     * then name.
     *
     * @return Collection<int, array>
     */
    public function published(): Collection
    {
        if ($this->published !== null) {
            return $this->published;
        }
        $counts = $this->verifiedCounts();
        $order = ['editor' => 0, 'reviewer' => 1, 'contributor' => 2];

        $roster = collect($this->repository->reviewers())
            ->filter(fn ($r) => (bool) ($r['published'] ?? true))
            ->map(function (array $r) use ($counts) {
                $name = (string) ($r['name'] ?? '');

                return $r + [
                    'verified' => $counts[$name] ?? 0,
                    'jurisdiction_names' => $this->jurisdictionNames($r['jurisdictions'] ?? []),
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($order[$a['role'] ?? ''] ?? 9) <=> ($order[$b['role'] ?? ''] ?? 9),
                fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''),
            ])
            ->values();

        return $this->published = $roster;
    }

    /**
     * How many published records each name has verified.
     *
     * Grouped by the name stored on the record rather than by a foreign key,
     * because verifications survive a re-import of the data and are re-applied
     * by slug; the name is what both sides carry.
     *
     * @return array<string, int>
     */
    public function verifiedCounts(): array
    {
        $counts = [];
        foreach (self::ATTRIBUTABLE as $class) {
            /** @var class-string<Model> $class */
            foreach ($class::published()->whereNotNull('reviewed_by')->where('review_status', 'verified')->pluck('reviewed_by') as $name) {
                $counts[(string) $name] = ($counts[(string) $name] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Corpus standing: how much of what is published has been through a named reviewer.
     *
     * @return array{published: int, attributable: int, verified: int, reviewers: int, unattributed: int}
     */
    public function standing(): array
    {
        $published = 0;
        foreach (self::ALL_KINDS as $class) {
            /** @var class-string<Model> $class */
            $published += $class::published()->count();
        }

        $attributable = 0;
        $verified = 0;
        foreach (self::ATTRIBUTABLE as $class) {
            /** @var class-string<Model> $class */
            $attributable += $class::published()->count();
            $verified += $class::published()->where('review_status', 'verified')->whereNotNull('reviewed_by')->count();
        }

        $counts = $this->verifiedCounts();
        $rosterNames = $this->published()->pluck('name')->map(fn ($n) => (string) $n)->all();
        // Verifications signed by a name that is not on the roster. The data validator
        // rejects these in data/, so a non-zero figure here means a verification recorded
        // in the admin queue that has not yet been exported and reviewed.
        $unattributed = 0;
        foreach ($counts as $name => $n) {
            if (! in_array($name, $rosterNames, true)) {
                $unattributed += $n;
            }
        }

        return [
            'published' => $published,
            'attributable' => $attributable,
            'verified' => $verified,
            'reviewers' => $this->published()->count(),
            'unattributed' => $unattributed,
        ];
    }

    /** @param list<string> $slugs @return list<string> */
    private function jurisdictionNames(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return Jurisdiction::whereIn('slug', $slugs)->orderBy('name')->pluck('name')->all();
    }
}
