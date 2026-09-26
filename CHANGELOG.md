# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Security
- An account became owner if its email matched `ADMIN_EMAILS`, verified or not. Anyone who registered an owner address that had no account yet, or changed their own profile email to one, could enrol their own authenticator and hold every capability. Ownership now requires a verified address.
- A session holding only an admin's password, stopped at the second-factor challenge, could still change the account's email or password or delete it, and so free an owner address to re-register. The profile and password routes now sit behind the second factor for admins; `POST /confirm-password` is throttled.
- Suspending an account, resetting its second factor, or changing or resetting its password now ends its other sessions and remember-me cookies. They had been checked only at the next password sign-in.
- A billing webhook that failed while being applied was acknowledged as a duplicate on the provider's retry and never applied, so a cancellation hit by a deadlock left Pro access in place. A retry of a failed event is now applied.
- The subscribe form sends at most three confirmation emails per address per day, whatever the source IP. It could be used to mail any unconfirmed address thousands of times a day.
- `/og/site/{slug}.png` drew and stored a fresh image for any made-up slug, so a loop of GETs filled the disk. Only `default` exists now.
- Public CSV exports of third-party data (AI incidents, risks, open data) escape cells a spreadsheet would run as formulas.
- Array query parameters (`page[]=`, `q[]=`, `jurisdictions[][]=`) no longer cause a 500 on the API, the risk browser or the applicability check, and a non-numeric `page` no longer creates a cache entry per variant.
- The AIID and MIT sync commands fail on an HTTP error instead of saving the error page as a workbook.
- A proxy the app trusts could have its visitor's `X-Forwarded-Host`, `-Port` and `-Prefix` rewrite every URL the app generated, including pagination links cached in public API responses. Only `X-Forwarded-For` and `-Proto` are trusted now.
- An admin cannot rename themselves on the profile page: the reviewer roster matches display names, so a rename could sign verifications as someone else.
- Confirming a subscription or unsubscribing takes a button press. Opening the link no longer changes anything, so mail scanners that follow every URL cannot do it for the reader.
- Security headers (CSP, `nosniff`, frame options) are sent on every response, including the API and 404 pages; `X-Powered-By` is removed.
- Full-table exports, `llms-full.txt` and social cards are rate-limited. Jobs cannot overlap, whichever of the scheduler, `/cron/*` or the admin started them. The `past_due` grace period no longer restarts with each webhook. Future-dated records are not reachable by slug. Third-party report links are http(s) only. Flowbite is pinned by Subresource Integrity. The XLSX reader bounds column references.
- Production defaults to secure, encrypted session cookies. The Docker image keeps `public/` read-only for the runtime user, sets `expose_php=Off`, has a health check, and builds assets on Node 22 (Node 20 is end of life).
- GitHub Actions are pinned to commit SHAs, and the one workflow input is validated before use.

### Added
- `.github/workflows/security.yml`: secret scanning of the full history (Gitleaks), SAST (Semgrep), dependency audits (composer, npm, Trivy), IaC (Trivy, Checkov) and a container image scan on every pull request; ZAP DAST and an active API scan weekly. Dependabot for composer, npm, Actions and Docker. See `docs/reference/security-scanning.md` and the assessment in `docs/reference/vapt-2026-09-24.md`.
- `data/LICENSE` (CC BY 4.0) and `GOVERNANCE.md`. The API list responses and the CSV and NDJSON exports state the data licence. NOTICE lists the data and third-party dataset licences, and no longer names libraries the project stopped using.

### Changed
- `composer lint` is enforced in CI; the codebase was formatted once with Pint.
- Contributor docs: fork-first setup, `policy:import` after seeding, schema limits for new records, a worked data-only pull request, and `composer test` no longer times out. A policy record must cite at least one source.
- The verification badge says what it is: a factual check against the official source, not a legal review.
- Repository layout: the three point-in-time audit and implementation reports moved from the repository root to `docs/reports/`, so the root holds only the documents a newcomer reads (README, contributing, governance, security, code of conduct, changelog and the operations notes). References in the debt register and inside the reports follow.
- README: status badges for CI, the gate check, data validation and security; the quick start and the command table moved to the top, where someone cloning the repository looks first; a repository map naming what lives in each directory; security reporting given its own section.
- The operating company is named once on the About page, in `NOTICE` and in the structured data, rather than in the footer of every page, in the About FAQ and in the header of every email. The site footer keeps the maintainer, which is who a reader with a correction needs.
- The address on the automated external-data pull requests is the project's maintainer address rather than a personal mailbox, matching `CONTACT_EMAIL` and `/.well-known/security.txt`.
- `docs/reference/compliance-map.md` cited `CLAUDE.md`, a local editor-tooling file that is git-ignored and so is not in the repository. It now cites the public documents that carry the same rule (`engineering-standard.md`, `change-gates.md`, `CONTRIBUTING.md`).
- `docs/reference/change-gates.md` put "Accepted debt" between gate 4 and gate 5, so the binding document broke its own numbered run and the section read as part of Compliance. It now follows gate 5, matching `.github/PULL_REQUEST_TEMPLATE.md`, which always had it last. The misplacement is not cosmetic: this pull request's own gate check failed for a missing accepted-debt section.
- Debt #48 recorded: `JobRun::run()` calls `@set_time_limit(280)`, which caps the whole PHP process, not a request. The test suite shares one process, so once any test touches `JobRun` the cap applies to every test after it and a slower machine than CI's cannot finish the suite in one run. Not changed here, because the limit is load-bearing for the admin job pages; recorded with the fix that is wanted.
- A local scratch copy of the whole tree (`aipt-rewrite/`) was invisible to git but not to the tools: `composer lint` reported 79 style failures and `npm run lint` 134 errors, every one of them in that second copy, so neither check could be read locally. `.gitignore` now names it, `eslint.config.js` ignores it, and a `pint.json` excludes it. Both checks pass on the project's own files.
- Public design system: the primary action carries a shadow and lifts a pixel on hover; a linked figure reveals a brand rule along its top edge; the law → duty → control → evidence strip numbers its steps, so the sequence still reads on narrow screens where the joining arrows are hidden; the empty state gained a mark, a tighter hierarchy and a tinted ground; the standalone subscribe block is a tinted panel rather than another ruled section; and the homepage masthead sits on a wash that fades into the page.

### Fixed
- The host crontab and the container could both drive the recurring jobs, which would have sent every weekly digest and every daily alert twice. `deploy/cron-install.sh` now installs one `schedule:run` entry instead of three per-job ones, refuses to install at all while the container is scheduling, and removes the old entries when re-run; the container's scheduler can be turned off with `SCHEDULER=off`. The old per-job entries also called artisan directly, so their runs never reached the job log and the admin page reported "never run" while they were running.
- The README said a workflow deploys `main`. There is no such workflow and there never was one in this repository: pushing to `main` runs CI, and a person runs the deploy. It now says so and points at the runbook that applies to the current host.

### Added
- The maintainer is on the reviewer roster (`data/reviewers/bhaskar-bhatt.yaml`) with a declared interest in the related commercial platform. Nobody could mark a record verified before this: the data validator refuses a signature from a name that has not published a declaration, so the roster had to have somebody on it before the first verification could exist.
- Bulk verification in the review queue. A reviewer who has just read a jurisdiction's instruments end to end selects them, makes the attestation once and records the lot; each record still gets its own dated, named decision that survives re-import and exports to `data/`. The one-at-a-time form was the right shape for a single careful pass and the wrong shape for a reviewing session.

### Changed
- The admin now refuses to record a verification signed by a name that is not on the published roster, instead of storing a decision the data check would reject on export. The rule the site publishes and the rule the software enforces are the same rule.
- The homepage leads with the figure that is true of the whole corpus, instruments linked to an official source, and publishes the reviewer-confirmed count beneath it as a link to the verification policy. It had led with a bare zero, which described the review backlog rather than the work.

### Added
- **Jobs and schedule** in the admin area. Every recurring job (weekly digest, daily alerts, incident sync, external and policy imports, validation, freshness, coverage, domain-list refresh) is catalogued once, runs on its own timetable through Laravel's scheduler, and can be run from the browser. Each run is recorded with who or what started it, how long it took and how it ended, so the dashboard and the jobs page can say when a job last ran instead of an operator guessing. Jobs that send e-mail or rewrite data ask for the password first. The cron endpoints record their runs the same way, and the container runs the scheduler itself, so the platform no longer depends on GitHub Actions to do its recurring work.
- Eleven more settings an operator can change from the browser instead of a deploy: the official contact address, the X handle, an external newsletter URL, the Google Analytics and Cloudflare Analytics tokens, whether analytics wait for consent, whether drawn social cards are used, whether throwaway mailboxes are refused, the staleness threshold in days, and the Search Console and Bing verification tokens. Each overrides the environment value; an empty field keeps it.

### Changed
- The social preview image is the site rather than a poster: the real wordmark, the white page, navy type, the brand rule and a navy footer band with the domain, drawn at render time with live counts. It read as a generic dark slab with numbers that had been wrong for months.
- The comparison page opens on the table, not the picker. With a comparison on screen the jurisdiction picker folds behind one line; without one it opens compact, with quick-pick chips for the ten most-recorded jurisdictions and every region collapsed. It had filled a screen and a half before a reader saw a single row of data.

### Changed
- Positioning. The site now says what it does: "AI governance intelligence, from regulation to evidence" as the product line, "Track regulations. Map obligations. Operationalise controls. Prove compliance." beneath it, and "AI policy, verified at the source" kept as the trust line. The homepage chain names the verb at each step. The framework comparison says in its own words that it is not a ranking, and shows purpose and structure before any count.
- Controls can be corrected through the contribution form (with their own fields), verified and published from the admin review page, and are counted as an expected gap on any obligation that names none. The applicability screening now ends with the controls to build first. Open-data and reference docs list every control surface.

### Added
- Fifty-five obligations, taking the corpus from 57 to 112, each meeting the record gate: exact article, actor, applicability, effective date, evidence, framework references and at least one control. EU AI Act from 19 to 44 (Articles 16, 18 to 22, 25, 26(4) to (11), 50(2) to (5), 52 to 55, 86 and the Article 6(4) documentation duty); Colorado SB 24-205 from 4 to 10; Texas TRAIGA 7; NYC Local Law 144 5; Korea's AI Basic Act 9; California SB 53 from 2 to 5. All pending human verification; the reviewer flags are in the records' confidence levels and in the strategy note.
- **Controls**: the layer between a duty and its evidence. Twenty-six original control records (`data/controls/*.yaml`, schema `control.schema.json`) each state what an organisation operates, who owns it, how often it runs, the evidence it produces (from a new `evidence_type` taxonomy of 23 terms), the MIT AI Risk Repository subdomains it addresses and the standards clauses it corresponds to. Every one of the 57 published obligations now names the controls that `satisfy` or `support` it (131 links), the validator refuses a dangling reference, and the importer unpublishes rather than deletes a control that leaves the corpus. Pages: `/controls` (filterable by kind, with totals) and `/controls/{slug}`, which lists every duty the control serves grouped by jurisdiction, the evidence table, the risk areas with live incident counts, the clause references and a governance card. Obligation pages show the controls that meet them and an evidence table; policy and jurisdiction pages roll up the controls that meet their duties; framework pages count controls, evidence types, risk areas and incidents and list the controls that cite them. Machine forms: `/controls/{slug}.md`, `/api/v1/controls`, `/open-data/controls.csv|.ndjson`, `/sitemap-controls.xml`, a section in `llms-full.txt` and the OpenAPI description.
- **Audience pages** at `/for/{audience}`: ten cuts of the corpus by role (providers, deployers, general-purpose model providers, public sector), sector (healthcare, financial services, education) and use case (hiring, generative AI, biometrics), generated from the taxonomy terms every obligation carries. Each lists the recorded duties by jurisdiction, the controls that meet them sorted by how many they satisfy, the evidence to keep, upcoming dates, the latest changes and a governance card. A cut with fewer than five duties is served but not indexed or advertised in the sitemap.
- `/frameworks/compare`: the frameworks side by side (type, binding, certification, publisher, how many controls cite each) and a matrix that, for every category of legal duty, counts the controls meeting those duties that have a home in each framework. The reuse question answered from the recorded mappings rather than asserted in prose.
- Four registry entries for what a control may cite, each with a `kind` so a principle, a threat model and a certifiable standard stop sharing one badge: ISO/IEC 23894 and ISO/IEC 42005 (assessment methods), the OWASP Top 10 for LLM Applications and MITRE ATLAS (threat models, cited by entry and mitigation identifier).
- A "law → duty → control → evidence" strip on the homepage with live counts, each tile the page that lists what it counts; audience entry points on the homepage; a shared stat-tile component so every count on the site is set the same way; Controls and By-role links in the navigation and footer.
- `docs/modules/controls.md`, and a research note on the strategy behind all of this at `docs/strategy/ai-governance-intelligence.md`.

### Added
- An official mailbox, bhaskar@aipolicytracker.org, published everywhere a person or a machine looks for one: the About page and footer, `SECURITY.md`, the code of conduct and contributing guide, the issue-template chooser, the Composer and npm manifests, `llms.txt`, the OpenAPI description, the `Organization` structured data (as `email` and a `ContactPoint`) and a generated `/.well-known/security.txt` (RFC 9116) whose `Expires` field can never fall into the past. The address is one configuration value, `CONTACT_EMAIL`, so a fork changes it in one place.
- A page for every change-log entry at `/changes/{slug}`. A development that only existed as an anchor on a list could not be shared, cited or returned as an answer on its own; the page states what changed, what it means in practice, the official source, the review status and the related changes, is described as an `Article` dated by the event, and is what the RSS feed, the digest e-mail, the policy page's history, the Markdown context file and the changes sitemap now point at.
- "Cite this record" on every policy, jurisdiction, obligation and change page: the record's address, the date read and the official text it rests on, in the format the open-data page already publishes.
- A named reviewer, where one has verified the record, shown beside the verification line and published as `reviewedBy` in the page's structured data, with the organisation as `author`. Only a verified record names anybody.
- `rel="alternate"` links from each record page to its JSON and Markdown forms, and `X-Robots-Tag: noindex` with a `Link: rel="canonical"` header on those forms and on the CSV and NDJSON exports, so an index treats them as the page in another shape rather than as competing documents.
- `og:locale`, `twitter:site`, `rel="license"`, a web manifest, a 180px Apple touch icon, 192px and 512px icons, and a real `favicon.ico`: the file served at that address was zero bytes.
- Explicit `robots.txt` groups for the answer-engine and assistant crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended and the rest), each allowed on the same terms as every other reader, so a future change to the stance is a decision rather than an accident of the default.
- A regression suite for the search and answer-engine surfaces (`tests/Feature/SeoSurfacesTest.php`) and for the proxy-trust rule (`tests/Feature/TrustedProxiesTest.php`).

### Changed
- Titles are trimmed to 60 characters before the site name is appended, and a policy whose name does not fit the long pattern gets a shorter suffix rather than a truncated one. Two page types carried a literal ellipsis in the browser tab.
- Every page of an indexable listing (policies, obligations, changes) is indexable and canonical to itself. Obligations and changes sent `noindex` together with a canonical to page one, which the search guidelines call contradictory, and a filtered policy listing joined its page number with a second question mark.
- Framework pages route their questions through `Seo::withFaq()`, so the layout renders them where a reader can see them. They carried `FAQPage` markup for text that was on no page.
- A non-binding instrument is described to an answer engine as a `CreativeWork`, not as `Legislation`; a binding one's page node links to its `Legislation` node by identifier instead of carrying a second, thinner description. Record datasets state the official text they rest on, the place they apply to and the source file in the repository. The publisher logo is a raster with declared dimensions, which is what the guidance for one asks for.
- Sitemap dates are claimed only where they are known: static and editorial pages no longer carry the corpus import time, which moved `/privacy` on every deploy; filtered listings are dated by their own records and gated on the same rule the jurisdiction page applies to itself; guides are listed once instead of twice.
- `robots.txt` no longer blocks the API, which the open-data page publishes as a dataset distribution and which sets its own `noindex`, nor the search-result URLs, which are `noindex` and can only carry that tag if a crawler is allowed to read it. A stale rule for a path that has redirected for a year is gone.
- HTML responses are `private, no-cache, must-revalidate` rather than `no-store`. Both force revalidation on every use; `no-store` also barred the back/forward cache, so a reader pressing Back re-rendered the page they had just left.
- Both nginx configurations redirect `www.` to the apex and strip trailing slashes, so a page has one address; and the origin refuses a connection that did not arrive through the edge.
- `llms.txt` dates itself, links each jurisdiction and policy to its Markdown context file, and names the contact address.

### Security
- The visitor's address could be chosen by the visitor on the PHP-FPM deployment: nginx resolved the real address from the edge header and then passed the client's own `X-Forwarded-For` through to an application that trusted every proxy. That address keys every rate limit, the login limiter and the hashed addresses in the audit log. nginx now hands the application only the address it resolved, and which proxies may speak for a visitor is a configuration value, `TRUSTED_PROXIES`, read at request time and covered by a test.
- Anyone who knew an address could re-enrol it in the digest after it had unsubscribed, or rewrite its topics, from the public form without a confirmation. An active subscription is now left untouched by the form, an address that opted out starts over with a fresh double opt-in, and the reply is the same in every case so the form cannot be used to find out whether an address is subscribed.
- An authenticator code is accepted once. The step of the last accepted code is kept on the account and a code from that step or earlier is refused, so a code seen over a shoulder cannot be replayed inside the ninety-second window (RFC 6238 §5.2).
- Registration and password-reset requests are rate limited per caller. Both hash, resolve a mail domain or send mail on every request and had no limit but the broker's per-address one.
- One password rule for sign-up, reset and change. Sign-up asked for mixed case and a symbol while a reset accepted any eight characters, so the weakest path set the real minimum.
- Sign-out is a POST. A sign-out reachable by GET can be triggered by any page that embeds the URL as an image.
- The return target after unfollowing a record is honoured only when it is a path on this site; `//host` was accepted as one.
- An uploaded template file keeps its name only when the extension is one of the allowed set, so a text file uploaded as `something.php` is refused rather than stored under a name a web server might one day execute.
- Admin CSV exports prefix any cell that a spreadsheet would run as a formula; names, organisations and referrers are typed by readers.
- Hashed network addresses are keyed with the application secret. A plain SHA-256 of an IPv4 address is reversible in seconds by hashing every candidate.
- The contribution form accepts only `http` and `https` source URLs; the composer-lock workflow passes its input through the environment rather than into the command line; the container runs as `www-data`; the Inertia layout's icon stylesheet, blocked by the content security policy, is now allowed from its host.

### Fixed
- Two documents still credited Dependabot with keeping dependencies updated, which stopped being true when its configuration was removed. A security policy that claims a control the repository does not run is worse than one that claims nothing, so `SECURITY.md` and the compliance map now describe what actually happens: CodeQL on every pull request and weekly, dependency updates made through the release process, and `npm audit` and `composer audit` recorded in each change. Whether GitHub raises advisory alerts is named as a repository setting rather than asserted as a fact about the code.

### Fixed
- The image every page showed when it was shared had counts painted into it, and they had stopped being true: it read "117 jurisdictions · 182 instruments" against a corpus of 212 and 186. A number inside a PNG cannot be kept current, and nothing in the system could tell that it had drifted, which on a site whose argument is that its figures can be trusted is the worst place to carry a stale one. Preview cards are now drawn from the record: a policy card shows its real title, jurisdiction, instrument type, whether it binds anybody, and whether a person has verified it, and the site-wide card reads its figures from the database at render time. Each card's URL carries a token derived from the record, because a platform caches a preview against its URL and will otherwise keep showing an old title after an edit. Cards are drawn once and kept on disk. Where a host has no TrueType font the site serves exactly the image it served before rather than a broken one, and `php artisan social:doctor` reports which face resolved on a given host.

### Added
- Two sitemap sections, `incidents` and `risks`. Roughly 1,700 recorded AI incident pages and 2,500 MIT AI Risk Repository entries were reachable, indexable and listed in no sitemap at all, which accounts for most of what Search Console reported as discovered and not indexed; the index went from 593 URLs to 4,756, each with a last-modified date from a real record timestamp. Both sections read the table in lazy chunks so the largest sitemaps do not have to be held in memory whole.
- The two dataset descriptions that were missing, on the AI risk overview and the latest-incidents page. A dataset a machine cannot summarise is one it will not cite, and Search Console reports the absence as a missing required field. A test now asserts every dataset the site publishes states its name, description, URL and licence.

### Added
- Structured data on every page rather than some of them. `Organization` and `WebSite` were emitted on the homepage alone, while every inner page carried `isPartOf` and `publisher` references to identifiers that resolved to nothing when that page was fetched on its own, which is exactly how an answer engine reads a record page. Both nodes now appear everywhere, and every page describes itself with exactly one page node carrying an identifier, its language, its publisher and a link to its own breadcrumb trail. Thirteen page types that carried nothing but a breadcrumb trail now say what they are. A record page also publishes a `Dataset` naming the JSON and Markdown files that actually serve it under the corpus licence, so a client is told the page is a structured record at a stable URL rather than prose about one. Pages that enumerate records carry a list whose stated count matches what it lists; the change log and the deadline calendar name their feed and calendar distributions. What the publisher claims to cover is read from the corpus: `areaServed` names only regions with published jurisdictions and `knowsAbout` comes from the taxonomy, with a test comparing the claim against the database, because a global remit the records do not support would be the same overclaim in machine-readable form.

### Fixed
- A retried billing webhook was answered with a 500 instead of an acknowledgement. Idempotency is the unique index on the event id, so the duplicate path runs after a failed insert, and PostgreSQL refuses every later statement in a transaction once one has failed. The existence check inside the error handler was itself refused. Providers retry on a non-2xx, so the failure encouraged more of the same. The insert now runs in its own savepoint, and a test asserts the retry is acknowledged while a transaction is open.

### Added
- CI runs the full PHP suite a second time against `postgres:16`, the engine production uses, after running the migrations and the policy import exactly as the deploy does. The suite had only ever run on in-memory SQLite, and the two disagree in ways that reached production silently: an unmatched double-quoted identifier is a string literal in SQLite and an error in PostgreSQL, which took `/reviewers` down, and a constraint violation poisons a transaction in PostgreSQL but not in SQLite, which is the webhook bug above. The new job found that one on its first run. Closes technical debt #15.

### Added
- A privacy policy at `/privacy` and terms of use at `/terms`, served by the application and linked from the footer of every page, from the sign-up form and from the sitemap. The sign-up checkbox asked readers to accept terms and a privacy policy that resolved to the About page whenever two optional environment variables were unset, which is how the site shipped, and the download gate recorded that acceptance. The privacy page is built from the real schema rather than boilerplate: it names the account columns that exist, states that page counts carry no identifier, that analytics do not load before consent, that no card data touches this application, and that the sign-up IP address is the one field kept in full rather than hashed, which is recorded as technical debt instead of glossed. Two facts that cannot be derived from the codebase, the address for data requests and the governing law, are omitted rather than invented, and appear as soon as they are configured. An externally hosted policy still overrides both pages.

### Added
- Registration, the newsletter, a change of address on the profile and the contact address on a contribution now refuse temporary and disposable mailboxes. Work and personal addresses are equally welcome — the rule exists because an alert sent to an inbox that expires in ten minutes never reaches anybody, and because subscriber and download counts should describe people who can be reached. Four checks in order: a trusted-provider list that wins outright so no heuristic can refuse a long-held personal mailbox, RFC 2606 reserved names, a curated list of throwaway domains, and where the domain's mail actually goes. The last is the one that earns its keep: a throwaway service rotates thousands of domains through a handful of its own exchangers, so blocking the exchanger stops the domains no list has catalogued. Measured against 400 known throwaway domains, 62.5% are refused and only 0.2% of that came from the static list; 50 out of 50 legitimate work, regulator, university, hospital and freemail domains were accepted. Shared infrastructure that real organisations use (Cloudflare Email Routing, Google Workspace, Microsoft 365, Amazon SES, Mailgun, ImprovMX, Forward Email, Zoho) is deliberately excluded, and alias services such as SimpleLogin and Hide My Email are accepted because they forward to a mailbox somebody reads. Anything the resolver cannot answer is accepted, and a control lookup means a DNS outage cannot become a sign-up outage. Sign-in and password reset are deliberately not policed, so no existing account is stranded. `php artisan email:check` explains any refusal down to the list entry; `php artisan email:domains-refresh` loads an operator-chosen list into an untracked overlay.

### Changed
- Choosing jurisdictions on the comparison page and the applicability check no longer means scanning 212 checkboxes in one flat list. Both now use a shared picker that groups them by region behind collapsible headings, shows beside each name how many instruments are recorded for it (a dash where nothing AI-specific exists yet, so a reader does not pick a jurisdiction and then find the comparison empty), and adds a type-ahead filter, a live count and removable chips for the current selection. The comparison picker states its four-jurisdiction limit rather than silently discarding the rest. Every checkbox still submits without JavaScript; the search box and chips are rendered hidden and revealed by the script, so nothing inert is ever on screen.

### Added
- Structured data for answer engines: a binding instrument is now described as schema.org `Legislation` — its jurisdiction, issuing body, type, adoption and application dates, official source, and `legislationLegalForce` stating in a controlled vocabulary whether it is actually in force, which is the question most often got wrong. Non-binding instruments are deliberately never described as legislation, and a proposal's legal force is left unstated rather than asserted as "not in force". Guides that lay out ordered steps are described as `HowTo`.
- Interlinks to surfaces that shipped but were reachable from nowhere: the review-status badge on every policy and jurisdiction now links to the verification policy, every policy, jurisdiction and obligation offers its Markdown context file beside the record, and a jurisdiction's deadlines section links its own calendar feed.

### Added
- Admin security: every admin session now proves a time-based one-time code as well as a password, enrolment is mandatory before any backend route answers, and the actions that change secrets or remove things (settings save, billing provisioning, tool and file deletion, recovery-code regeneration) require a freshly confirmed password. The authenticator secret and the eight single-use recovery codes are encrypted at rest and never serialised; the enrolment page shows the key as text and an `otpauth://` link rather than sending it to a third-party QR service. `php artisan admin:two-factor-reset` is the operator escape hatch for a lost device. A new audit log records every state-changing admin request — who, route, record, status, hashed IP, never request bodies — and is published at Admin → Audit log. RFC 6238 is implemented in-repo with the specification's own test vectors in the suite, rather than adding a dependency.

### Changed
- Free-tool downloads now confirm the reader's name and organisation at the point of download and keep both on the account, and registration requires an organisation when the reader arrived at a template's download gate. A template download is a lead, and sign-up had left the organisation optional, so download records existed that nobody could follow up. Admin → Downloads gains a second export with one row per download (date, tool, version, name, email, organisation, sign-up source, consent, referrer) beside the existing per-user export.

### Fixed
- `/reviewers` returned 500 in production. The reviewer roster counted verifications across four record kinds, but `obligations` carries no `reviewed_by` column: obligations record a review status and date and no reviewer, because an obligation's verification belongs to the instrument it was read out of. SQLite reads an unmatched double-quoted identifier as a string literal, so the bad query matched every row and the test suite stayed green; PostgreSQL rejects it (debt #15). The roster now attributes only the kinds that carry a reviewer, still counts every published record in the corpus total, says so on the page, and a test asserts every attributed kind really has the column.
- Deploys had been aborting since the verification policy shipped. `config/verification.php` and `config/completeness.php` held closures, `php artisan config:cache` cannot serialize those, and `azure/startup.sh` ran it under `set -e` — so `policy:import`, `external:import` and the seeders never ran, while the deploy workflow still reported success because it only ships the archive. The rules and checks moved to `App\Services\Verification\VerificationRuleset` and `App\Services\Completeness\CompletenessChecks`; config keeps only serializable values. CI now builds the three production caches, a test fails on any closure left in config, and the startup script logs a cache failure loudly instead of taking the site down.

### Added
- Error pages for 403, 419, 429, 500 and 503, joining the existing 404. The 500 and 503 pages are deliberately self-contained — inline styles, no database, no shared layout, no named routes — because a page that depends on the application cannot explain the application being broken, which is why a real fault showed the words "Server Error" on a white page. Both tell the reader the same records remain available as open data, and tests assert the 500 page renders without a single database query.

### Added
- Machine-readable surfaces: every published record is now also served as a Markdown context file with a provenance block (`/policies/{slug}.md` and the same for jurisdictions, obligations and changes); the whole corpus exports as CSV and newline-delimited JSON at `/open-data/{dataset}.{csv,ndjson}`; `/open-data/health.json` reports freshness, completeness and review standing in one document; and the JSON Schemas are finally served at `/schema/{name}.schema.json`, the URL each one's `$id` had always claimed and nothing answered. A dependency-free Model Context Protocol server in `agent/` lets an assistant query all of it, offering the corpus health check first. Every artefact carries its own source, review status and last-confirmed date, because these formats are the ones most likely to be quoted without the page around them. `npm test` now runs the agent server's protocol tests instead of an echo. See `docs/reference/machine-readable-surfaces.md`.
- Reviewer roster: `/reviewers` names the people who verify records, publishes every interest each has declared, and shows how many records each has actually verified rather than what they are listed as covering. The roster lives in `data/reviewers` so a declaration and every later change to one is reviewed in a pull request; `policy:validate` now rejects a record whose `reviewed_by` is not a published reviewer, and a roster entry without a declaration of interest ("None declared." counts, an omission does not). While no record has been verified the page says so rather than implying review has happened. See `docs/reference/reviewer-roster.md`.
- Coverage, gaps and corrections: `/coverage` publishes what a record must carry to be checkable (`config/completeness.php`) and how many published records carry it; `/gaps` turns every shortfall into an open queue, required first, filterable by record type and by check, where each row links to the correction form with the record and field already selected; `/corrections` publishes what readers reported and what was decided, refusals included, without the submitter's identity or any text the site never moderated. `php artisan policy:coverage` runs the same report in the data workflow and fails when required gaps exceed the budget, which launched at zero against the full corpus. See `docs/reference/completeness-policy.md`.
- Deadline calendar: `/calendar` publishes a subscribable iCalendar feed of dated application deadlines, whole-corpus or per jurisdiction, with reminders 30 and 7 days ahead and the record's review status and confidence in every entry. Only scheduled dates recorded to an exact day are published; a date held as a month, a year or still undecided is left out rather than guessed into a day.
- Verification policy: every record type now has a published maximum age before its facts must be confirmed again, with an owner per queue (`config/verification.php`). `/verification` publishes the rules, the live counts and the longest overdue records, and `php artisan policy:freshness` runs the same report in the data workflow and fails when critical breaches exceed the agreed budget. A record that has never been confirmed counts as overdue rather than current. The budget is a ratchet set to the backlog that existed at launch and may only be lowered (debt #26). See `docs/reference/verification-policy.md`.

### Fixed
- Alerts: the module documentation claimed a saved profile contributed no application dates, while the code already included the deadlines of the instruments in the profile's scope. The documentation now matches the behaviour, a test proves that an account which follows nothing but saved a profile is still told when a date in that scope approaches, and an unused helper that could have implied obligations the email never shows was removed.

### Added
- Social cards declare the preview image's size and alt text (`og:image:width`, `og:image:height`, `og:image:alt`, `twitter:image:alt`), so Slack, LinkedIn and X reserve the correct box before the file loads and screen readers get a description. Dimensions are configurable (`SITE_OG_IMAGE_WIDTH`, `SITE_OG_IMAGE_HEIGHT`) and emitted only for the default image; a test asserts they match the file actually served.

### Changed
- Positioning: "AIPolicyTracker is the regulatory intelligence layer for AI governance. Monitor source-backed AI policy changes, map obligations to real AI systems, and turn regulatory requirements into practical governance actions." applied to the home page eyebrow and meta description, footer, about page, email header, `llms.txt`, Organization structured data and README.

### Changed
- Repository hygiene: the implementation report no longer names the working branch used to produce it; pull request descriptions carry no tooling attribution.

### Added
- Change-impact alerts: a Pro account can save an applicability screening as a named profile from the free check, and the daily alert then flags changes that fall in that profile's scope with "May affect: <name>", naming the system in the subject line when one profile is affected. The screening logic moved into `ApplicabilityScreener` so the free tool and the paid alert use one implementation; profiles hold answers only, never customer systems, and every alert repeats that relevance is not a legal determination. Documented in `docs/modules/alerts.md`; debt #25 records that verification depth, not the engine, is what limits the claim.
- Billing diagnosis: Admin → Billing → "Can we sell right now?" asks the provider to open a checkout session for the first purchasable plan and prints its answer (nothing is charged, no attempt row is written); when a customer's checkout is refused, admins also see the provider's response inline on the pricing page, while customers keep the neutral message.

### Changed
- Pricing now lists only what Pro delivers: following records, the daily alert email and application-date reminders. The unbacked "full change history" and "higher API quota" lines, and their unread entitlements, are removed from `config/billing.php`, the pricing page, the account return page, the provider product description and the README (debt #22 closed; remaining plan gaps tracked as #24).

### Added
- Alerts (first Pro capability): "Follow for daily alerts" on every policy, jurisdiction and obligation page for Pro accounts (free and guest visitors see "Follow with Pro"), a `/following` page to manage follows, and `alerts:send`, run daily at 06:30 UTC through `POST /cron/alerts`, which emails each Pro follower once a day when a followed record has a new published change or an application date is 30, 7 or 1 days away. Idempotent per user and day, quiet when nothing changed. Documented in `docs/modules/alerts.md`.
- Billing: Admin → Billing lists recent checkout attempts with the provider's response when a session could not be created, so a refused checkout is diagnosable without server logs.
- Billing: one-click provider setup from Admin → Billing ("Provision webhook and products" registers the webhook endpoint for every subscription and payment event, creates the Pro monthly and annual products, and stores the signing secret and product ids encrypted; idempotent) and a Checkout on/off switch in Admin → Settings that overrides `BILLING_ENABLED`, so test-mode selling can start without a redeploy.

### Changed
- Positioning: "AIPolicyTracker is the regulatory intelligence layer for AI governance. Monitor source-backed AI policy changes, map obligations to real AI systems, and turn regulatory requirements into practical governance actions." applied to the home page eyebrow and meta description, footer, about page, email header, `llms.txt`, Organization structured data and README.

### Added
- Billing foundation with Dodo Payments as merchant of record: `/pricing` (Free and Pro monthly/annual), hosted checkout hand-off, return page that waits for confirmation, customer portal hand-off, a signature-verified webhook endpoint that mirrors subscriptions locally (idempotent by webhook id, stale-event guard), entitlements (`User::entitled()`, `subscribed` middleware), a plan card on the account page, a payment-failed email with a grace period, Dodo keys in Admin → Settings (encrypted) and an Admin → Billing page with a provider price check. Ships disabled (`BILLING_ENABLED=false`); see `docs/modules/billing.md`.

### Changed
- Governance docs: a binding engineering standard (`docs/reference/engineering-standard.md`: inspect before coding, Analyze → Design → Implement → Test → Validate → Document, definition of done, verification language) referenced from `CLAUDE.md`, `CONTRIBUTING.md`, the change gates, the compliance map and the pull request template; stale "four gates" wording corrected to five; `IMPLEMENTATION_REPORT.md` marked as a point-in-time record of #16.
- Resilience of external data: each live sync also merges its rows into a local snapshot on the persistent private disk, and `external:import` reads that snapshot after the repository files, so a rebuilt database recovers every live-synced record without the AI Incident Database API; pages never call AIID at request time, and a failed sync leaves the stored data untouched.

### Fixed
- Tool downloads returned 404 after a clean deploy because the private storage folder was replaced while the file rows survived. The `local` disk root is now configurable (`FILESYSTEM_LOCAL_ROOT`, set to persistent storage in production), the seeder restores missing seeded files at startup, and the download route falls back to the bundled copy.

### Added
- Live sync of AI incidents from the AI Incident Database API (`external:sync-aiid-api`): new and modified records with their alleged deployers, developers and harmed parties (with entity identifiers), implicated systems, editor notes, related incidents, MIT and CSET classifications and report metadata are pulled every six hours through the cron trigger, on demand from Admin → External data, and merged into the reviewed JSON by the weekly refresh. `/ai-risk/incidents` lists the latest recorded incidents from the read model with the sync time, each linking to its profile; profiles show editor notes, entity links, implicated systems, related incidents and sync dates. Re-imports of the weekly snapshot never delete or downgrade live-synced rows.

### Changed
- Open-source hygiene: the edge Worker reads its origin host from an `ORIGIN_HOST` variable instead of the repository; stale hosting examples removed; CODEOWNERS covers data, compliance docs, workflows and middleware; new required **Gate check** workflow fails pull requests that do not document the five gates and accepted debt; the deploy workflow purges the Cloudflare cache when the zone secrets are configured.

### Added
- Five global tool templates: Global AI Regulatory Applicability Matrix, AI Vendor Due Diligence Questionnaire, AI System Technical Documentation Template (Annex IV structure), AI Impact Assessment Template (FRIA and HUDERIA aligned) and AI Policy Statement Template, in XLSX, CSV, Markdown and DOCX; the library now holds ten tools.
- Charts: hover tooltips on every bar, milestone and treemap block; a relative colour scale (below half of peak, half to three-quarters, top quarter) on the incident timeline and the harmed-party and deployer charts with a legend; Download SVG, PNG and CSV on every chart (with source and date stamped).
- Admin Tool library: step-by-step "How to add a tool and upload its files" panel; matching section in the module documentation.

### Added
- AI risk hub rebuilt as an evidenced narrative: headline totals with 12-month growth, an incident timeline annotated with policy milestones (each bar opens that year's incidents), the shift in domain shares since 2019, most-named harmed parties and deployers, assessed harm levels, a "where harm is recorded versus where rules exist" coverage table (incidents by country against tracked and binding instruments), instruments per domain, and next steps by persona (CISO, researcher, policymaker or diplomat, civil society and journalists).
- Home page "Start from your job" entry points; policy pages show the AI risk domains an instrument addresses with recorded incident counts; the weekly digest includes the week's recorded AI incidents.
- `docs/reference/personas-and-jobs.md`: personas, pain points and the product's single USP (harm → rule → action, sourced at every step).

### Added
- Complete country coverage: 95 further jurisdictions so every UN member state (plus Kosovo, Palestine, Taiwan and Hong Kong) has a record. Where no AI-specific instrument exists the record says so and points to the government portal and a labelled secondary source; four instruments added (El Salvador's AI promotion law, the Holy See's Rome Call for AI Ethics, Azerbaijan's AI Strategy 2025–2028, Tajikistan's AI strategy to 2040). Totals: 212 jurisdictions, 186 instruments.

### Added
- MIT AI Risk drilldown: subdomain profile pages (`/ai-risk/{domain}/{subdomain}`) with the repository's definition, causal entity, intent, timing and level breakdowns, incidents per year, the frameworks that cite the subdomain, paginated risk entries and recent incidents; domain→subdomain treemaps on the AI risk hub sized by risk entries and by recorded incidents; subdomain pages in the sitemap.

### Added
- Incident profiles list the news reports the AI Incident Database catalogues for that incident (6,434 reports across 1,647 incidents): date, title linked to the publisher, source, authors and the AIID report number. Metadata only; synced weekly from the AIID backup by `external:sync-aiid-reports`.

### Changed
- Guides framework and topic filters are compact dropdown buttons with a checkbox panel (showing the selected count) instead of native multi-select lists; selections apply when the panel closes.

### Added
- Durable human verification: the admin review queue lists unverified and low-confidence instruments first with a link to the official source; saving "verified" requires confirming the source was opened and records reviewer, date and confidence. Decisions are re-applied after every import and `policy:export-verifications` writes them back into the YAML records.

### Changed
- Removed the 24 estimated adoption dates added in the coverage expansion; those records now show no date until a reviewer sources one.
- Download-links email after every free-tool download: 24-hour personal links to each format, the related guide and the next-step tool.
- DOCX versions of the EU AI Act Readiness Checklist, AI Incident Response Checklist and 30-Day Starter Plan.
- Anonymous daily page-view counts for the guides library and tool pages (no cookies, IPs or user ids) feeding a 30-day funnel on the admin Guides and downloads page: library views, tool views, download clicks, sign-ups from a gate, downloads, second-tool users.
- "Report a correction" on AI incident and MIT risk profile pages, prefilled with the record's fields and its source link.
- Account page (`/profile`) in the site theme: profile and organisation, password change, download history with fresh links, consent status, resend verification, account deletion.

### Changed
- Forgot-password, reset-password, verify-email and confirm-password pages are server-rendered in the site theme; the legacy React versions are no longer served.
- Guide titles and summaries rewritten around the reader's outcome (who it is for, what they get) for the four featured guides.
- Open Graph image regenerated in the navy brand (1200×630).
- Technical-debt register: entries #3, #8, #9, #10 and #12 closed (legacy map, CMS and log mailer no longer exist).

### Changed
- Startup clears all Laravel caches (`optimize:clear`) before rebuilding them, so no configuration, route, view or application cache from the previous release survives a deploy.

### Added
- Admin → Tool library: create, edit, publish/draft/archive free tools; upload, activate, deactivate and remove their files (XLSX, CSV, Markdown, PDF, DOCX, JSON, text). Tools moved from configuration to `tools` and `tool_files` tables, seeded once from the previous configuration; downloads now record the served file and files count downloads.

### Changed
- Guides filters are a compact bar: search, content-type and access dropdowns, multi-select framework and topic lists, with active filters shown as removable chips.
### Fixed
- Scheduled uptime self-heal: every 15 minutes the health endpoint is probed and, if it fails twice, the app is restarted once through the Kudu API (skipped while a deploy is running). Covers the container stalls seen after deploys on the free tier.

### Security
- Nonce-based Content-Security-Policy and Cross-Origin-Opener-Policy on every response; PHP version header removed and nginx server tokens hidden. Fifth change gate (Security / VAPT) added to the process with the first assessment recorded in `docs/reference/vapt-2026-09-11.md`.

### Security
- Removed 15 unused JavaScript packages left from the legacy map site (amCharts, CKEditor, MUI, Emotion, react-select, react-slick, react-toastify, react-dropzone, DOMPurify) and applied `npm audit fix`; production dependencies now audit clean.

### Added
- Guides page now has search and filter chips (content type, framework, topic, access) and a "Free tools and templates" section: AI System Inventory Template, AI Risk Register Template, EU AI Act Readiness Checklist, AI Incident Response Checklist and AI Governance 30-Day Starter Plan (XLSX, CSV, Markdown). Each tool page previews every field, explains purpose and use, maps to recorded policies and guides, and gates the download behind a free account with explicit licence acceptance; files are served through signed 30-minute links to the requesting account.
- Free-account registration is a server-rendered page (name, email, optional organisation, password, terms; marketing updates opt-in and unticked). Admin "Guides and downloads" page: user and download metrics, most downloaded tools, sign-up sources, download activity, registered users and CSV export; dashboard tiles for users and downloads.

### Fixed
- AI incidents page no longer scrolls horizontally; grid children and charts are constrained to the viewport.
### Changed
- README rewritten around what readers can do, with architecture, data-flow and free-tools diagrams (`docs/diagrams/`); hosting and rate-limit specifics moved out of the public README. Dependabot configuration removed; dependency updates are handled through the release process.

### Fixed
- Legacy record URLs (`/news/{id}`, `/aipolicytracker/single-view/{id}`) that Google still crawls now redirect permanently to the change log and policy explorer instead of returning 404 or 5xx.

### Fixed
- Deploys now clean the target folder before unpacking the release, so files deleted or moved in the repository no longer linger on the server (stale duplicates had blocked `policy:import`). When the import fails at startup, the validation errors are printed to the container log.

### Added
- Single-incident profiles (`/ai-risk/incidents/{id}`) with every stored field, alleged deployer/developer/harmed parties, MIT taxonomy classification, similar incidents, other incidents involving the same deployer, the MIT risk entries describing the same failure mode, a link to the incident's news reports on the AI Incident Database, Save button, JSON export and Article structured data.
- Single-risk profiles (`/ai-risk/risks/{ev_id}`) for each MIT AI Risk Repository entry with the subdomain definition, matching real-world incidents, how other frameworks describe the same risk and the paper's other entries. Browse pages, the home page and domain pages now link to the profiles.
- Maintainer links (website, X, LinkedIn, GitHub) on the About page and in the footer.

### Changed
- Admin sign-in is a server-rendered page in the admin theme with a show/hide password control; the legacy React login page with outdated feature copy and a register link is no longer served. Admin layout uses a sticky sidebar and full-height content area.
- Correction form prefilled from the record: opening "Report a correction" on a policy, jurisdiction, obligation or change shows the record, its official source, a field picker with the value currently displayed, and a "correct value" box. The captured context (field, shown value, proposed value, record URL and content version) is stored with the submission and shown in the admin queue and alert email.
- `/subscribe` page with a jurisdiction picker grouped by region, subscriber count and recent changes; "Subscribe" and "Saved" links in the header, mobile menu and footer.
- "Follow" box on every policy page: subscribers can receive changes for one instrument only (policy slugs are now valid digest topics).
- Saved records: a per-browser reading list (`/saved`) with Save buttons on policy, jurisdiction and obligation pages, count badge in the header, and copy as Markdown or JSON.

### Fixed
- `/dashboard` redirected to `/map`, which redirected again; it now goes straight to the home page.
- Branded email layout for every message (confirmation, weekly digest, submission alerts and the admin test mail): centred logo, tagline, organisation, "Follow us on social" icons and copyright footer. Official LinkedIn, X, Facebook and Instagram profiles are configured once in `config/aipolicytracker.php` and shown in the website footer, the emails and the Organization structured data.
- Global coverage: 78 new jurisdictions (117 total) and 104 new policy instruments (182 total) with 26 dated change events, covering every EU member state, non-EU Europe (Norway, Iceland, Switzerland, Serbia, Ukraine, Türkiye, Russia), the Council of Europe Framework Convention, international instruments (OECD AI Principles, UNESCO Recommendation, UN General Assembly resolution, G7 Hiroshima code, Bletchley Declaration, ISO/IEC 42001), ASEAN guides, US states (Texas, Utah, Illinois, New York, Tennessee), Canadian provinces (Quebec, Ontario), Central and South America, the Caribbean, Asia-Pacific (New Zealand, Hong Kong, Taiwan, Sri Lanka, Kazakhstan, Uzbekistan, Mongolia, Cambodia), the Middle East (Israel, Jordan, Kuwait, Oman, Lebanon) and Africa (Morocco, Tunisia, Algeria, Ethiopia, Senegal, Mauritius, Benin, Côte d'Ivoire, Uganda, Zambia, Zimbabwe, Sierra Leone, Tanzania, Namibia). Every record links an official source and enters as `pending_review` (debt #18).

### Changed
- Region labels normalised (`Americas`/`Northern America`, `Asia`/`Western Asia`, `South-East Asia`) so jurisdiction grouping and the subscribe page read consistently; US state policy files moved under their own jurisdiction directories.

### Changed
- Deploy workflow waits for the health endpoint and restarts the app once through the Kudu API if the platform stops the container after a deploy.

### Fixed
- `external:import` failed on PostgreSQL when the MIT database contained duplicate `Ev_ID` values in one upsert batch; duplicates are now suffixed deterministically and the import is idempotent. Startup data steps no longer take the container down on failure.

### Changed
- Space Grotesk (with Space Mono for numerals) is now the typeface across the public site, admin and emails.
- HTML responses are served with no-store cache headers so every page reflects the latest import; API, exports, feeds and sitemaps keep their own caching.
- Admins receive an email for every public submission (correction, source, policy, reviewer application) linking to the review queue.
- Home page shows the latest AI incidents with the snapshot date; research pages carry Dataset structured data with CSV/JSON distributions and FAQ schema for search and answer engines.

### Added
- Researcher tooling on AI risk: the full AI Incident Database (1,663 incidents, metadata only) and MIT AI Risk Repository database (2,500 risk entries from 74 frameworks) imported into read-model tables; browse pages with filters (year, domain, subdomain, entity, intent, timing, country, sector, harm level, keyword), CSV/JSON exports that carry licence and citation, a frameworks page, causal entity × intent matrix and stacked domain-by-year charts, and per-domain risk/incident counts and recent incidents.

### Removed
- Legacy map site and admin CRUD (React/Inertia pages, controllers, models, seeders, the `ai_policies.json` sample dataset and ten database tables). `/map`, `/news`, `/timeline` and `/bookmarks` now redirect permanently to the structured pages; the change log and topic-based digest subscriptions replace news, timeline and bookmarks. Auth, profile and registration are unchanged.

### Changed
- Review queue moved into the shared admin layout with a jurisdictions publish table.
- Legacy map site (`/map`, `/news`, `/timeline`, `/bookmarks`) and its admin CRUD are disabled by default (`LEGACY_MAP_ENABLED=false`); old URLs redirect to the new pages. Audit in `docs/reference/admin-audit.md`.

### Added
- Weekly email digest: double opt-in subscribe form (home, change log, jurisdiction pages), branded confirmation and digest emails with one-click unsubscribe, `digest:send` command and a weekly workflow trigger secured by a token.
- New admin (Blade) at `/backend/dashboard`: live counts from the structured model, submissions and feedback with review decisions, subscribers (export CSV, re-send confirmation, delete), external-data status, and a Settings page where the Resend API key, mail transport and cron token are stored encrypted and applied without redeploying. Legacy React admin remains under "Legacy (map data)".

### Fixed
- Legacy login, register and verify-email pages now use the navy primary and open "Go to home" as a full page load instead of an Inertia visit (which rendered the server-side home page inside a frame).

### Changed
- About page rewritten with an original structure (why it exists, how to use it, how records are made, datasets and research references, team, open-by-default, reviewers, contact) plus FAQ structured data; Organization structured data now carries the founder (bhaskar.com.np) and parent organisation.

### Changed
- Record verification wording: unverified records now read "Source-linked · checked <date>" instead of "Human verification pending"; the amber banner on policy pages was removed (review status stays in the data, API and llms-full output).
- Footer no longer lists llms.txt; it remains linked from the Open data page and served at /llms.txt.

### Added
- AI risk section: the seven MIT AI Risk Repository domains and 24 subdomains (CC BY 4.0) with incident counts, per-domain pages linking to related policies, and an AI incidents summary page (AI Incident Database, CC BY-SA 4.0) with yearly, domain, sector, country and harm-level views and links to each incident record. Data lives in `data/external/` and is refreshed weekly by `external:sync-aiid` / `external:sync-mit-risk` through a pull request.

### Added
- Structured records bridged from the source-backed legacy dataset: 29 new jurisdictions, 60 policy instruments and 96 dated change events (all `pending_review`, official source on every record), bringing the public site to 39 jurisdictions and 78 instruments.

### Changed
- Public site brand refresh: official logo lockups (light and dark), SVG favicon from the mark, navy `#002147` primary with semantic brand and state colour tokens, Source Serif 4 / IBM Plex typography, editorial front page (lead changes, upcoming dates, jurisdiction rows) replacing stat tiles and card grids, navy footer with attribution.

### Fixed
- Change log pages and the changes sitemap failed on PostgreSQL because year grouping used `substr()` on a date column; years are now derived portably.
- Production startup: `symfony/yaml` declared as a runtime dependency and caches rebuilt before data steps.

### Added
- Policy-intelligence data foundation: `data/` YAML records with JSON Schema, taxonomies, and `policy:validate`, `policy:import`, `policy:export` commands; new tables for jurisdictions, policy instruments, versions, sections, obligations, applicability rules, deadlines, enforcement events, procurement rules, framework mappings, evidence artifacts, change events, source documents, contributor submissions and reviewer decisions.
- Source-backed seed records for the EU, UK, US (federal, Colorado, California), India, Nepal, Singapore, Australia and the UAE, all marked `pending_review` until human verification.
- Server-rendered public site: home, `/policies`, `/policies/{slug}` (+ `.json`), `/jurisdictions`, `/jurisdictions/{slug}`, `/obligations`, `/obligations/{slug}`, `/compare` (+ curated pages), `/changes` (+ yearly archives and RSS), `/tools/applicability-check`, `/open-data`, `/methodology`, `/about`, `/contribute`, `/guides/*` and editorial landing pages.
- Read-only public API under `/api/v1` with OpenAPI document at `/openapi.json`.
- Technical SEO: per-page metadata, canonicals and noindex rules, sitemap index with six child sitemaps, updated `robots.txt`, JSON-LD structured data, `llms.txt` / `llms-full.txt`, branded 404, legacy redirects.
- Admin review queue and publish/unpublish controls at `/backend/review`.
- Consent-gated analytics hooks and event tracking; Search Console / Bing verification placeholders.
- `PRODUCT_SEO_AEO_AUDIT.md`, `SEO_OPERATIONS.md`, `CONTENT_OPERATIONS.md`, `DATA_UPDATE_OPERATIONS.md`.

### Changed
- The homepage is now the policy-intelligence site; the legacy map dashboard moved to `/map` (noindex). The client-rendered legacy/admin shell is `noindex`.
- `php artisan migrate --seed` also imports the structured `data/` records after the legacy map dataset.
- Deployment scripts run `policy:import` after migrations.
- Replaced the fictional sample policies with a source-backed dataset (`database/data/ai_policies.json`) covering the EU, UK, US, Canada, Australia, Japan, South Korea, China, South Asia, ASEAN, Africa, the GCC, and Latin America; every entry cites an official source and access date. The seeder is now idempotent and `policies:purge-sample` removes the old sample rows on deployment.
- Binding four-role change gates (`docs/reference/change-gates.md`), technical-debt register, compliance map, module documentation for every existing module, data-integrity test proving all interlinks, and `CLAUDE.md` project rules; PR template restructured around the gates.
- Why comments on every migration.
- Licence changed from MIT to Apache License 2.0 (with `NOTICE`).

### Added
- CodeQL analysis, Dependabot configuration, CODEOWNERS, and private vulnerability reporting.
- Cloudflare Worker (`cloudflare/worker.js`) that fronts the public domain.
- Azure App Service startup/nginx configuration and GitHub Actions deploy workflow.
- Production `Dockerfile`, entrypoint, `.dockerignore`, and optional `fly.toml`; README deployment notes for containers and Cloudflare.

## [1.0.0] - 2026-09-10

First public open-source release.

### Added
- MIT licence, README, contribution guide, code of conduct, security policy, and source attribution policy.
- GitHub issue templates (bug, policy data correction, new jurisdiction/source, feature), pull request template, and CI workflow (PHP lint/tests, JS lint/build).
- `config/aipolicytracker.php` with environment-driven admin list, public links, contact addresses, analytics ID, and page-size limits.
- `SecurityHeaders` middleware and a restrictive `config/cors.php`.
- ESLint 9 configuration and `npm run lint`.
- Admin access feature tests.
- Migration dropping the legacy `user_infos.password` column.

### Changed
- Admin authorisation now uses `ADMIN_EMAILS` instead of hard-coded addresses.
- Header/footer/about links and contact addresses are read from configuration.
- Google Analytics loads only when `GOOGLE_ANALYTICS_ID` is set.
- JSON error responses no longer include exception messages; page sizes are clamped.
- Country status update validates its input.
- Registration no longer stores the plaintext password.
- Admin seeder creates the first admin from `ADMIN_*` environment variables.
- Tests updated to match the application's redirect behaviour; PHPUnit uses in-memory SQLite.

### Removed
- `/clear-cache` and `/storage-link` web routes.
- Hard-coded personal e-mail addresses, contributor lists, and external document links.
- Duplicate/dead files (copied templates, unused Vue components, duplicate profile pages, unused images) and unused npm packages.
