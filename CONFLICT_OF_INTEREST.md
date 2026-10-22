# Conflict of interest

This document says how the project handles situations where a person's outside interests could
influence what the records say. It applies to every maintainer, reviewer and contributor.

## The rule

A person with an interest in the outcome of a record does not approve or merge that record alone.
They declare the interest in the pull request and step aside. Someone without that interest reviews
and approves the change. If no uninterested reviewer is available the change waits.

## What counts as an interest

An interest is anything a reasonable reader could think might skew a record. This includes:

- Employment, consulting or funding from a company, government or body named in the record.
- A financial stake (equity, revenue share, advisory fees) in a product or service that competes
  with, complies with, or is affected by the law or policy the record describes.
- Authorship of the law, regulation or guidance the record covers.
- An ongoing advocacy or lobbying position on the subject of the record.

When in doubt, declare it. A disclosed interest that turns out to be irrelevant costs nothing. An
undisclosed one that is later found relevant costs trust.

## The lead maintainer's standing interest

Bhaskar Bhatt is the lead maintainer and is associated with **Certifyi**, a commercial
AI-governance compliance platform. That association is disclosed:

- In the maintainer's reviewer roster entry (`data/reviewers/bhaskar-bhatt.yaml`).
- On the site's [independence page](/independence) and [funding page](/funding).
- In [`GOVERNANCE.md`](GOVERNANCE.md).

### What Certifyi does not get

- No editorial say over what records exist, what they say or how they are described.
- No early access: records are published to everyone at the same time.
- No private data: Certifyi uses the same public API and open data as everyone else.
- No advertising, affiliate links or vendor mentions in records.

### What this means in practice

a) The maintainer may author and merge code, schema and infrastructure changes without a second
   reviewer (though review is encouraged).

b) Once the independent data reviewer role is active (see [GOVERNANCE.md](GOVERNANCE.md)), every
   change to `data/` needs the independent reviewer's approval before it merges. The maintainer may
   not approve a data change alone.

c) If a specific record names Certifyi, a Certifyi product, or a competitor the maintainer has
   a financial relationship with, the maintainer declares the interest in the pull request and does
   not approve it. This applies even after the independent reviewer role is active.

d) Funding and sponsorship arrangements do not buy coverage, priority, favourable wording or
   suppression of a record. The funding rules are in [FUNDING.md](FUNDING.md).

e) If a contributor believes a conflict has not been declared, they raise it on the pull request or
   by emailing the independent reviewer. The independent reviewer's reading of whether a conflict
   exists is final on data questions.

**Status.** Rule (b) is not yet enforced. The independent data reviewer's roster entry exists but
is not published because a declaration of interest has not been received. Until it is, the
maintainer can approve data changes alone. The [setup checklist](MANUAL_SETUP_CHECKLIST.md) tracks
the steps needed to make rule (b) effective.

## Reviewers

Every reviewer publishes a declaration of interest in `data/reviewers/` before they may mark a
record verified. The declaration lists every interest a reader could consider relevant. A reviewer
who acquires a new interest updates their declaration by pull request.

A reviewer does not verify a record where they have a declared interest unless no other reviewer is
available, and in that case the interest is noted on the record.

## Corrections

A person with an interest in a record does not close a correction report about that record alone.
See the corrections process in [GOVERNANCE.md](GOVERNANCE.md).

## Changing this policy

Changes to this file go through a pull request. The independent reviewer's objection is recorded
in the PR even if overruled. See [GOVERNANCE.md](GOVERNANCE.md), "Policy and governance changes".
