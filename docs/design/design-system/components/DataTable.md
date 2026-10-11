# DataTable

A data table shows comparable rows and is the twin of every chart. Utrecht Table (`utrecht-table utrecht-table--html-table`).

- **The consumer provides:** a caption, column headers with `scope`, rows, and which columns sort.
- **Rules:** sortable headers are buttons with `aria-sort` on the header cell; amounts use `utrecht-table__cell--numeric-data` with tabular figures; on narrow screens the table scrolls inside a focusable, labelled container.
