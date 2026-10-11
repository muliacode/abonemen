# FormField

A form field is a visible label, an optional description, an optional error and one control. Utrecht Form field (`utrecht-form-field`) with Form label, Form field description, Form field error message, Textbox, Textarea and Select.

- **The consumer provides:** label, optional description, the control, its value and, after validation, the error message.
- **Rules:** the label is always visible, never a placeholder; description and error are linked with `aria-describedby`; an invalid field gets `utrecht-form-field--invalid`, `aria-invalid="true"` on the control and its error message above the control; amounts use the money input (currency select plus a numeric textbox) with tabular figures.
