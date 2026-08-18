=== Date Picker Blocker for Gravity Forms ===
Contributors: Paxrank
Tags: date picker, booking, blocked dates, form validation, availability
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block dates, ranges, weekdays and minimum advance notice in Gravity Forms date fields, globally or per form and field.

== Description ==

**This plugin requires the Gravity Forms plugin, which is a commercial product sold separately by Rocketgenius, Inc. It does nothing on its own.**

Date Picker Blocker for Gravity Forms restricts which dates visitors can pick in a Gravity Forms date field. It grew out of the needs of travel businesses, built around real client work at [Paxrank](https://paxrank.com), where managing product availability usually calls for finer control than a plain date field offers. That said, nothing about it is travel-specific: it works just as well for any kind of business.

The plugin is completely free, and I have no plans to charge for it. Feel free to use it and adapt it however suits you best. Have a suggestion, or found a bug? Report it in the support forum or through the website, and I will look into it as soon as I get a chance.

= Three kinds of restriction =

* **Blocked dates and ranges.** Block a single day, or a whole range in one record by giving it a start and an end date.
* **Minimum advance notice.** Require bookings to be made at least a set number of days ahead, from 0 to 365. Dates in the past are always blocked, regardless of this setting.
* **Weekdays.** Block one or more days of the week — for example every Sunday.

= Three levels of scope =

Every restriction can be applied globally to all forms, to one specific form, or to one specific date field on one form.

The two ways scopes combine are deliberately different, because the rules mean different things:

* **Advance notice resolves by priority.** The most specific restriction wins — field, then form, then global — and they do not stack.
* **Weekday restrictions are additive.** Every applicable restriction is combined, so the blocked days add up.

= Other things it does =

* Works with all Gravity Forms date field types: date picker, plain text input, and the day/month/year dropdown variant.
* Server-side validation parses each submitted date using that field's own Gravity Forms date format, so a field configured differently from the rest of the site still validates correctly.
* A global date format setting (DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD and dashed variants) controls how dates are shown and entered in the admin.
* Every restriction can carry a description, and the list shows who created it and when.
* Uninstall is opt-in. Your data is kept unless you explicitly tick the box that deletes it.
* Fully translatable, with translations delivered through translate.wordpress.org.

= Developer hooks =

`do_action( 'paxrank_gf_date_blocker_init' );`
`apply_filters( 'paxrank_gf_date_blocker_should_load', bool $should_load );`

== Installation ==

1. Make sure Gravity Forms is installed and active. This plugin does nothing without it.
2. Upload the plugin folder to `/wp-content/plugins/`, or install it through the Plugins screen in WordPress.
3. Activate the plugin. Activation creates the three database tables it needs.
4. Go to **Settings → Date Blocker** in the WordPress admin, or use the Settings link on the plugin's row in the Plugins screen.
5. Set the global **Date Format** to match what your forms display, then add your first restriction.

== Frequently Asked Questions ==

= Does it work with every kind of Gravity Forms date field? =

Yes, the date picker, the plain text input, and date fields that use day/month/year dropdowns. Server-side validation reads each field's own configured date format, so mixed formats across your forms are handled correctly.

= Can I block several dates at once? =

Yes. Leave the end date empty to block a single day, or fill it in to block the whole range from start to end in a single record.

= What happens to dates in the past? =

They are always blocked. The plugin is built for future bookings, and past dates cannot be added from the admin interface either.

= Which date format should I choose? =

The one your forms actually display, since the setting controls how dates are shown and entered in the admin. Server-side validation is independent of it and uses each field's own format, so a field with a different format still validates correctly.

= What happens if Gravity Forms is deactivated? =

The plugin detects it, shows an admin notice, and stays inert. It does not throw errors, and your restrictions are preserved for when Gravity Forms comes back.

= Does the plugin delete my data when I uninstall it? =

Only if you ask it to. Data deletion is opt-in, under Uninstall Options. If you leave that box unchecked, your restrictions and settings survive uninstalling.

= I use a page-caching plugin. Does that affect the blocking? =

Only the visual layer. The restrictions the date picker greys out are printed into the page, so a cached page keeps showing the restrictions that existed when it was cached — until the cache is purged, a newly added restriction may not be greyed out yet (or a removed one may still look blocked). Enforcement is unaffected: form submissions are POST requests, which bypass the page cache, and the server validates every submission against the live restrictions. If you change restrictions often, purge your page cache after saving, or exclude your booking pages from caching.

== Changelog ==

= 1.0.1 =
* Table names in every custom-table query are now bound through the `%i` identifier placeholder of `$wpdb->prepare()` instead of being interpolated into the SQL string.
* Nonce verification in the admin handlers moved inline, so it sits in the same scope as the request data it protects. Behaviour is unchanged.
* Form-scoped frontend queries now bind their form list to a single placeholder, replacing the dynamically built `IN ()` clause.
* Minimum WordPress version raised to 6.2, which is where the `%i` placeholder was introduced.
* Bundled translation files removed. Translations are now delivered through translate.wordpress.org, which generates them for every locale and ships them via the standard update system.

= 1.0.0 =
* Initial release.
* Block specific dates and full date ranges in Gravity Forms date fields.
* Minimum advance notice restrictions, from 0 to 365 days. Past dates are always blocked.
* Weekday restrictions: block one or more days of the week.
* Every restriction can be scoped globally, to one form, or to one date field.
* Works with all three Gravity Forms date presentations: date picker, text input, and day/month/year dropdowns.
* Unavailable dates are greyed out in the date picker, and every submission is re-validated on the server, so the rules hold even without JavaScript.
* Dates are parsed with each field's own Gravity Forms date format, and "today" follows your site's timezone.
* Configurable admin date format, a debug panel, and opt-in data removal on uninstall.
* Fully translatable, with translations delivered through translate.wordpress.org.
