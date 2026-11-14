---
name: Abonemen
description: A calm household view of what renews when, set as ink-blue rail and warm paper pages like a binder.
colors:
  paper: "#f6f4ef"
  surface: "#ffffff"
  rail: "#222a5e"
  rail-text: "#f3f2fb"
  rail-muted: "#b9bee3"
  ink: "#211f1c"
  muted: "#5a554d"
  accent: "#2d3b8f"
  accent-foreground: "#ffffff"
  accent-wash: "#e6e9fa"
  danger: "#a8261d"
  success: "#1d6a3a"
  warning: "#7a4b00"
  warning-surface: "#fbefd3"
  control-border: "#857e72"
  focus: "#222a5e"
  border: "#dcd7cd"
  dark-paper: "#1a1917"
  dark-surface: "#242220"
  dark-rail: "#12152f"
  dark-rail-text: "#eceaf8"
  dark-rail-muted: "#a9aed4"
  dark-ink: "#efece5"
  dark-muted: "#b3ada2"
  dark-accent: "#b4bdff"
  dark-accent-foreground: "#14173a"
  dark-accent-wash: "#2a2e57"
  dark-danger: "#f2a39b"
  dark-success: "#8fd3a6"
  dark-warning: "#f0c36a"
  dark-warning-surface: "#3a2f14"
  dark-control-border: "#8f897d"
  dark-focus: "#ffffff"
typography:
  page-title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.25
  welcome-line:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 500
    lineHeight: 1.375
  section-title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 600
    lineHeight: 1.3
  part-title:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: 1.25
  small:
    fontFamily: "Atkinson Hyperlegible Next, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.43
rounded:
  control: "10px"
  surface: "12px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "20px"
  lg: "24px"
  xl: "40px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-foreground}"
    rounded: "{rounded.control}"
    height: "44px"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    height: "44px"
  text-field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    height: "44px"
  rail:
    backgroundColor: "{colors.rail}"
    textColor: "{colors.rail-text}"
    width: "256px"
  rail-item-current:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    height: "44px"
  surface-panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.surface}"
    padding: "24px"
  settings-nav-current:
    backgroundColor: "{colors.accent-wash}"
    textColor: "{colors.accent}"
    height: "44px"
  warning-note:
    backgroundColor: "{colors.warning-surface}"
    textColor: "{colors.warning}"
---

# Design System: Abonemen

## Overview

**Creative North Star: "The Household Binder"**

Abonemen looks like a paper binder with an ink spine. A deep ink-blue rail carries the mark, the household and the destinations; the pages beside it are warm paper with white surfaces laid on them. It is calm on purpose: the product's job is to say what renews next and what needs action, then let the person close it. It refuses the finance-dashboard look (navy and gold, hero numbers, glossy charts) and the default grey app with one blue button.

The system is flat and quiet. Corners are 10px, borders are 1px warm greys, nothing casts a shadow except menus and dialogs. Type is Atkinson Hyperlegible Next, chosen so that look-alike characters cannot be confused, because a child, an older relative and a person with low vision use this as much as the parent who runs the money. Red, green and amber appear only to mean something, and always with an icon and words.

The light and dark themes share one set of colour roles; a third (high contrast) is reserved for a later ticket.

**Key Characteristics:**
- Ink rail beside paper page, joined by a tab for the current destination.
- One ink blue, doing two jobs: the rail field and the accent. Periwinkle replaces it in dark.
- Warm greys throughout, including every colour Flux paints from its zinc scale.
- Flat surfaces; shadow only on overlays.
- 16px body, 14px smallest, 44px controls on every device.
- Colour never carries meaning alone.

## Colors

A warm paper ground with white surfaces, one ink blue, and warm greys; red, green and amber are reserved for meaning. Views use the roles below and never a raw palette colour. The frontmatter holds the values: unprefixed keys are the light theme and `dark-` keys are the dark theme, which swaps the role values on a `.dark` class. Every role is checked for WCAG 2.2 AA contrast in both themes by `tests/Unit/ColourTokensTest.php`; a new role is added to both themes and to that test.

### Primary
- **Ink Blue** (accent): links, the primary button, the current item, the selected theme choice. White text sits on it. In dark it becomes **Periwinkle** (dark-accent) with deep indigo text (dark-accent-foreground).
- **Accent Wash** (accent-wash): the background of the current item in the settings menu, so the current section is shown by a tint as well as by colour of text.

### Secondary
- **Rail Ink** (rail): the sidebar and the mobile top bar. **Rail Text** (rail-text) is the text and icons on it; **Rail Mist** (rail-muted) is secondary text on the rail. In dark the rail is a near-black indigo.

### Neutral
- **Paper** (paper): the page, and the tab that joins it. **Surface** (surface): white (dark: warm charcoal) for panels and fields that sit on paper.
- **Ink** (ink): primary text, headings. **Muted** (muted): secondary text, help text, page descriptions.
- **Control Edge** (control-border): the border of text fields, secondary buttons, checkbox boxes and radio circles, strong enough to be seen against the page (3:1).
- **Hairline** (border): the 1px warm border on panels and dividers (Flux zinc-300).
- **Focus** (focus): the one focus ring colour. It is ink blue in light and white in dark; on the rail the ring is rail-text.

### State
- **Danger** (danger): errors and destructive actions, with a warning-triangle icon and a heading. Flux's red-500 and red-600 are pinned to #dc2626 and #b91c1c so white text on a danger button passes.
- **Success** (success): confirmations, with a check icon and text.
- **Warning** and **Warning Surface** (warning, warning-surface): a date that needs action, on its amber tint.

### Flux zinc scale
Flux reads its borders, secondary text and dark surfaces from the zinc scale, so the scale is replaced with warm greys: 50 #faf9f6, 100 #f6f4ef (the paper), 200 #e9e6df, 300 #dcd7cd, 400 #a8a196, 500 #6b655b, 600 #5a554d, 700 #3b3834, 800 #242220 (dark surface), 900 #1a1917 (dark paper), 950 #12110f. Do not use zinc-400 for text; it fails contrast.

### Named Rules
**The Meaning-Only Rule.** Red, green and amber say something happened or needs doing. They are never decoration, and never alone: an icon and words go with them.

**The Roles-Not-Palette Rule.** A view says `text-danger` or `bg-surface`, never `text-red-500` or `bg-white`. `ColourUsageTest` fails the raw palette colours.

**The One Ink Rule.** The same ink blue is the rail and the accent. Do not add a second accent hue.

## Typography

**Display Font:** none; headings use the body face.
**Body Font:** Atkinson Hyperlegible Next (with ui-sans-serif, system-ui, sans-serif)

**Character:** A legible, humane humanist sans designed for low vision. Its zero is slashed on purpose so that 0 and O, and I, l and 1, cannot be confused; the slash is a feature to keep, not a defect to switch off.

### Hierarchy
- **Page title** (semibold 600, 28px / 1.75rem, line-height 1.25): the single h1 of an app page, via the page heading component.
- **Welcome line** (medium 500, 22px, snug): the product statement on the welcome page, capped at 24ch.
- **Section title** (semibold, 22px / 1.375rem, 1.3): an h2 that names a section of a page, such as a settings section.
- **Part title** (semibold, 18px / 1.125rem, 1.4): an h3, a part of a section.
- **Body** (regular, 16px, relaxed): all running text, field labels, buttons, menu items and rail items. Descriptions are capped near 65ch (`max-w-prose`).
- **Small** (medium, 14px): the smallest text anywhere, for help text and the error-summary list.

### Named Rules
**The Sixteen-Fourteen Rule.** 16px is the body and every control's text; 14px is the floor. `text-xs` never appears; `ColourUsageTest` refuses it.

**The Tabular Rule.** Money and dates use tabular figures so columns line up. Money is always amount, currency code and period together.

## Layout

A fixed 256px ink rail on the left from the large breakpoint up (mark and name, household switcher, destinations, the User at the bottom). Below it, the rail becomes a drawer behind a 44px menu button in a rail-coloured top bar with the mark; the page is one column.

The page is paper with 24px padding, 40px from the large breakpoint, and content capped at 72rem. An app page opens with the page header, then surfaces. Grid gaps are 20px between surfaces; forms stack at 24px; the header sits 32px above its content.

**Page header pattern.** One h1 (28px semibold), one line of muted description under it, and the primary action on the right in the actions slot. It wraps on narrow screens, the action dropping below. Every app page uses it; auth screens use the auth header (title and one line of muted text) instead.

**Auth and welcome screens.** No card and no panel: the mark and form sit directly on the paper in a single centred column no wider than 28rem (`max-w-md`). The welcome page leads with a large mark in the accent, the name, and the statement.

Layouts reflow at 400% zoom (320px) with no horizontal scrolling.

Deliberately open: a bottom tab bar when four or more destinations exist (#13), and a denser row for lists (#13).

## Elevation & Depth

Flat, like paper. Depth comes from tone (white surface on paper, ink rail beside paper) and from a 1px warm border, never from shadow. Flux's `shadow-xs` and `shadow-sm` are set to none. Only overlays (menus, dialogs) keep a shadow, because they float above the page.

### Named Rules
**The Flat-Paper Rule.** A panel on the page has a border and no shadow. A shadow means "this floats above the page".

## Shapes

Corners are 10px on controls, fields, buttons and the rail's current-item tab, and 12px on the larger surfaces (Flux's `rounded-lg` and `rounded-xl`, tuned in `app.css`). Borders are 1px warm grey; checkboxes and radios draw a 1.5px control edge. The pill is not used.

### Signature: the paper tab
The current destination in the rail is a tab of paper joined to the page. Its background is paper, its text is ink at semibold weight, and it extends 16px past the rail's inner edge with its page-side corners squared off, so that it melts into the page like a divider in a binder. The state is carried by shape and weight as well as colour, and the link carries `aria-current="page"`. On focus its ring moves inside (offset -4px) so it is not clipped.

### The mark
A house with a renewal arrow: a solid house in the text colour (currentColor), with a circular arrow cut out of it. The arrow is knocked out in a second colour, set by `--mark-knock`, which the parent must set to the colour behind the mark: the rail on the rail and top bar, paper on auth and welcome screens. Only two colours ever appear in it. On the auth and welcome screens the house is the accent, with the name in ink. The favicon is the same mark in paper on an ink-blue square (`public/favicon.svg`); the ICO and touch icon are rasters of it.

## Components

### Buttons
- **Shape:** 10px corners, minimum 44px tall and 44px wide on every device, 16px text.
- **Primary:** ink blue field with white text (periwinkle with indigo text in dark). One per view, in the page header actions slot or beside the form it submits.
- **Secondary:** surface field with a visible control edge and ink text.
- **Danger:** Flux's danger variant on the darkened reds.
- **Focus:** the one 2px focus ring, offset 2px.

### Inputs / Fields
- **Style:** surface fill, 1px control-edge border, 10px corners, 44px tall, 16px text. The label is always visible above the field; no placeholders. Format hints are help text in muted.
- **Focus:** the 2px focus ring, offset 2px.
- **Error:** the field shows its message beneath it in danger with an icon. The form opens with the error summary.
- **Checkbox and radio rows:** 44px tall so the label is as easy to hit as a button.
- **Theme choice:** a segmented control with 44px items; the selected one is semibold and outlined with a 2px accent inset ring, not only lighter.

### Navigation
- **Rail:** ink rail, rail-text items at 500 weight, a 10% white wash on hover, and the paper tab for the current item. Items are 44px.
- **Mobile:** an ink top bar with the mark and a menu button; the rail opens as a drawer with a 44px close control.
- **Settings menu:** a named navlist whose current section is accent-wash with accent text, semibold.
- **Household switcher:** a dropdown trigger filling the rail, truncating long names.

### Error summary
A 2px danger border box with 16px padding and 10px corners, opening a failed form. A triangle icon and "There is a problem" in danger, then one underlined link per invalid field. It takes focus when it appears.

### Status message
A live region with a check icon and text in success, 14px medium, placed beside the button or at the top of the page after a redirect. It stays until the User does something else. There are no toasts.

### Modals
Native dialog, named by its heading, with a shadow because it is an overlay.

## Do's and Don'ts

### Do:
- **Do** use colour roles (`bg-paper`, `bg-surface`, `text-ink`, `text-muted`, `text-accent`, `text-danger`, `text-success`, `text-warning`) for every colour.
- **Do** open every app page with the page header: one h1, one line of description, the primary action at the right.
- **Do** set `--mark-knock` to the colour behind the mark wherever it is placed.
- **Do** keep every control 44px tall and every text at 16px, with 14px as the floor.
- **Do** show a confirmation in the status message component, in the page, with an icon and text.
- **Do** pair every danger, success or warning with an icon and words.
- **Do** put money and dates in tabular figures, with amount, currency code and period together.
- **Do** let surfaces be a white panel with a 1px warm border and no shadow.

### Don't:
- **Don't** put eyebrow labels, kickers or small-caps overlines above headings.
- **Don't** put cards or panels around auth screens; the form sits on the paper.
- **Don't** use raw palette colours (`text-red-500`, `text-green-600`, `text-zinc-400`) for state or text.
- **Don't** use `text-xs` or any text under 14px.
- **Don't** use toasts; they vanish on a timer and a screen reader never hears them.
- **Don't** add shadows to panels, or a second accent hue, or navy-and-gold finance styling with hero numbers.
- **Don't** replace the slashed zero or switch the typeface to a system display face.
- **Don't** show colour alone as meaning: the current page, an error and a confirmation each have a second cue.
- **Don't** use placeholders as labels, or `label:sr-only`.

### Deliberately open
- A bottom tab bar when four or more destinations exist, and a denser list row (#13).
- A high-contrast theme and a per-User theme choice (#34); the colour roles are built so a third theme slots in.
- The dashboard's surfaces are empty placeholders until #16; they are not a design for the real dashboard.
