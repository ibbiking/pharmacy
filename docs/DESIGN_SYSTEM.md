# Design system

Goal: a modern, colourful, professional SaaS look that sells — the visual language of our own **SeeATS**
product (reference screens 410–416), extended with full **dark / light** theming.
Applies to every screen built in the Inertia/React frontend (Roadmap step 3 onward). The current Blade
screens are **not** restyled — they are replaced module by module (no work done twice).

## Stack decision

| Concern | Choice | Why |
|---|---|---|
| UI framework | **Bootstrap 5.3** compiled CSS + CSS-variable design tokens (`resources/css/app.css`, no Sass) | Native `data-bs-theme="dark|light"`: every built-in component (buttons, inputs, tables, dropdowns, modals) flips colour automatically, which is what makes "every button/text follows the theme" reliable. SeeATS is also Bootstrap 5 → its layout/cards can be ported. The current admin is Bootstrap, so the team already knows it. |
| Icons | `bootstrap-icons` (same as SeeATS) | One icon set everywhere |
| Charts | **Apache ECharts 6** via our own `Components/Chart.tsx` wrapper (theme-aware, lazy-loaded) | Donuts, gradient bars, line/area, funnels, gauges, heatmaps; built-in dark theme; handles large series. Replaces Chart.js v2 on the dashboard. |
| Font | **Inter** (self-hosted, variable) | Matches the reference screens; excellent numerals for money tables |
| Tables | Own `<DataTable>` component, **server-side only** (see pagination rule) | One component, one behaviour across all modules |
| Selects | Own `<AsyncSelect>` (react-select-async-paginate over `/admin/lookup/{entity}`) | Searchable, 10/20 per page, never loads a full list |
| Forms | react-hook-form + server validation errors from Laravel | — |

## Theme

- Theme toggle in the top bar (sun/moon) + "System" option. Stored per **user** (DB column) so it follows the user
  across devices; applied before first paint (no white flash in dark mode).
- All colours come from CSS variables — **no hard-coded hex in components**. Light and dark each define:
  `--surface`, `--surface-raised`, `--border`, `--text`, `--text-muted`, `--primary`, `--primary-contrast`,
  `--success`, `--warning`, `--danger`, `--info`, plus a categorical chart palette of 8 hues tuned per theme.
- Contrast: body text ≥ 4.5:1, large text/icons ≥ 3:1 in both themes (checked in CI with a token contrast test).

## Layout (from 410–416)

- **Sidebar**: deep indigo → violet vertical gradient; white icon+label items; active item = bright violet pill with
  soft glow; collapsible groups with chevrons; collapses to an icon rail (tooltips) and to an off-canvas drawer on
  mobile. Items appear only for **modules active in the business's plan** (locked modules show a small lock + "Upgrade").
  Sidebar order follows the business flow of the active modules.
- **Top bar**: business logo/initial chip + business name; **Switch Business** dropdown (count of businesses);
  global search; quick-create "+"; theme toggle; notifications; user chip with role.
- **Page header**: icon tile (gradient square) + title + one-line description; primary action button on the right.
- **KPI strip**: row of stat cards — circular coloured icon ring, label, big number, optional % chip, tinted
  background per status colour. Clicking a card filters the list below.
- **Section cards**: rounded 12px, subtle shadow, thin gradient top border in the section's colour, icon tile,
  title + muted subtitle, optional segmented control (Today / 7 days / 30 days / 90 days / All time).
- **Settings pages** (410): collapsible accordion sections with ON/OFF status pills in the header, toggle cards
  with explanatory text, inline success/warning callouts ("Emails are sent through SMTP…"), provider quick-picks
  (Gmail / Microsoft 365 / Zoho / Amazon SES / Other), live preview panel.
- **Lists** (416 + Books/IMS): KPI strip → filter bar → table with coloured left accent per row status, pill badges,
  avatar initials, ⋮ row menu, "Total: N" + per-page selector. Row click → **split view** (list left, document right)
  for transaction documents (IMS/Books pattern).
- **Detail modals / drawers** (411–413): tabbed (Statistics / Information / Related), action icon buttons
  (open, edit, pause, cancel) in tinted squares.
- **Charts** (411, 414, 415): gradient horizontal progress bars with count + %, donut with centre total and legend
  with counts and %, bar/line over time with hover tooltips, range-slider style comparisons (min–avg–max).

## Receipts and printed documents

- **Live POS receipt.** An interactive on-screen receipt that is also the print preview: lines animate in,
  clicking a line edits it, totals update live, and it shows batch and expiry chips, the FBR block and a change
  calculator.
- **Every document has two print layouts**, built from the same data:
  - **Thermal** (58/80 mm, monospace-friendly, high-contrast black and white, configurable margins in one place);
  - **A4** (branded header with logo or default badge, addresses, line table, tax summary, terms, signature and QR).

## Helper pages & inline help

- Every module has a **"How it works"** page: an interactive flow diagram (steps light up as you hover), plain-language
  explanation, and "Try it" links into the real screens.
- Every preference has an **ⓘ explainer** right where it is set (sale price preferences, POS preferences, tax
  inclusion, lot selection) with a worked example using the business's own currency.

## Accessibility & quality bar

- Keyboard reachable everything; visible focus ring in both themes; POS fully usable by keyboard + barcode scanner.
- Spacing scale 4/8/12/16/24/32; radius 8/12; type scale 12/13/14/16/20/24/32.
- Empty states with illustration + next action; skeleton loaders, never blank screens.
