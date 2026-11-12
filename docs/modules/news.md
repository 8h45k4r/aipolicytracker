# News

**What it is.** `/news`: one dated list of AI policy news, newest first. It merges three streams:
- **Official sources:** headlines from regulators' and governments' own feeds (`news_items`).
- **Recorded changes:** published entries in this tracker's change log (`change_events`), site updates excluded.
- **AI incidents:** indexable incident records from the AI Incident Database import (`data/external/`).

Readers can filter by topic (policy and law, safety and security, governance, responsible AI, strategy and investment, risk, incidents) and by stream.

**What it is not.** Feed items are not copied or summarised. Each one shows only its headline, its date and its publisher, and links to the publisher's own page. No third-party news site, aggregator or paid database is a source. This follows the owner's choice of "official sources only" on 12 November 2026 and [`SOURCE_ATTRIBUTION.md`](../../SOURCE_ATTRIBUTION.md).

## Sources

`config/news.php`, each fetched and parsed before it was listed (11 and 12 November 2026):

| Key | Publisher | Format | Filtered for AI |
|---|---|---|---|
| `nist` | US National Institute of Standards and Technology | RSS | yes |
| `uk-aisi` | AI Security Institute (GOV.UK) | Atom | no: the whole feed is about AI |
| `uk-dsit` | Department for Science, Innovation and Technology (GOV.UK) | Atom | yes |
| `uk-govuk-ai` | GOV.UK search, "artificial intelligence" | Atom | yes |
| `eu-digital-strategy` | European Commission | RSS | yes |
| `federal-register` | US Office of the Federal Register | JSON API | yes |

**AI filter.** On a filtered feed, an item is kept when either of these holds:
- its title matches `relevance`;
- its summary matches the narrower `relevance_summary`, because a passing "AI" in a long summary is not enough.

**Topics.** Topics come from the `topics` patterns, matched against the title and summary. An item can have several topics or none.

Hosts tried with no usable feed: OECD.AI, UNESCO, ICO. Some hosts are blocked by the network: the California legislature, the Connecticut legislature and the CPPA. A new source is added only after its feed has been fetched and checked, and only if the publisher's own site offers the feed.

## Schema: `news_items`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| source | varchar(40) | no |  | A key of `config('news.sources')` |
| external_id | varchar(255) | no |  | The feed's guid or id, else the link; unique with `source` |
| title | varchar(500) | no |  | As published |
| url | varchar(500) | no |  | The publisher's page |
| jurisdiction_slug | varchar(80) | yes |  | From the source's config |
| topics | json | no |  | Topic keys |
| published_at | timestamp | no |  | The feed's date |
| fetched_at | timestamp | yes |  | Last time the feed returned it |
| created_at, updated_at | timestamp | yes |  |  |

Unique (`source`, `external_id`); index on `published_at`. No summary or body column, by design.

## Jobs and configuration

- `php artisan news:sync [--source=key]`: job `news_sync`, every two hours while `NEWS_SYNC` is not false.
  - A feed that fails is reported and skipped. The run fails only when every feed fails.
  - Items older than `keep_days` (365) are pruned.
- XML is parsed with network access off (`LIBXML_NONET`).

## Page rules

- Changes and incidents come from the last 180 days. There are 40 items per page.
- Only the first page with no filter is indexable. Filtered and later pages are noindex, and a page past the end answers 404.
- `/news/{anything}` still redirects to `/changes` (old addresses).
- Analytics events (consent permitting): `news_click_official`, `news_click_records`, `news_click_incidents`.

## Interlinks

- Inbound: the **News** menu (`config/navigation.php`), `llms.txt`, the sitemap.
- Outbound: publisher pages (nofollow), change records (`/changes/...`), incident pages (`/ai-risk/incidents/...`).

## Tests

`tests/Feature/NewsTest.php` covers:
- only AI headlines are kept from filtered feeds, everything from an AI-only feed, once each, with topics;
- a failing feed is reported and the others still run;
- the page shows the headline, publisher and link and never the feed text; it filters by topic, a filtered page is noindex and a page past the end is 404;
- the AI filter treats a passing "AI" in a summary as not enough.
