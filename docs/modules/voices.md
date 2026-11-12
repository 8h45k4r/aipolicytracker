# AI Voices

**What it is.** `/voices` and `/voices/{slug}`: profiles of the people who hold the offices that shape AI policy, safety and governance. They include:
- heads of AI regulators and data-protection authorities;
- ministers responsible for AI;
- legislators who lead AI bills;
- heads of AI safety and security institutes;
- leaders of international and standards bodies.

**How people are chosen.** By the office they hold, never by fame or influence, and the list is not ranked. This follows the owner's choice of "office-holders, sourced" on 12 November 2026. A person is listed for their public role only. The rules:
- **No photographs.** Cards show initials.
- **No private details.** No contact details beyond the official profile page, no family, no opinions attributed without a source.
- **Corrections and removal:** every profile and the index link the correction form (`/contribute`). A request from the person concerned to correct or remove their profile is acted on.

**Where the facts come from.** Each profile says only what an official source says about the role:
- the official profile page of the body (`official_profile_url`);
- `sources` (official pages and official news agencies, each with the date accessed);
- optionally, `statements`: dated official speeches, statements or press releases, linked and never quoted at length.

The summary is written in the project's own words.

## Records: `data/voices/<slug>.yaml`

One file per person, validated by `policy:validate` against `data/schema/voice.schema.json`. There is no database table; `App\Services\Voices\VoiceCatalog` reads the files and caches them by modification time.

| Field | Required | Notes |
|---|---|---|
| slug | yes | Equals the file name |
| name, role, organisation | yes | As the official source gives them |
| jurisdiction | yes | A slug in `data/jurisdictions/`, or `international` |
| category | yes | `regulator`, `government`, `legislator`, `safety_institute`, `international`, `standards`, `judiciary` |
| topics | yes | From `policy`, `regulation`, `safety`, `governance`, `responsible_ai`, `strategy`, `risk`, `incidents`, `standards` |
| current | yes | Whether they still hold the role on `last_checked_at` |
| since | no | Date they took the role, only when a source states it |
| summary | yes | Own words, from the sources |
| related_policies | no | Policy slugs; each must exist |
| statements | no | `{title, date, url, publisher}` |
| official_profile_url | yes | https |
| sources | yes | `{title, publisher, url, accessed}` |
| published | yes | Only `true` profiles are shown |
| review_status, reviewed_by, last_verified_at | yes | As for policy records: only a person who opened the sources sets `verified`, with a reviewer published in `data/reviewers/` |
| last_checked_at, confidence_level | yes | |

**The first set, 12 November 2026.** There are 101 profiles: Europe 28, Asia-Pacific 28, the Americas 23, and international bodies, Africa and the Middle East 22.
- All are `pending_review` and `confidence_level: medium`.
- Agents drafted them from search results that pointed to official pages. The official hosts themselves could not be opened from the build environment, so a reviewer must open each source before marking a profile verified.
- Office-holders change. A profile whose person has left the role is set `current: false`, or unpublished, after checking.

## Pages

- `/voices`:
  - a bar showing the mix of roles;
  - filters by category and topic, where filtered views are noindex;
  - cards with initials, role and jurisdiction;
  - a "How people are chosen" note with the correction route.
- `/voices/{slug}` includes:
  - a summary and the official profile link;
  - the laws in this tracker that the role relates to;
  - dated official statements, the review status and the sources;
  - related voices from the same jurisdiction or laws.

  Its structured data is a `ProfilePage` whose `mainEntity` is a `Person`: name, job title, organisation, the official profile as `sameAs`, and no image.
- Both are in the sitemap (`resources` section) and `llms.txt`, and appear in the **News → People** menu.
- Analytics events (consent permitting): `voice_card`, `voice_official_profile`.

## Interlinks

- Outbound: policy pages (`related_policies`), jurisdiction pages, official profiles and sources (nofollow).
- Inbound: the News menu, the sitemap, `llms.txt`.

## Tests

`tests/Feature/VoicesTest.php` covers:
- every profile validates, is sourced, has an https official profile and no photo, and is never verified without a reviewer;
- the index lists profiles and filters by role, and filtered pages are noindex;
- a profile shows its role, official profile, sources and review status, has Person structured data and no image, and an unknown slug is 404;
- profiles are in the sitemap and `llms.txt`.
