# People filter chips and team paging

## People

`[hws_search profile="people" directory="people" filter_layout="chips" style="none"]`

Publication people-list shortcodes also accept `filter_layout="chips" search_style="none"` with `per_page="10"`. Defaults remain dropdowns. Chips choose one group; All removes the group constraint and preserves the name search. Existing dropdowns retain multiple selection.

Markup: `.hls-filters > fieldset.hls-chips > legend` names the group; each `label.hls-chip` contains a native `input[type=radio][name="hf[group][]"]` and a `span` label. Values are empty (All), `byline`, `contributor`, `staff`. Checked state and `.is-active` identify the selection. Native keyboard and focus behavior are retained. `style="none"` emits the same HTML without chip decoration. Site CSS can use `.hls-chip:has(input:checked)`; never hide the input without preserving focus indication.

