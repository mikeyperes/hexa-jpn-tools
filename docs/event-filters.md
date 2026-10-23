# Events quick filters

`[jpn_event_filters]` renders Hexa WP Core's search box for the `jpn_events`
profile. Place it in a Shortcode widget above the Elementor Loop Grid whose
Query ID is `jpn_home_upcoming_events`; that loop is marked as the list the
box narrows, so it needs no extra CSS class.

## Filters

- Text: event title, content, venue and host (the site search settings, events only).
- `hf[when][]=today|tomorrow`: events on that local day, including ones that started earlier and are still running.
- Kids events (`kids_event` field `1`) are left out by default; `hf[kids]=1` ("Show kids events") brings them back.
- `hf[location][]=<area slug>`: one or more locations (taxonomy `area`), matched with "any".

Counts and the "N upcoming events" line cover the same upcoming events the
loop lists. Ticking or typing refreshes the loop in place; the address keeps
the filters, and the loop's own "View more" loads the next page with them.

## Markup

```html
<form class="hls hls--default-1 hls--list jpn-event-filters" id="hls-jpn_events" role="search" method="get" action="/">
  <div class="hls-bar">
    <label class="hls-field hls-field--text"><input class="hls-input" type="search" name="hq"></label>
    <div class="hls-filters">
      <label class="hls-toggle hls-field--kids"><input type="checkbox" name="hf[kids]" value="1"><span>Show kids events</span></label>
      <details class="hls-filter hls-field--location">
        <summary class="hls-filter-toggle"><span class="hls-filter-label">Locations</span></summary>
        <div class="hls-filter-panel" role="group" aria-label="Locations">
          <label class="hls-option"><input type="checkbox" name="hf[location][]" value="boca-raton"><span class="hls-option-label">Boca Raton</span><span class="hls-count">17</span></label>
        </div>
      </details>
    </div>
    <a class="hls-clear" href="/">Clear</a>
  </div>
  <p class="hls-status" role="status" aria-live="polite">47 upcoming events</p>
</form>
```

The JPN look (`assets/event-filters.css`) prints once, inline, with the first
box and only on pages that use the shortcode. `style="none"` keeps the same
markup with Core's plain styling.
