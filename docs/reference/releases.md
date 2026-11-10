# Releases, versions and DOIs

How the project is versioned, how a release is cut, and how a quarter's data becomes a citable release with a DOI.

## Versions

Releases follow [Semantic Versioning](https://semver.org/) and are tagged `vX.Y.Z` on a commit on `main`. What counts
as the public interface is what someone running or consuming the project depends on: the REST API (`/api/v1`) and
its OpenAPI document, the data files and their JSON Schemas in `data/`, the open-data exports, the MCP server's
tools, the permanent ids, and what it takes to run a copy (PHP and Node versions, required environment keys,
migrations).

| Bump | When | Examples |
|------|------|----------|
| **Major** (`X`) | Something that worked stops working without action by the user. | A higher PHP minimum; an API field removed or renamed; tokens that now expire; a schema change that rejects files that used to validate. |
| **Minor** (`Y`) | New capability or a new quarter's data, nothing breaks. | A new endpoint, field, export or page; new records; the quarterly dataset snapshot. |
| **Patch** (`Z`) | Fixes only. | A bug fix, a security fix, a corrected record, documentation. |

A major release opens its changelog section with **Upgrade notes**: what breaks and the steps to upgrade a running copy.

## When to release

- **Each quarter**, as a dataset snapshot, after that quarter's report is frozen (below). This is the regular release.
- **Whenever a breaking change or a security fix lands on `main`**, so people running a copy can pin a version that
  has it. Do not hold a security fix for the quarter.
- Not for every deploy. The site deploys continuously from `main`; a release marks a state worth citing or pinning.

## Cutting a release

1. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`, start a new empty `## [Unreleased]`
   above it, and update the comparison links at the bottom. For a quarterly release, open the section with the
   dataset line ("Dataset snapshot: …"); for a major one, add **Upgrade notes**.
2. Set the version in `package.json`, `CITATION.cff` (`version`, `date-released`), `.zenodo.json` and `codemeta.json`.
3. Check: `scripts/release/check-changelog.sh` (CI runs it on every push) and `scripts/release/notes.sh X.Y.Z`
   (prints the release notes; fails if the section is missing or empty).
4. Merge to `main` and wait for CI to pass.
5. Run **Release** from the Actions tab on `main` (or push an annotated tag: `git tag -a vX.Y.Z -m "vX.Y.Z" && git
   push origin vX.Y.Z`). The workflow refuses a commit that is not on `main` or whose required CI jobs are not
   green, creates the tag if needed, and publishes the GitHub release with the changelog section as its notes.
6. After the deploy, run **Deployment check** from the Actions tab.

Never move or delete a published tag: Zenodo archives each release and its DOI cannot be withdrawn. A mistake is
fixed by a new patch release.

## Dataset DOIs

Academics cite DOIs, not URLs, and DataCite DOIs are indexed by dataset search engines directly. The site already freezes each quarter's figures (`report:freeze`); a GitHub release on the same day, archived by Zenodo, gives that state of the data a permanent identifier.

## Citation metadata in the repository

| Piece | Purpose |
|-------|---------|
| `CITATION.cff` | Citation File Format 1.2.0, `type: dataset`. GitHub shows a "Cite this repository" button from it. |
| `.zenodo.json` | Metadata Zenodo uses when it archives a GitHub release: upload type, title, creators, licence (CC BY 4.0), keywords, related links. It takes precedence over `CITATION.cff` on Zenodo, so keep the two in step. |
| `DATASET_DOI` (env) → `config('aipolicytracker.dataset_doi')` | The concept DOI. When set, it is emitted as the corpus Dataset's `identifier` and `sameAs` in JSON-LD (the one corpus node, `Seo::corpusDataset()` at `/open-data`; the home page and each narrower Dataset reference it by `@id`), each record page is declared `isPartOf` that DOI'd corpus, and the "Cite this record" box and `/open-data` show the DOI and carry it in the BibTeX. When unset, nothing DOI-related renders. Code: `App\Support\DatasetCitation`. |

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

1. Cut the release as above: move `## [Unreleased]` in `CHANGELOG.md` to `## [X.Y.Z] - YYYY-MM-DD`, and name the frozen quarter in that section, e.g. "Dataset snapshot for 2026-Q3".
2. Tag and push (`git tag -a vX.Y.Z -m "vX.Y.Z" && git push origin vX.Y.Z`) or run **Release** from the Actions tab. `.github/workflows/release.yml` publishes the GitHub release from the changelog section.
3. Zenodo archives the release within minutes and mints its version DOI. Nothing else changes: `DATASET_DOI` stays the concept DOI, which now resolves to this release.
4. Draw the quarter's independent-check sample from the same data: `php artisan verification:sample` (see [verification policy](verification-policy.md#independent-checks)).

A draft release is not archived; Zenodo acts when the release is published, so publish only when the data is meant to be cited. A minted DOI cannot be withdrawn.
