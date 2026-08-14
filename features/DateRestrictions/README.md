# DateRestrictions Feature

## Overview
Blocks/restricts date selection in Gravity Forms date fields — both client-side
(UX) and server-side (authoritative enforcement). Three restriction types share
one scope model (global / per-form / per-field): blocked **dates & ranges**,
**advance-booking** windows, and **weekday** restrictions.

Design principle: **the client executes decisions the server already
resolved** — per-field advance days, the site's timezone offset, and the
recognized-field list all ship in the payload, so the JS never re-derives
server semantics.

## Structure
Classes live in `Paxrank\DateBlocker\DateRestrictions\...` (sub-namespace =
folder) and are resolved lazily by the plugin's PSR-4 autoloader
(`autoload.php` at the plugin root) — there is no require list. File name ==
class name, so the verb-noun files ARE the class names (action classes:
`ReadingBlockedRanges::all()`, `SavingBlockedRanges::add()`).

- `DateRestrictionsHandler.php` — wires the slice's hooks.
- `Database/` — schema (with the `end_date` range column + DB-version gate),
  the `Reading*`/`Saving*` classes for each table (atomic
  `INSERT ... WHERE NOT EXISTS` guards), and `CleaningUpOnFormDelete.php`
  (drops a form's/field's rows on permanent GF deletion).
- `Rules/` — `Checking*` classes: the date logic + user-facing message per type.
- `Enforcement/` — `gform_validation` (parses each submitted value with the
  FIELD's own GF date format; `get_input_type()` so inputType-date fields are
  covered too).
- `Frontend/` — enqueue + `date-blocker.js` (range-aware) + `date-blocker.css`,
  plus `DeterminingWhereToLoad.php` (the load gate).
  Datepicker options are injected via GF's `gform_datepicker_options_pre_init`
  filter (pre-init, so the shared `#ui-datepicker-div` is never painted while
  closed); the same callback captures each field's jQuery-UI `dateFormat` for
  typed-value parsing. Post-init `.datepicker('option', ...)` remains only as
  a fallback, and the CSS ships `#ui-datepicker-div{display:none}` as
  defense-in-depth. The script self-initializes on `gform_post_render` (GF's
  own init pipeline) — there is NO per-form inline bootstrap. Multi-input and
  dropdown presentations are wired by GF's structural part classes
  (`gfield_date_*` / `gfield_date_dropdown_*`).
- `Admin/` — Settings submenu page (under `options-general.php`), admin-post
  handlers (restrictions + the global plugin settings in
  `SavingPluginSettings.php`), section templates, and assets.

## Frontend payload (`window.paxrankGFBlocker`)
Built once, in `wp_footer` (priority 10), only when the page rendered at least
one GF form with recognized date fields (collected via a no-output
`gform_form_tag` filter). Pages without forms run zero restriction queries.

```
{
  siteOffset: -10800,              // site UTC offset, seconds (business clock)
  forms: {                          // ONLY forms rendered on this page
    "5": {
      fields:  [2, 7],              // date fields the server recognizes/enforces
      advance: { "2": 3, "7": 30 }  // advance days, RESOLVED field>form>global
    }
  },
  blockedRanges:       [...],       // rows filtered: global OR rendered forms
  weekdayRestrictions: [...],       // idem (per-day semantics live client-side)
  dateFormat: 'DD/MM/YYYY',         // admin display format (legacy JS fallback)
  i18n: { ... }
}
```
The visual layer never blocks a (form, field) pair absent from `forms[..].fields`
— if the server will not enforce it, the picker must not grey it out.

## Data model
- `wp_paxrank_gf_blocked_dates`: `blocked_date` (start) + `end_date` (NULL =
  single day). A date is blocked when `date BETWEEN blocked_date AND
  COALESCE(end_date, blocked_date)` and the scope matches.
- `wp_paxrank_gf_advance_restrictions`: `advance_days` (resolution: field > form
  > global — most specific wins).
- `wp_paxrank_gf_weekday_restrictions`: `blocked_weekdays` JSON (resolution:
  additive — global + form + field merged).

## Dependencies
- `Shared/DateFormat.php` — date parsing/format + WP-timezone "today".
- `Shared/GravityForms.php` — GFAPI wrappers (`get_input_type()`-based field
  discovery).

No cross-slice dependencies: this is the plugin's only feature slice.

## Hooks / filters provided
- `paxrank_gf_date_blocker_should_load` — filter the frontend load gate.
- `do_action('paxrank_gf_date_blocker_init')` — fired from the bootstrap.

## Hooks consumed (Gravity Forms)
- `gform_validation` — authoritative server-side enforcement.
- `gform_form_tag` — rendered-form collector (no output).
- `gform_after_delete_form` / `gform_after_delete_field` — orphan cleanup.
- JS: `gform_datepicker_options_pre_init`, `gform_post_render`.

## Stable public names (do not rename)
Tables `wp_paxrank_gf_*`, options `paxrank_gf_date_blocker_*`, nonce
`paxrank_gf_date_blocker_nonce`, admin-post actions `admin_post_paxrank_gf_*`,
the AJAX action `wp_ajax_paxrank_gf_get_form_fields` (the only AJAX endpoint —
every write goes through admin-post), JS globals `paxrankGFBlocker` /
`paxrankGFAdmin` / `window.paxrankGFBlockerAPI`. The SHAPE of
`paxrankGFBlocker` (see payload above) is part of that contract: changing it
is a breaking change for integrators.
