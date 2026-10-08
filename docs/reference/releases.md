# Dataset releases and DOIs

How a quarter's data becomes a citable, versioned release with a DOI.

Academics cite DOIs, not URLs, and DataCite DOIs are indexed by dataset search engines directly. The site already freezes each quarter's figures (`report:freeze`); a GitHub release on the same day, archived by Zenodo, gives that state of the data a permanent identifier.

## What is in the repository

| Piece | Purpose |
|-------|---------|
| `CITATION.cff` | Citation File Format 1.2.0, `type: dataset`. GitHub shows a "Cite this repository" button from it. |
| `.zenodo.json` | Metadata Zenodo uses when it archives a GitHub release: upload type, title, creators, licence (CC BY 4.0), keywords, related links. It takes precedence over `CITATION.cff` on Zenodo, so keep the two in step. |
| `DATASET_DOI` (env) → `config('aipolicytracker.dataset_doi')` | The concept DOI. When set, it is emitted as the corpus Dataset's `identifier` and `sameAs` in JSON-LD (home page, `/open-data`), each record's Dataset is declared `isPartOf` that DOI'd corpus, and the "Cite this record" box and `/open-data` show the DOI and carry it in the BibTeX. When unset, nothing DOI-related renders. Code: `App\Support\DatasetCitation`. |

Only real people go in `authors` and `creators`. Add a person when they have contributed to the data as a named reviewer or author and have agreed to be listed; never add an ORCID, affiliation or funder that has not been confirmed with them.

## One-time setup (maintainer)

1. Sign in to Zenodo with the GitHub account that owns the repository, open the GitHub integration page in Zenodo and switch the repository on. Zenodo then archives every **published** GitHub release.
2. Cut the first release (below). Zenodo mints two DOIs: a **version DOI** for that release and a **concept DOI** that always resolves to the newest version.
3. Set the **concept DOI** as the "Dataset DOI" on the admin settings page (Citation and funding), or set `DATASET_DOI` in the production environment and redeploy. A bare `10.5281/zenodo.N` or the `https://doi.org/` URL both work. The saved setting wins over the environment. Check `/open-data`: the citation section now shows the DOI.
4. Add `doi: 10.5281/zenodo.N` (the concept DOI) to `CITATION.cff` in a pull request.

Never set `DATASET_DOI` to a value Zenodo has not minted. A malformed value is ignored by the code, but a well-formed wrong DOI would be printed into every citation.

## Each quarter

On the first day of the quarter, after the scheduled `report:freeze` has run (or run it yourself):

```bash
php artisan report:freeze                 # freezes the quarter that just closed, e.g. 2026-Q3
php artisan policy:validate               # the data being released must pass
```

1. Release the code as usual (`CONTRIBUTING.md`, Releases): move `## [Unreleased]` in `CHANGELOG.md` to `## [X.Y.Z] - YYYY-MM-DD`, and name the frozen quarter in that section, e.g. "Dataset snapshot for 2026-Q3".
2. Tag and push (`git tag -a vX.Y.Z -m "vX.Y.Z" && git push origin vX.Y.Z`) or run **Release** from the Actions tab. `.github/workflows/release.yml` publishes the GitHub release from the changelog section.
3. Zenodo archives the release within minutes and mints its version DOI. Nothing else changes: `DATASET_DOI` stays the concept DOI, which now resolves to this release.
4. Draw the quarter's independent-check sample from the same data: `php artisan verification:sample` (see [verification policy](verification-policy.md#independent-checks)).

A draft release is not archived; Zenodo acts when the release is published, so publish only when the data is meant to be cited. A minted DOI cannot be withdrawn.
