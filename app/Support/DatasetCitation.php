<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Database\QueryException;

/**
 * The dataset's persistent identifier and the citation formats built on it.
 *
 * The DOI is the Zenodo concept DOI (the `dataset_doi` admin setting, else config
 * `aipolicytracker.dataset_doi`, env DATASET_DOI), which always resolves to the newest
 * archived release. Until the
 * maintainer has had one minted it is unset, and every caller must then render
 * nothing DOI-related: a placeholder DOI is worse than none, because a reader
 * copies it into a reference list.
 *
 * The DOI belongs to the whole corpus, not to a record. A record page therefore
 * says it is part of the DOI'd dataset; it never claims the DOI as its own.
 */
class DatasetCitation
{
    /**
     * The DOI as a bare name (10.prefix/suffix), or null when unset or malformed. The
     * `dataset_doi` setting saved in the admin wins over the environment value.
     */
    public static function doi(): ?string
    {
        try {
            $stored = trim((string) AppSetting::get('dataset_doi'));
        } catch (QueryException) {
            $stored = ''; // no settings table yet; the environment still answers
        }

        return self::parse($stored !== '' ? $stored : (string) config('aipolicytracker.dataset_doi'));
    }

    /**
     * Reduces a bare DOI, a `doi:` name or a doi.org URL to the bare DOI, or null when the
     * value is empty or malformed. The admin settings page validates with this too.
     */
    public static function parse(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $doi = preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#i', '', $raw);

        // A malformed value is treated as unset rather than printed: a wrong
        // identifier in a citation is not recoverable by the reader.
        return preg_match('#^10\.\d{4,9}/\S+$#', (string) $doi) ? $doi : null;
    }

    /** The resolver URL for the DOI, or null. */
    public static function doiUrl(): ?string
    {
        $doi = self::doi();

        return $doi ? 'https://doi.org/'.$doi : null;
    }

    /**
     * schema.org properties identifying the corpus Dataset by its DOI, or an empty
     * array when no DOI is set.
     *
     * @return array{identifier?: array, sameAs?: string}
     */
    public static function jsonLdIdentifier(): array
    {
        $doi = self::doi();
        if (! $doi) {
            return [];
        }

        return [
            'identifier' => ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi, 'url' => 'https://doi.org/'.$doi],
            'sameAs' => 'https://doi.org/'.$doi,
        ];
    }

    /**
     * A reference to the whole corpus as a Dataset node, for a record-level Dataset's
     * `isPartOf`. Null when no DOI is set, so a record keeps its existing graph.
     */
    public static function corpusNode(): ?array
    {
        if (! self::doi()) {
            return null;
        }

        return ['@type' => 'Dataset', '@id' => route('open-data').'#dataset', 'name' => config('aipolicytracker.site_name').' open AI policy dataset', 'url' => route('open-data')] + self::jsonLdIdentifier();
    }

    /**
     * A BibTeX entry for a record (or the dataset), using @misc so it compiles with
     * classic BibTeX as well as biblatex. The DOI field appears only when set.
     */
    public static function bibtex(string $title, string $url, ?\DateTimeInterface $accessed = null, ?int $year = null): string
    {
        $accessed ??= now();
        $year ??= (int) $accessed->format('Y');
        $doi = self::doi();
        $key = 'aipolicytracker'.$year.'_'.substr(preg_replace('/[^a-z0-9]+/', '', strtolower($title)) ?: 'record', 0, 24);
        $note = 'Data licensed CC BY 4.0. Accessed '.$accessed->format('j F Y');
        if ($doi) {
            $note .= '. Part of the '.config('aipolicytracker.site_name').' dataset';
        }
        $fields = array_filter([
            'author' => '{'.self::escape((string) config('aipolicytracker.site_name')).'}',
            'title' => '{'.self::escape($title).'}',
            'year' => (string) $year,
            'howpublished' => '\\url{'.$url.'}',
            'url' => $url,
            'urldate' => $accessed->format('Y-m-d'),
            'doi' => $doi,
            'note' => $note,
        ], fn ($v) => $v !== null && $v !== '');

        $lines = [];
        foreach ($fields as $name => $value) {
            $lines[] = '  '.str_pad($name, 12).' = {'.$value.'}';
        }

        return '@misc{'.$key.",\n".implode(",\n", $lines)."\n}";
    }

    /** Escapes the characters BibTeX treats as markup in a free-text field. */
    private static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\textbackslash{}', '{' => '\\{', '}' => '\\}', '&' => '\\&', '%' => '\\%', '$' => '\\$', '#' => '\\#', '_' => '\\_', '~' => '\\textasciitilde{}', '^' => '\\textasciicircum{}']);
    }
}
