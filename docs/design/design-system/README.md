# Abonemen design system

Files in this folder:

| File | What |
| --- | --- |
| `tokens.json` | Design tokens: colours for the light, dark and high-contrast themes, type, spacing, radius, sizes |
| `tokens.css` | The same tokens as CSS custom properties, per theme (`data-theme="light|dark|contrast"`) |
| `abonemen-theme.css` | The Abonemen theme: sets Utrecht component tokens (`--utrecht-*`) from the tokens above, plus the Abonemen-only components (`mc-` classes) |
| `components/` | Guidelines per component: which Utrecht component it is, what the consumer provides, accessibility rules |
| `icons/` | The Tabler icons in use, with their licence |

The screens that use all of this are in [`../wireframes`](../wireframes/README.md).

Abonemen is the theme of `@muliacode/ui` for the Abonemen subscription tracker. `@muliacode/ui` is a brand-neutral Vue component library built on [NL Design System](https://nldesignsystem.nl/) tokens and the Utrecht CSS components; this system defines Abonemen's colours, type, spacing and component look on top of it. Every rule here serves one goal: a calm tool that everyone can use, including people on screen readers, keyboards, 400% zoom and Windows high contrast. WCAG 2.2 AA is the floor, not the target.

## Content fundamentals

**Voice.** Calm, plain and specific. Abonemen tells people what is due, what it costs and what to do, then gets out of the way. Write at language level B1. Address the reader as "you" (Dutch: "je", Indonesian: "kamu"); Abonemen refers to itself only in system messages.

**Casing and punctuation.** Sentence case everywhere: headings, buttons, labels, navigation. No exclamation marks, no emoji, no ALL CAPS.

**Money and dates.** Every amount shows its currency and, when recurring, its period: "€ 15,99 per month", never "15.99". Converted amounts carry their rate date in `small` text: "≈ € 4,12 per month · rate of 9 October 2026". Dates are written out in the user's language: "Friday 17 October", with the year only when it is not the current one.

**Buttons and links** start with a verb and name the object: "Add subscription", "Save changes", "Cancel subscription", never "OK", "Submit" or "Click here".

**Errors** say what happened and how to fix it: "Enter a price, for example 12,99", not "Invalid input". Empty states say what to do next: "No subscriptions yet. Add your first one to see what you pay each month."

| Do | Don't |
| --- | --- |
| Netflix renews on Friday 17 October. | Upcoming renewal detected! |
| Cancel by 3 November to avoid the next payment. | Act now before it's too late |
| Enter the price you pay per cycle. | Price field is required |

## Visual foundations

### Colour

- Set the page on `bg-document`; put cards, panels, dialogs and form controls on `bg-surface`; use `bg-subtle` for table headers, zebra rows and the bottom bar.
- Body text is `text-default`; secondary details are `text-subtle`. Never use `text-subtle` for anything a user must read to act.
- `action-1` is the one interactive colour: primary buttons, links, checked controls, the selected navigation item. Text on an `action-1` fill is always `on-action-1`.
- `accent-2` (kunyit, turmeric) marks what deserves a second look: the budget meter, highlighted totals, the "needs attention" panel on `accent-2-bg`. Use it sparingly; one accent area per screen.
- Status colours (`status-positive`, `status-negative`, `status-warning`, `status-info`) always come with a word or icon. Colour alone never carries meaning.
- Charts use `chart-1` to `chart-4` in order, each series also labelled, and every chart has a table twin.
- Three themes: Light (default), Dark, and High contrast, which raises every text pair to 7:1 and draws controls and cards with solid borders. Windows high contrast (`forced-colors`) is respected as well: never rely on background colour to show a boundary.

### Typography

- One family: Atkinson Hyperlegible Next, designed by the Braille Institute for low-vision readers, licensed under the SIL Open Font License. The app self-hosts the font files (no third-party requests).
- Body copy is `body` at 18px. Nothing a user reads is smaller than `small` (16px).
- One `heading-1` per page, then `heading-2` and `heading-3` in order; never skip a level for looks.
- Amounts use `amount` or `amount-large` with `font-variant-numeric: tabular-nums`, right-aligned in tables so the decimals line up.
- Line length stays under 70 characters for running text.

### Spacing and layout

- Spacing comes only from the `space-*` steps.
- One content column, at most `content-max` (72rem) wide. Side gutter `space-4` on phones, `space-6` from 48rem, `space-12` from 80rem.
- Under 48rem: primary navigation is a bottom bar of five items. From 48rem: a side bar. The layout reflows to a single column at 320px wide (400% zoom) with no horizontal scrolling.
- Every interactive target is at least `target-min` (44px) high and wide.

### Shape, borders and depth

- Corners: `radius-md` for buttons, controls and alerts; `radius-lg` for cards and dialogs; `radius-round` only for logo discs.
- Borders, not shadows. Cards are `bg-surface` with a `border-width` line in `border-default`; controls have a `border-width-control` border in `border-control`. `shadow-overlay` is reserved for dialogs and menus.

### States

- **Focus:** a `focus-width` (3px) solid outline in `focus-ring`, `focus-offset` (2px) outside the element, with `radius-sm` corners. Every focusable element shows it on keyboard focus; never remove it.
- **Hover:** fills move to `action-1-hover`; links gain a thicker underline. Hover never reveals information that keyboard users cannot reach.
- **Disabled:** avoid it. Prefer an enabled button that explains what is missing on press. When unavoidable, use `bg-subtle` with `text-subtle` and keep the control focusable with `aria-disabled`.
- **Error:** the field's border becomes `status-negative`, an error message with an icon appears above the input, and an error summary at the top of the form links to each field.

### Motion

Motion is minimal: 150ms colour and border transitions only, no movement. With `prefers-reduced-motion: reduce` all transitions are off.

## Iconography

Use the Tabler Icons outline set (MIT licence) at 24px with a 2px stroke, in the colour of the text beside it. Icons accompany a text label; an icon-only button needs an accessible name and a visible tooltip. Status icons: circle-check (positive), alert-triangle (warning), circle-x (negative), info-circle (info). The `icons/` folder holds the 68 icons the screens use (Tabler 3.48.0, unchanged), with a table of which icon means what; take any new icon from the same version and style.

There is no Abonemen logo yet; the name is set in `heading-1` weight Atkinson Hyperlegible Next.

## How this follows NL Design System

- **Components are Utrecht's, unchanged.** The app loads `@utrecht/component-library-css` 9.0.15 (EUPL-1.2, Gemeente Utrecht and Frameless B.V.) from npm exactly as published, followed by `tokens.css` and `abonemen-theme.css` from this folder. Use the Utrecht class names (`utrecht-button utrecht-button--primary-action`, `utrecht-form-field`, `utrecht-alert utrecht-alert--warning` …); never restyle a Utrecht component with your own selectors.
- **The theme is component tokens.** The Abonemen theme sets Utrecht's own custom properties (`--utrecht-button-primary-action-background-color`, `--utrecht-form-control-border-color`, `--utrecht-focus-outline-color` …) to Abonemen tokens. Switching theme only swaps token values, so every Utrecht component follows.
- **Abonemen-only components** cover what NL Design System has no component for: `SubscriptionItem`, `TotalsPanel`, `ErrorSummary`, `Navigation`, `Calendar` (an agenda grid; Utrecht's Calendar is a date picker) and `BarChart`. They use the `mc-` prefix and only Abonemen tokens.
- **In the app** `@muliacode/ui` wraps the same Utrecht CSS components as Vue components and loads the same theme file; the HTML here is what those components render.

## Components

Each component's README says which Utrecht component it is (or that it is Abonemen's own), what the consumer provides and the accessibility rules it must keep. Wrap a page in `utrecht-document` so text inherits the theme's font, size and colour.
