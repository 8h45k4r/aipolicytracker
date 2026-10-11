# Design system: shape, elevation, motion and interaction

One set of tokens for the public site, the account screens and the admin. They live in
`resources/css/public.css` (`:root`) and are read by Tailwind (`tailwind.config.js`) and
by `admin.css`. Never write a raw `border-radius`, `box-shadow` or transition timing in a
component: use the token.

## Corner radius

A 4px grid, in the range most mature systems settle on (Material 3: 4/8/12/16/28;
Shopify Polaris: 8px for its standard surfaces). We stop at 16px: this is a reference work
read by lawyers and policy staff, and very round corners read as consumer and playful.

| Token | Value | Tailwind class | Use |
|-------|-------|----------------|-----|
| `--radius-xs` | 4px | `rounded-sm` | badges, checkboxes, colour swatches, inline code, counters |
| `--radius-sm` | 6px | `rounded` | menu items and small controls *inside* a panel |
| `--radius-md` | 8px | `rounded-md` | buttons, inputs, selects, callouts and alerts, toasts, code blocks |
| `--radius-lg` | 12px | `rounded-lg` | cards, panels, tables, toolbars, dropdown panels, empty states |
| `--radius-xl` | 16px | `rounded-xl` | dialogs, bottom sheets |
| `--radius-full` | 9999px | `rounded-full` | filter chips, pills, avatars, status dots, progress bars |

Rules:

1. **Size follows the element.** The bigger the surface, the bigger the corner. A 4px
   corner on a page-wide panel looks sharp, and a 12px corner on a 20px badge looks like a pill.
2. **Nested corners are concentric:** inner radius = outer radius − padding. A dropdown
   panel (12px) with 6px padding holds menu items at 6px. With no padding, clip the
   children (`overflow-hidden`) instead of rounding them.
3. **Full-height and full-width edges are square.** The side panel (drawer) and table rows
   that touch the screen edge on a phone have no radius on that edge.
4. **Chips are pills, buttons are not.** The shape tells a filter (toggleable, many) from
   an action (one, does something).

## Elevation

Four steps, tinted with the brand navy so shadows read as depth, not grime. Dark mode
swaps them for denser black shadows, because a tinted shadow vanishes on a dark surface.

| Token | Use |
|-------|-----|
| `--shadow-sm` | resting primary buttons |
| `--shadow-md` | a hovered card or figure that is a link; sticky action bars |
| `--shadow-lg` | dropdown panels, toasts |
| `--shadow-xl` | dialogs and side panels |

## Motion

`--duration-fast` (120ms) for state changes such as hover, press and focus, and
`--duration-base` (200ms) for things that arrive, such as panels and raised cards. Both use
`--ease-standard`, which decelerates. Everything collapses to near zero under
`prefers-reduced-motion`. The one deliberate motion is the primary button's 1px lift on
hover.

## Interaction

- **Focus**: one indicator everywhere, a 2px cyan outline 2px off the element. It is an
  outline, not a box-shadow ring, because it follows the element's corners and survives
  Windows high-contrast mode. Fields show focus with their own border and ring instead.
- **Hover** applies only on devices that can hover (`hoverOnlyWhenSupported`), so a tap
  on a phone never leaves a button looking hovered.
- **Targets**: public buttons are 44px tall, chips 34px, admin buttons 38px (30px with
  `.btn-sm`). All exceed WCAG 2.2's 24px minimum (2.5.8).
- **Disabled** buttons fade to 50% and show the not-allowed cursor; say why next to them.
- **Busy**: a submitted POST form marks its button `aria-busy` and holds it, so a double
  click sends once. The admin also relabels it "Working…".
- **Invalid** fields (`aria-invalid="true"`) turn their border red, not only the message
  below them.
- **Forced colours**: filled buttons, active chips and badges keep a visible border, and
  focus uses the system highlight colour.
- **Text**: headings balance their lines, paragraphs avoid a lone last word, and table
  figures use tabular numbers.

## Third parties

No page loads a font, script or stylesheet from a CDN (`tests/Unit/NoThirdPartyFontsTest.php`).
Fonts are in `resources/fonts`. Icons are inline SVG or text.
