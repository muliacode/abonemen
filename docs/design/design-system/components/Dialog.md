# Dialog

A dialog asks for confirmation before something destructive, or warns that a session is about to end. Utrecht Alert dialog (`utrecht-alert-dialog`) on the native `dialog` element.

- **The consumer provides:** a title that names the item, one or two sentences, and the actions.
- **Rules:** opened with `showModal()`; focus moves to the first action and returns to the trigger on close; Escape closes it; destructive confirmations name the item and put the safe action first.
