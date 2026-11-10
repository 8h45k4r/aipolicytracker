# Governance

This document says who decides what in AI Policy Tracker and how. It describes how the
project runs today; where something is planned but not yet enforced it says so. Changes to
this document go through a pull request like any other change.

## Roles

| Role | Who | What they do | What they cannot do |
|------|-----|--------------|---------------------|
| Lead maintainer | Bhaskar Bhatt ([@8h45k4r](https://github.com/8h45k4r)) | Merges pull requests, publishes records, cuts releases, manages infrastructure, handles security reports. Listed in `.github/CODEOWNERS`. | Approve a change to `data/` alone once an independent reviewer is active (see [CONFLICT_OF_INTEREST.md](CONFLICT_OF_INTEREST.md)). |
| Independent data reviewer | Joyce Loksee Ho ([joyce@aipolicytracker.org](mailto:joyce@aipolicytracker.org)) | Reviews data changes for accuracy against official sources. Requested on every `data/` pull request through CODEOWNERS. Has a veto on data accuracy disputes. | Merge pull requests or deploy (today). This changes when branch protection and merge access are set up. |
| Reviewer | Anyone published in [`data/reviewers/`](data/reviewers/) with a declaration of interest | Checks records against their official source and marks them verified. The data validator rejects a verification signed by anyone not on the roster. | Mark records verified without opening the official source. |
| Contributor | Anyone | Opens issues, submits corrections, sends pull requests. See [CONTRIBUTING.md](CONTRIBUTING.md). | — |

**Status of the independent reviewer role.** Joyce's reviewer entry exists in the roster
but is not yet published because her declaration of interest has not been received. Until
it is, records cannot be signed in her name and CODEOWNERS review requests will show her
handle but her approval is not yet enforced by branch protection. The lead maintainer must
complete the items in the setup checklist to make this role effective.

A person becomes a reviewer by opening a pull request that adds their roster file to
`data/reviewers/`, including every interest a reader could consider relevant. The lead
maintainer merges it, or explains why not, after the independent reviewer (when active)
confirms the declaration is complete.

## How decisions are made

### Data changes

Every change to `data/` goes through a pull request with:

1. An approving review from someone listed in CODEOWNERS for that path.
2. A green CI run, including the data validator, the security scans and the five role
   gates in [`docs/reference/change-gates.md`](docs/reference/change-gates.md).
3. When an active independent reviewer is listed in CODEOWNERS: their approval, or a
   written explanation why they recused themselves on that record.

A person with a conflict of interest on a specific record declares it in the PR and does
not approve it. See [CONFLICT_OF_INTEREST.md](CONFLICT_OF_INTEREST.md).

### What counts as a fact

Only what an official or openly licensed source says. See
[SOURCE_ATTRIBUTION.md](SOURCE_ATTRIBUTION.md). Where a source is ambiguous the record
says so and its confidence level is lowered; the project does not settle legal questions.

### Schema and code changes

Decided by the lead maintainer after discussion on the issue or pull request. The
independent reviewer is consulted on changes that affect how data is stored, validated
or displayed, but does not have a veto on code.

### Policy and governance changes

Changes to this file, CONFLICT_OF_INTEREST.md, FUNDING.md or SOURCE_ATTRIBUTION.md are
decided by the lead maintainer after a public pull request. The independent reviewer's
objection on a governance change is recorded in the PR even if overruled. This is a
concession to the project's current size; the target is consensus.

### Disagreements

Raised on the issue or pull request. On a data accuracy question, the independent
reviewer's reading of the official source wins. On everything else the lead maintainer
decides and records the reason. A decision on a correction is published in the
corrections log on the site.

## Corrections

Anyone can report an error from the "Report a correction" link on any record or through
the *Policy data correction* issue template. Every report is decided and logged publicly
at `/corrections`, including the ones that are declined, with the reason. A person with a
conflict of interest on the record does not close the correction alone.

## Bus factor and succession

**Today.** The lead maintainer holds sole admin access to the GitHub organisation, the
domain, the hosting account and the deployment credentials. This is a single point of
failure.

**Required (not yet in place).**

- A second person must hold GitHub organisation owner or repository admin access.
- Domain registrar credentials must be accessible to at least two people.
- Hosting and deployment credentials must be documented in a sealed envelope, a password
  manager vault shared with the independent reviewer, or equivalent.

If the lead maintainer is unavailable for 30 or more consecutive days and these
provisions are in place, the independent reviewer (or the second admin) may:

- merge data corrections that have a green CI run and a reviewer approval,
- publish security advisories,
- keep the site running (renew hosting, apply security patches).

They may not change the project's licence, governance structure, or funding arrangements
without the lead maintainer's return or a public process agreed by the remaining
maintainers and reviewers.

## Adding maintainers and reviewers

New reviewers are added by pull request as described above. The project aims to recruit
independent reviewers from regions that are underrepresented in AI governance data work,
especially South Asia, South-East Asia, Africa and Latin America.

A new maintainer (someone with merge access) is added by:

1. A pull request updating this file and `.github/CODEOWNERS`.
2. Approval from the lead maintainer and the independent reviewer.
3. A published declaration of interest in `data/reviewers/`.

## Conflicts of interest

See [CONFLICT_OF_INTEREST.md](CONFLICT_OF_INTEREST.md) for the full policy. In short:
the lead maintainer is associated with Certifyi AI, a commercial compliance platform.
That product has no editorial say, no early access and no data beyond what is public.
Every data change needs approval from a reviewer with no Certifyi ties once the
independent reviewer role is active.

## Licences

Code: Apache-2.0 (`LICENSE`). Data: CC BY 4.0 (`data/LICENSE`). Third-party datasets
keep their own licences (`NOTICE`).

## Conduct and security

Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md). Security issues
are reported privately as described in [SECURITY.md](SECURITY.md).
