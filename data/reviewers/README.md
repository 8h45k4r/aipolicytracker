# Reviewer roster

One YAML file per reviewer, validated against `../schema/reviewer.schema.json` by
`php artisan policy:validate` and published at `/reviewers`.

The roster lives here rather than in a database on purpose: **a declaration of interest is
only worth something if it is auditable**. Keeping it in version control means every
declaration, and every later change to one, arrives through a pull request with a date, a
diff and a reviewer, exactly like the policy records themselves.

**A reviewer cannot be published without declaring their interests.** `interests` is
required by the schema and must hold at least one entry. "None declared." is a declaration;
leaving the field out is not, and fails the build.

```yaml
slug: jane-doe
name: Jane Doe
role: reviewer            # editor | reviewer | contributor
published: true
joined_on: 2026-09-17
bio: >-
  One or two sentences on the background that qualifies this person to check
  AI policy records against official sources.
expertise:
- EU AI Act
- Data protection
jurisdictions:            # slugs from ../jurisdictions
- european-union
affiliations:
- organisation: Example University
  role: Research fellow
  current: true
interests:
- declaration: Employed by a vendor of AI governance software.
  affects:
  - european-union
  mitigation: Does not verify records that name that vendor or its products.
  declared_on: 2026-09-17
links:
- label: Public profile
  url: https://example.org/people/jane-doe
```

`name` is also the join back to review activity: `/reviewers` counts the records whose
`reviewed_by` matches it, so the roster shows what each person has actually verified rather
than what they are listed as covering. Use the same spelling as the reviewer's account name
in the admin review queue.

Set `published: false` to keep an entry in the repository without listing it.
