# Posting to X

Each new verified change is posted to X with a flag, the headline, one sentence on what changed,
the link, hashtags and mentions. The link shows the change's preview card (`/og/change/<slug>.png`).

```
🇪🇺 Urgent: General-purpose AI duties under the EU AI Act start to apply

Providers of general-purpose AI models must now keep technical documentation…

https://aipolicytracker.org/changes/…

#AIPolicy #EUAIAct
@aipolicytracker @8h45k4r
```

## What gets posted

A change is posted once, when all of these hold:

- it is published and `review_status: verified` (unverified changes are never posted);
- it happened, and first appeared on the site, in the last 21 days (`social.x.max_age_days`), so a rebuilt
  database cannot post the archive;
- its impact is at or above `X_MIN_IMPACT`;
- it is not a template release (`template-*`).

An older change can still be posted by hand from the admin page, as a draft.

Hashtags: the base tags (`X_HASHTAGS`), `#AIStrategy` for a strategy, the instrument as one word
(`#EUAIAct`), then the place unless the instrument tag already names it. Three at most. Mentions come
from `X_MENTIONS`. Length is counted the way X counts it: a link is 23, a flag or emoji 2.

## How it runs

`social:post` (job `social_post`) runs every 30 minutes while posting is on. It queues a post per
eligible change, then sends up to two that are due, oldest first. In `review` mode new posts wait as
drafts until someone approves them.

Sending stops while posting is off, while a key is missing, or once `X_MONTHLY_CAP` posts went out
this month. A rate limit or an outage is retried up to five times. Any other refusal is kept as
failed with X's reason. `social:post --dry-run` prints what the next run would queue.

Posts are signed with OAuth 1.0a (`App\Services\Social\OAuth1`) and sent to `POST /2/tweets`. No image
is uploaded: the link's card is the image.

## Cost

X bills API posts by use. Since April 2026 a post containing a link costs about $0.20 and one without
about $0.015 (third-party reports of X's announcement; check the developer console for the current
rate). The monthly cap is the spending limit: 40 posts is about $8.

## Setting it up

1. Turn on the **Automated** label for the account (X → Settings → Your account → Account information →
   Automation), naming @8h45k4r as the person who runs it. X's automation rules require it.
2. At developer.x.com create a project and app, set **User authentication settings** to
   "Read and write", and add credit to the account.
3. Under **Keys and tokens**, copy the API key and secret, then generate the access token and secret
   *after* setting "Read and write" (a token made before cannot post).
4. In the admin: **Settings → Posting to X**, paste the four keys, choose automatic or approval, and save.
5. **Settings → Live switches → Post changes to X → Turn on.**
6. **Operations → Posts to X** shows what goes out next and what went out.

## Admin

**Operations → Posts to X** (`records.publish`): status, this month's count against the cap, new
changes not yet queued, and every post with its text as it will read. Actions: check for new changes,
approve, approve all drafts, edit the text (checked against 280), post now, skip, queue again, and
add an older change as a draft. Keys and the on/off switch are on the settings page (`settings.manage`).

## Schema: `social_posts`

| Field | Type | Null | Default | Notes |
|-------|------|------|---------|-------|
| id | integer | no |  |  |
| change_event_id | integer | no |  | FK → change_events.id (cascade) |
| network | varchar(16) | no | 'x' | Unique with change_event_id |
| status | varchar(16) | no | 'queued' | draft, queued, posted, failed, skipped |
| text | text | no |  | The post as sent |
| external_id | varchar(64) | yes |  | X's post id |
| attempts | tinyint | no | 0 |  |
| error | text | yes |  | X's reason for the last refusal |
| approved_by | integer | yes |  | FK → users.id (set null) |
| posted_at | timestamp | yes |  |  |
| created_at, updated_at | timestamp | yes |  |  |

## Interlinks

- Inbound: `change_events` (policy intelligence) and the change preview card.
- Outbound: X API. Settings in `app_settings` (`x_*` keys).

## Debt

- Posts only to X. Bluesky, Mastodon and LinkedIn would reuse `ChangePost` with another client.
- No image upload: X's media endpoint is moving to OAuth 2.0, and the link card already carries the design.
