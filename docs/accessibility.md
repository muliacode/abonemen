# Accessibility

Abonemen meets **WCAG 2.2 level AA**, in the light and the dark theme. This page is the contract for every screen:
the patterns to build with, what to do before calling a screen done, the Flux views we override, and the checks a test
cannot make. A ticket that adds a screen cites this page in its acceptance criteria instead of repeating it.

A high-contrast theme and a per-User choice of theme belong to the themes ticket (#34). The colour roles below are built
so that a third theme can slot in.

## Definition of done for a screen

- [ ] The page is added to `pages()` in `tests/Concerns/RendersPages.php`, so the structure and form tests cover it.
- [ ] It uses a layout (`x-layouts::app` or `x-layouts::auth`) and one `<x-page-heading>`.
- [ ] Every form follows the form pattern below, and a failed submit is added to `tests/Feature/Accessibility/ErrorSummaryTest.php`.
- [ ] Confirmations use `<x-status-message>`, never a toast.
- [ ] It uses colour roles (`text-danger`, `text-success`, `text-muted`), never a raw palette colour for state.
- [ ] Every new text is translated (`TranslationCompletenessTest` fails otherwise).
- [ ] The manual checklist at the end passes, in both themes.

## Page structure

- Every layout starts with `<x-skip-link />`, the first focusable element, which jumps to `<main id="main">`.
- `<flux:main>` renders a real `<main>` (see the overrides). The auth layout wraps its content in `<main id="main">`.
- A page has exactly one `<h1>`, written with `<x-page-heading>`. Headings below it never skip a level. In settings, the
  section title is an `<h2>` (`x-pages::settings.layout` renders it).
- Every `<nav>` has a name (`aria-label`). The current link carries `aria-current="page"` (the `button-or-link` override).
- After a `wire:navigate` page change, `resources/js/app.js` moves focus to the new page: to a waiting confirmation if
  there is one, otherwise to the `<h1>`, otherwise to `<main>`. Elements that take focus this way have `tabindex="-1"`
  and no focus ring of their own.
- The mobile sidebar toggle reports `aria-expanded` (`resources/js/app.js`).

## Forms

```blade
<form wire:submit="save" class="space-y-6">
    <x-error-summary />

    <flux:input id="name" wire:model="name" :label="__('Name')" :description="__('As it appears on invitations.')" required />

    <div class="flex items-center gap-4">
        <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        <x-status-message :message="$status" />
    </div>
</form>
```

- **Labels are visible and always there.** Use the `label` prop. Never `label:sr-only`, never a placeholder instead of a label.
- **No placeholders.** A format hint is help text: the `description` prop, which Flux ties to the control.
- **Field ids.** A field's `id` equals its validation key (`id="name"` for `wire:model="name"`, `id="email"` for `name="email"`).
  When two forms share a page, such as a modal in the layout, give the form a prefix: `id="create-household-name"`,
  `error:id="create-household-error-name"` and `<x-error-summary prefix="create-household-" />`.
- **Error summary.** `<x-error-summary />` is the first thing in the form. On a failed submit it appears, takes focus,
  names the problem and lists one link per invalid field, each landing on that field. It is the only announcement:
  Flux's inline error is no longer a `role="alert"`.
- **Inline error.** Each field shows its own message under the control (Flux does this from the `name` or `wire:model`).
  `resources/js/app.js` points the control at it with `aria-describedby`, which Flux does not do.
- **Validation runs on submit and on blur**, not on every keystroke. Live validation is an opt-in per field
  (`wire:model.live`), for cases such as "is this name taken", and the reason is written next to it.
- Both stacks work: classic POST forms (Fortify) read the session's `$errors`, Livewire forms their own.
- Controls a keyboard cannot reach are bugs. A "link" that acts is a `<button type="button">`, never a `<div @click>`.

## Confirmations

A confirmation stays on the page, in a live region, until the User does something else. There are no toasts: they vanish
on a timer (WCAG 2.2.1) and Flux's toast has no live region, so a screen reader never hears it.

- Same page: the component has a `public ?string $status = null;`, sets it after saving (`$this->status = __('Saved.');`),
  clears it in an `updated()` hook, and renders `<x-status-message :message="$status" />` beside the button.
- After a redirect: `session()->flash('confirmation', __('Household created.'))`. The app layout shows it and, after a
  page change, focus goes to it so that it is read out.
- Sign-in screens show Fortify's `status` with `<x-auth-session-status>`, which uses the same component.

## Colour

`resources/css/app.css` defines **colour roles**. Views use them, and nothing else, to say what a colour means:

| Role | Use |
| --- | --- |
| `text-danger`, `border-danger` | errors, destructive actions |
| `text-success` | confirmations |
| `text-muted` | secondary text, help text, hints |
| `bg-surface`, `bg-surface-sunken` | the page and the sidebar |
| `--color-control-border` | the edge of a text field (set globally) |
| `--color-focus` | the focus ring (set globally) |

`tests/Unit/ColourTokensTest.php` computes the contrast of every role on every surface in both themes (4.5:1 for text,
3:1 for control edges and the focus ring). `tests/Unit/ColourUsageTest.php` fails on `text-red-500`, `text-green-600`,
`text-zinc-400` and the like in views. To add a role, add it to both themes in `app.css` and to `ColourTokensTest::roles()`.

Colour never carries meaning alone: an error has an icon, a heading and text, the current page has `aria-current`, a
confirmation has an icon and text.

## Focus and touch targets

- One focus ring (`--color-focus`) for everything focusable. Do not remove an outline without replacing it.
- Controls are at least 24px (WCAG 2.5.8) and, where the pointer is coarse (`@media (pointer: coarse)`), 44px.
  Icon-only controls need an `aria-label`.
- Layouts reflow at 400% zoom (a 320px viewport) with no horizontal scrolling: no fixed widths beyond `max-w-*`,
  no `min-w-*` wider than 320px outside `md:`.

## Modals

Flux modals are native `<dialog>` elements, so the browser traps focus, closes on Esc and returns focus to the trigger.
Our part: the dialog needs a name. Give the heading an id and the modal `aria-labelledby`:

```blade
<flux:modal name="remove" aria-labelledby="remove-heading">
    <flux:heading id="remove-heading" level="2" size="lg">{{ __('Remove') }}</flux:heading>
```

## Flux overrides

Where a Flux component fails WCAG we override its view in `resources/views/flux`. That is the supported way to customise
Flux (`php artisan flux:publish` copies a component there) and fixes every use at once, including future ones.
Never edit `vendor/`. An override does not receive Flux's own fixes, so each one is listed here with the version it was
copied from, and `tests/Unit/FluxOverridesTest.php` fails when Flux is upgraded or an override is added without a row.

Flux version the overrides are based on: **2.21.0**

After adding or changing an override, run `php artisan view:clear`: compiled views are cached.

| File | Why |
| --- | --- |
| `flux/main.blade.php` | Renders a `<main>` landmark instead of a `<div>`. |
| `flux/button-or-link.blade.php` | Adds `aria-current="page"` to a link to the current page; Flux marked it with colour only. |
| `flux/error.blade.php` | Gives the message an id, drops `role="alert"` (the summary announces), and uses `text-danger` (red-500 was 3.8:1). |
| `flux/modal/index.blade.php` | Passes `aria-labelledby`, `aria-describedby` and `aria-label` to the `<dialog>` instead of the wrapper; darkens the close icon. |
| `flux/navlist/group.blade.php` | From the starter kit: the sidebar group. |

When Flux is upgraded: for each file above, diff it with `vendor/livewire/flux/stubs/resources/views/<file>`, port what
changed upstream, then update the version above.

## What the tests cover, and what they cannot

Automated (`tests/Feature/Accessibility`, `tests/Unit`):

- every page: skip link first, one `<main>`, one `<h1>`, no skipped heading level, named navigation, no forced theme, named dialogs;
- every form: a visible label for every control, no placeholders, a summary that links to the invalid fields;
- confirmations in a live region, with no toast;
- colour contrast of the roles, and no raw palette colours;
- the Flux overrides against the installed version.

Not covered, so checked by hand:

- the JavaScript behaviours: focus moving to the error summary and to the new page, `aria-expanded` on the sidebar
  toggle, errors linked to their control;
- that Flux's own script links each label and description to its control (it does this at runtime);
- visible focus, reflow, target size, and contrast of Flux's internal colours.

## Manual checklist

Do this for a new screen, in the light and in the dark theme (Settings > Appearance), with the keyboard only:

1. **Tab through the page.** Focus is always visible, the order follows the reading order, and nothing is skipped or trapped.
2. **First Tab** shows the skip link. Enter jumps to the content.
3. **Submit the form empty.** The error summary takes focus, each link lands on its field, each field says what is wrong.
4. **Fix the errors and save.** The confirmation appears next to the button and a screen reader reads it.
5. **Follow a link** (`wire:navigate`). Focus lands on the new page's heading, or on its confirmation.
6. **Open each modal.** Focus moves in and stays in, Esc closes it, focus returns to the button that opened it.
7. **Zoom to 400%** (or a 320px window). Nothing scrolls sideways and nothing is cut off.
8. **Touch a device or emulate one** (`pointer: coarse`). Controls are comfortably large and not touching each other.
9. **Look at the page without colour** (a greyscale filter). Errors, the current page and confirmations are still obvious.
10. **Run a screen reader** (NVDA or VoiceOver) over the page once: landmarks, headings, labels and errors make sense.
