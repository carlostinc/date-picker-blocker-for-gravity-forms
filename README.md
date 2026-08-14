# Date Picker Blocker for Gravity Forms

Block dates, date ranges, weekdays and minimum advance notice in Gravity Forms
date fields — globally, per form, or per individual date field.

> **Requires Gravity Forms**, a commercial product sold separately by
> Rocketgenius, Inc. This plugin does nothing on its own.

## Requirements

| | |
|---|---|
| WordPress | 6.2 or later |
| PHP | 8.0 or later |
| Gravity Forms | required (sold separately) |

## Three kinds of restriction

- **Blocked dates and ranges.** Block a single day, or a whole range in one
  record by giving it a start and an end date.
- **Minimum advance notice.** Require bookings to be made at least a set number
  of days ahead, from 0 to 365. Dates in the past are always blocked, regardless
  of this setting.
- **Weekdays.** Block one or more days of the week — for example every Sunday.

## Three levels of scope

Every restriction applies globally to all forms, to one specific form, or to one
specific date field on one form.

The two ways scopes combine are deliberately different, because the rules mean
different things:

- **Advance notice resolves by priority.** The most specific restriction wins —
  field, then form, then global — and they do not stack.
- **Weekday restrictions are additive.** Every applicable restriction is
  combined, so the blocked days add up.

## Design

The browser is UX; the server is authority. Unavailable dates are greyed out in
the date picker through the official `gform_datepicker_options_pre_init` filter,
and every submission is re-validated on `gform_validation` — so the rules hold
even with JavaScript disabled.

Server-side validation parses each submitted date using that field's *own*
Gravity Forms date format, not the plugin's global admin setting, so a field
configured differently from the rest of the site still validates correctly.
"Today" is resolved in the site's timezone.

The frontend payload is emitted once, only on pages that render a form with date
fields, and only with the rows for those forms plus the global ones. Pages
without a form run no queries at all.

## Installation

1. Make sure Gravity Forms is installed and active.
2. Copy this directory into `wp-content/plugins/`, or install the packaged zip
   through the Plugins screen.
3. Activate the plugin. Activation creates the three database tables it needs.
4. Go to **Settings → Date Blocker**, set the global date format to match what
   your forms display, then add your first restriction.

## Developer hooks

```php
do_action( 'paxrank_gf_date_blocker_init' );
apply_filters( 'paxrank_gf_date_blocker_should_load', bool $should_load );
```

## Uninstalling

Data removal is opt-in. Your restrictions and settings survive uninstalling
unless you tick the box under **Advanced Options → Uninstall**.

## Translations

Fully translatable, with a Spanish (es_ES) translation included.

## License

GPLv2 or later. See [LICENSE](LICENSE).

Built around real client work at [Paxrank](https://paxrank.com).
