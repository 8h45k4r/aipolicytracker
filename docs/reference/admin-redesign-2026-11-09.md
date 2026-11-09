# Admin redesign, 9 November 2026

## Why

The owner asked for a full UX pass on the admin, with the navigation first. This round screenshotted all 25 admin screens at desktop and phone widths.

| Found | Effect |
|---|---|
| A dark navy sidebar of 20 entries in one long list, with nothing to say where work was waiting | Every visit started by opening pages to find out whether anything needed doing |
| Two search fields stacked at the top of the sidebar ("Go to…" and "Search pages and records") | Two boxes that look alike and do different things |
| Poppins as the admin face, Space Grotesk (until the week before) on the public site | Two faces to load, and the admin read as a different product |
| 11px capitalised labels above every filter field and table column | The densest text on the page was the hardest to read |
| Bulk-action panels showing a row of disabled buttons when nothing was ticked | Most of a list page was controls that did nothing |
| Every link in every table row fully underlined | A list read as a wall of lines |

## Research

These points come from design-system and practitioner guidance, not from user studies of this admin:

- **Group by the work, not by feature.** Group entries and give the groups clear headings, so a person scans one group instead of the whole list ([UX Movement, sidebar grouping](https://uxmovement.substack.com/p/how-to-group-your-sidebar-items-for); [SaaS sidebar patterns](https://www.saasui.design/blog/saas-sidebar-navigation-ux-patterns)).
- **Past about 20 entries, add collapsible sections and a search fallback** ([Sidebar navigation design](https://www.inspoai.io/blogs/sidebar-navigation-design)). This is a rule of thumb from one author, not a measured threshold.
- **Show only what the role can use.** Too many options at once slow people down ([AdminLTE, admin dashboard design 2026](https://adminlte.io/blog/admin-dashboard-design/), citing NN/g on progressive disclosure). The admin already hid entries a role cannot open; this round keeps that.
- **A badge is its own part of the navigation**, beside the item, group heading and collapse toggle ([GitLab Pajamas, navigation sidebar](https://design.gitlab.com/usability/navigation-sidebar)).
- **Colour is never the only signal** (WCAG 1.4.1):
  - counts carry their number and a screen-reader phrase;
  - the current page has a bar as well as a tint;
  - table links keep an underline.

## What changed

- **Navigation as data.** The sidebar is `config/admin-navigation.php`, in six groups ordered by how often each is used:
  - Overview;
  - Records: change desk, review queue, submissions, independent checks;
  - Content: external data, tool library, posts to X;
  - Audience;
  - Operations;
  - Administration.
- **Collapsing groups.** Every group folds. The group holding the current page is always open, and the browser remembers groups the person folded.
  - While the menu filter has text, every group opens, so a match is never hidden.
  - A folded group that holds waiting work keeps a dot.
- **Counts of waiting work** beside entries (`App\Services\Admin\NavCounts`, cached for a minute), shown only when above zero:
  - review queue: instruments unverified or stale;
  - submissions waiting;
  - licence enquiries from the last 7 days;
  - posts awaiting approval;
  - failed alert deliveries.
- **A light sidebar** on white with a hairline border, and the current page marked by a tint and a blue bar.
- **A top bar** with the section and page name, the one search (Ctrl/⌘ K, pages, actions and records) and "View site".
  - The sidebar keeps only a menu filter (`/`).
- **The system font**, as on the public site. Poppins is removed, so the admin downloads no font.
- **Quieter lists:**
  - field labels and column headings are 12–13px sentence case instead of 11px capitals;
  - table links keep a light underline that darkens on hover.
- **Bulk actions appear when rows are ticked.** With nothing ticked, the panel says "None selected" and shows no disabled buttons. Without JavaScript the buttons show, as before.

Tests:
- `tests/Feature/AdminNavigationTest.php` (config, counts, role filtering, top bar);
- updated sidebar assertions in `AdminDashboardTest`, `AdminShellTest` and `AdminListsTest`.

## Not changed in this round

- **The page bodies.** Dashboard cards, filters and tables keep their structure; they inherit the new type, labels and table style.
- **The phone layout.** It keeps its Menu button and was not re-screenshotted after sign-in.
- **The account screens and the security pages.** They use the shared sign-in shell.
