# Governance

This document says who decides what in AI Policy Tracker and how. It describes how the
project runs today; changes to it go through a pull request like any other change.

## Roles

| Role | Who | What they do |
|------|-----|--------------|
| Maintainer | Bhaskar Bhatt ([@8h45k4r](https://github.com/8h45k4r)) | Reviews and merges pull requests, publishes records, cuts releases, handles security reports. Listed in `.github/CODEOWNERS`. |
| Reviewer | Anyone published in [`data/reviewers/`](data/reviewers/) with a declaration of interest | Checks records against their official source and marks them verified. The data validator rejects a verification signed by anyone not on the roster. |
| Contributor | Anyone | Opens issues, submits corrections through `/contribute`, and sends pull requests. See [CONTRIBUTING.md](CONTRIBUTING.md). |

A person becomes a reviewer by opening a pull request that adds their roster file, including
every interest that a reader could consider relevant. The maintainer merges it or explains why not.

## How decisions are made

- **Code and data changes:** by pull request. Every change needs the maintainer's approval and a
  green CI run, including the data validator and the security scans. The five role gates in
  `docs/reference/change-gates.md` are binding.
- **What counts as a fact:** only what an official or openly licensed source says (see
  [SOURCE_ATTRIBUTION.md](SOURCE_ATTRIBUTION.md)). Where a source is ambiguous the record says so
  and its confidence level is lowered; the project does not settle legal questions.
- **Disagreements:** raised on the issue or pull request. The maintainer decides and records the
  reason there. A decision on a correction is published in the corrections log on the site.

## Corrections

Anyone can report an error from the "Report a correction" link on any record or through the
*Policy data correction* issue template. Every report is decided and logged publicly at
`/corrections`, including the ones that are declined, with the reason.

## Conflicts of interest

The maintainer is associated with Dignep Group Pvt. Ltd., which operates Certifyi, a separate
commercial compliance platform. The records on this site name no vendor or product, every
verification is made against the official source that the record links, and every reviewer
publishes their interests in `data/reviewers/`. Certifyi has no access to this project's
systems or data beyond what the public API and open data offer to anyone.

## Licences

Code: Apache-2.0 (`LICENSE`). Data: CC BY 4.0 (`data/LICENSE`). Third-party datasets keep their
own licences (`NOTICE`).

## Conduct and security

Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md). Security issues are
reported privately as described in [SECURITY.md](SECURITY.md).
