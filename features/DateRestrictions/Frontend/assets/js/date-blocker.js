/**
 * Date Picker Blocker for Gravity Forms - Frontend JavaScript
 *
 * Client-side UX layer: disables blocked dates/ranges, past dates, advance
 * window, and blocked weekdays in Gravity Forms date pickers. Server-side
 * validation remains the authoritative enforcement.
 *
 * The script executes decisions the server already resolved: the payload
 * ships per-field advance days, the site's timezone offset, and the list of
 * fields the server recognizes — the client never re-derives that logic.
 * Initialization is self-contained via gform_post_render (Gravity Forms'
 * own init pipeline); there is no per-form inline bootstrap.
 */

(function ($) {
    'use strict';

    var blockedRanges = [];
    var weekdayRestrictions = [];
    var formsMap = {};
    var dateFormat = 'DD/MM/YYYY'; // Legacy fallback; overridden from the payload.
    var siteOffset = null;         // Site UTC offset in seconds (null = browser clock).

    // jQuery UI dateFormat per (form, field), captured from GF's own pre-init
    // options — the exact format the picker will render with.
    var fieldFormats = {};

    var filterRegistered = false;
    var filterHandled = {}; // scopeKey(formId, fieldId) => true when options were injected pre-init.

    /**
     * Load the restriction payload from the inline global.
     *
     * @return {boolean} True when the payload was available.
     */
    function loadRestrictions() {
        if (window.paxrankGFBlocker) {
            blockedRanges = window.paxrankGFBlocker.blockedRanges || [];
            weekdayRestrictions = window.paxrankGFBlocker.weekdayRestrictions || [];
            formsMap = window.paxrankGFBlocker.forms || {};
            dateFormat = window.paxrankGFBlocker.dateFormat || 'DD/MM/YYYY';
            siteOffset = typeof window.paxrankGFBlocker.siteOffset === 'number'
                ? window.paxrankGFBlocker.siteOffset
                : null;
            return true;
        }
        return false;
    }

    if (!loadRestrictions()) {
        $(document).ready(loadRestrictions);
    }

    /**
     * Normalized key for a form/field pair (GF passes numbers, we parse strings).
     *
     * @param {number|string} formId  Form ID.
     * @param {number|string} fieldId Field ID.
     * @return {string}
     */
    function scopeKey(formId, fieldId) {
        return String(formId) + '_' + String(fieldId);
    }

    /**
     * Whether the server recognizes this (form, field) pair as a date field.
     *
     * The visual layer must never block what the server will not enforce, so
     * anything outside the payload's field list is left untouched.
     *
     * @param {number|string} formId  Form ID.
     * @param {number|string} fieldId Field ID.
     * @return {boolean}
     */
    function isRecognizedField(formId, fieldId) {
        var form = formsMap[String(formId)];
        if (!form || !form.fields) {
            return false;
        }
        for (var i = 0; i < form.fields.length; i++) {
            if (String(form.fields[i]) === String(fieldId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Today at midnight in the SITE's timezone (the business clock).
     *
     * Built from the visitor's UTC clock plus the site offset shipped in the
     * payload, so a visitor in another timezone sees the same "today" the
     * server enforces. Falls back to the browser's local date when the
     * payload is absent.
     *
     * @return {Date}
     */
    function getSiteToday() {
        if (typeof siteOffset !== 'number') {
            var now = new Date();
            return new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
        }
        var shifted = new Date(Date.now() + siteOffset * 1000);
        return new Date(shifted.getUTCFullYear(), shifted.getUTCMonth(), shifted.getUTCDate(), 0, 0, 0, 0);
    }

    /**
     * PRIMARY path: inject our options through Gravity Forms' documented
     * `gform_datepicker_options_pre_init` JS filter, BEFORE GF calls
     * `.datepicker(options)`.
     *
     * Why: every post-init `.datepicker('option', ...)` call makes jQuery UI
     * repaint the shared #ui-datepicker-div at the end of <body> while it is
     * closed (its _optionDatepicker always ends in _updateDatepicker). When the
     * datepicker theme CSS is missing/stripped, that painted calendar shows as
     * a full-width block at the page bottom. Injecting the options at init time
     * means the shared div is never painted while hidden.
     *
     * @return {boolean} True when the filter is (already) registered.
     */
    function registerPreInitFilter() {
        if (filterRegistered) {
            return true;
        }
        if (!window.gform || typeof window.gform.addFilter !== 'function') {
            return false;
        }

        window.gform.addFilter('gform_datepicker_options_pre_init', function (optionsObj, formId, fieldId) {
            loadRestrictions();

            // Capture the field's own display format — the very object GF is
            // about to hand to jQuery UI — so typed values are later parsed
            // with the field's format, never the plugin's global setting.
            if (optionsObj && optionsObj.dateFormat) {
                fieldFormats[scopeKey(formId, fieldId)] = optionsObj.dateFormat;
            }

            if (!isRecognizedField(formId, fieldId)) {
                return optionsObj;
            }

            filterHandled[scopeKey(formId, fieldId)] = true;

            var blocking = buildDatepickerOptions(formId, fieldId, optionsObj.beforeShowDay);
            optionsObj.minDate = blocking.minDate;
            optionsObj.beforeShowDay = blocking.beforeShowDay;

            return optionsObj;
        });

        filterRegistered = true;
        return true;
    }

    // Register as early as possible (GF triggers gform_post_render at DOM
    // ready, and ready callbacks run before that trigger), with a DOM-ready
    // retry in case the gform core script evaluates after this one.
    if (!registerPreInitFilter()) {
        $(document).ready(registerPreInitFilter);
    }

    /**
     * Initialize date blocking for a specific form. Idempotent; safe to call
     * on every gform_post_render (AJAX page changes replace the DOM, so the
     * wiring must re-run each time).
     *
     * @param {number} formId Gravity Forms form ID.
     */
    function initForm(formId) {
        loadRestrictions();
        registerPreInitFilter();
        setupDateBlockingForForm(formId);
    }

    /**
     * Wire up blocking for every date field in a form.
     *
     * @param {number} formId Gravity Forms form ID.
     */
    function setupDateBlockingForForm(formId) {
        var formElement = $('#gform_' + formId);
        if (formElement.length === 0) {
            return;
        }

        formElement.find('input[type="date"], input.datepicker, input.hasDatepicker').each(function () {
            var field = $(this);
            setupDateFieldBlocking(field, formId, getFieldIdFromInput(field));
        });

        setupMultiPartDateFields(formElement, formId);
    }

    /**
     * Wire up blocking for a single-input date field.
     *
     * @param {jQuery} field   Input element.
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     */
    function setupDateFieldBlocking(field, formId, fieldId) {
        if (!field || field.length === 0 || !formId || !fieldId) {
            return;
        }
        if (!field.is('input') || !$.contains(document, field[0])) {
            return;
        }
        if (!isRecognizedField(formId, fieldId)) {
            return;
        }

        if (field.is('[type="date"]')) {
            setupHTML5DateBlocking(field, formId, fieldId);
        }
        if (field.hasClass('datepicker') || field.hasClass('hasDatepicker')) {
            setupDatepickerBlocking(field, formId, fieldId);
        }

        // .off first: initForm re-runs on every gform_post_render, and the
        // same element must never accumulate handlers.
        field.off('change.paxrankBlocker').on('change.paxrankBlocker', function (e) {
            if (e.paxrankInternal) {
                return;
            }
            var $field = $(this);
            setTimeout(function () {
                validateSelectedDate($field, formId, fieldId);
            }, 50);
        });
    }

    /**
     * Wire the multi-input and dropdown date presentations of a form.
     *
     * These have no single input to hook, so each day/month/year part gets a
     * change handler; once all three parts hold values the combined date is
     * validated and cleared if blocked. Parts are identified by Gravity
     * Forms' structural container classes, not by visual order.
     *
     * @param {jQuery} formElement The form element.
     * @param {number} formId      Form ID.
     */
    function setupMultiPartDateFields(formElement, formId) {
        var partContainers = '.gfield_date_month, .gfield_date_day, .gfield_date_year, ' +
            '.gfield_date_dropdown_month, .gfield_date_dropdown_day, .gfield_date_dropdown_year';

        formElement.find(partContainers).find('input, select').each(function () {
            var part = $(this);

            part.off('change.paxrankBlocker').on('change.paxrankBlocker', function (e) {
                if (e.paxrankInternal) {
                    return;
                }
                var self = $(this);
                var gfield = self.closest('.gfield');

                // Debounce per FIELD: filling day+month+year fires three
                // changes; without coalescing, the validation triggered by the
                // first one clears the parts and the later ones — seeing the
                // now-empty field — would hide the just-shown message.
                var pending = gfield.data('paxrankPartTimer');
                if (pending) {
                    clearTimeout(pending);
                }
                gfield.data('paxrankPartTimer', setTimeout(function () {
                    gfield.removeData('paxrankPartTimer');
                    validateMultiPartDate(self, formId);
                }, 80));
            });
        });
    }

    /**
     * Validate the combined value of a multi-part date field.
     *
     * @param {jQuery} part   The part (input/select) that changed.
     * @param {number} formId Form ID.
     */
    function validateMultiPartDate(part, formId) {
        var gfield = part.closest('.gfield');
        if (gfield.length === 0) {
            return;
        }

        var idMatch = (gfield.attr('id') || '').match(/field_\d+_(\d+)/);
        var fieldId = idMatch ? idMatch[1] : null;
        if (!fieldId || !isRecognizedField(formId, fieldId)) {
            return;
        }

        var month = gfield.find('.gfield_date_month input, .gfield_date_dropdown_month select').val();
        var day = gfield.find('.gfield_date_day input, .gfield_date_dropdown_day select').val();
        var year = gfield.find('.gfield_date_year input, .gfield_date_dropdown_year select').val();

        var anchor = gfield.find('.gfield_date_year input, .gfield_date_dropdown_year select').last();
        if (anchor.length === 0) {
            anchor = part;
        }

        if (!month || !day || !year) {
            hideBlockedDateMessage(anchor);
            return;
        }

        var dateString = partsToYmd(year, month, day);
        if (!dateString) {
            hideBlockedDateMessage(anchor);
            return;
        }

        if (isDateBlocked(dateString, formId, fieldId)) {
            gfield.find(partContainerInputs()).each(function () {
                var el = $(this);
                el.val('');
                var evt = $.Event('change');
                evt.paxrankInternal = true;
                el.trigger(evt);
            });
            showBlockedDateMessage(anchor, getBlockedDateMessage(dateString, formId, fieldId));
            return;
        }

        hideBlockedDateMessage(anchor);
    }

    /**
     * Selector for the inputs/selects of a multi-part date field.
     *
     * @return {string}
     */
    function partContainerInputs() {
        return '.gfield_date_month input, .gfield_date_day input, .gfield_date_year input, ' +
            '.gfield_date_dropdown_month select, .gfield_date_dropdown_day select, .gfield_date_dropdown_year select';
    }

    /**
     * Build a validated Y-m-d string from year/month/day parts.
     *
     * @param {string|number} year  Year part.
     * @param {string|number} month Month part.
     * @param {string|number} day   Day part.
     * @return {string|false}
     */
    function partsToYmd(year, month, day) {
        var y = parseInt(year, 10);
        var m = parseInt(month, 10);
        var d = parseInt(day, 10);

        if (!y || !m || !d) {
            return false;
        }

        var test = new Date(y, m - 1, d);
        if (test.getFullYear() !== y || test.getMonth() !== m - 1 || test.getDate() !== d) {
            return false;
        }

        return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    /**
     * Blocking for HTML5 date inputs (min attribute + clear-on-change).
     *
     * @param {jQuery} field   Input element.
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     */
    function setupHTML5DateBlocking(field, formId, fieldId) {
        var advanceDays = getAdvanceBookingDays(formId, fieldId);
        var minDate = advanceDays > 0 ? getMinimumAllowedDate(formId, fieldId) : getSiteToday();
        field.attr('min', formatDateForComparison(minDate));
    }

    /**
     * Build the jQuery UI options this plugin adds to a datepicker
     * (minDate and a beforeShowDay that respects any previous one).
     *
     * @param {number|string} formId               Form ID.
     * @param {number|string} fieldId              Field ID.
     * @param {Function}      [previousBeforeShowDay] Pre-existing beforeShowDay to chain.
     * @return {{minDate: Date, beforeShowDay: Function}}
     */
    function buildDatepickerOptions(formId, fieldId, previousBeforeShowDay) {
        var advanceDays = getAdvanceBookingDays(formId, fieldId);
        var minDate = advanceDays > 0 ? getMinimumAllowedDate(formId, fieldId) : getSiteToday();

        return {
            minDate: minDate,
            beforeShowDay: function (date) {
                var dateString = formatDateForComparison(date);
                if (!dateString) {
                    return [true, '', ''];
                }

                var previousResult = [true, '', ''];
                if (typeof previousBeforeShowDay === 'function') {
                    try {
                        previousResult = previousBeforeShowDay(date);
                        if (!Array.isArray(previousResult) || previousResult.length < 3) {
                            previousResult = [true, '', ''];
                        }
                    } catch (e) {
                        previousResult = [true, '', ''];
                    }
                }

                if (isDateBlocked(dateString, formId, fieldId)) {
                    return [false, 'paxrank-blocked-date', getBlockedDateMessage(dateString, formId, fieldId)];
                }

                return previousResult;
            }
        };
    }

    /**
     * FALLBACK path: apply options after init, only for pickers that were
     * initialized before our pre-init filter was registered.
     *
     * Note: a post-init `.datepicker('option', ...)` call repaints the shared
     * #ui-datepicker-div while closed; the plugin stylesheet keeps it hidden.
     * The options are batched into ONE call (one repaint, not two).
     *
     * @param {jQuery}        field   Input element.
     * @param {number|string} formId  Form ID.
     * @param {number|string} fieldId Field ID.
     */
    function setupDatepickerBlocking(field, formId, fieldId) {
        if (filterHandled[scopeKey(formId, fieldId)]) {
            return; // Options were already injected at init time.
        }

        if (!field.hasClass('hasDatepicker')) {
            // Wait for Gravity Forms to initialize the picker, then re-check.
            setTimeout(function () {
                if (!filterHandled[scopeKey(formId, fieldId)] && field.hasClass('hasDatepicker')) {
                    setupDatepickerBlocking(field, formId, fieldId);
                }
            }, 500);
            return;
        }

        // Capture the field's format here too: this path runs when the
        // pre-init filter never saw the picker.
        try {
            var uiFormat = field.datepicker('option', 'dateFormat');
            if (uiFormat) {
                fieldFormats[scopeKey(formId, fieldId)] = uiFormat;
            }
        } catch (e) {
            // Not a jQuery UI datepicker after all.
        }

        var blocking = buildDatepickerOptions(formId, fieldId, field.datepicker('option', 'beforeShowDay'));

        field.datepicker('option', {
            minDate: blocking.minDate,
            beforeShowDay: blocking.beforeShowDay
        });
    }

    /**
     * Whether a range restriction applies to the current form/field scope.
     *
     * @param {Object} range   Range entry {start,end,form_id,field_id}.
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     * @return {boolean}
     */
    function rangeApplies(range, formId, fieldId) {
        if (range.form_id === null && range.field_id === null) {
            return true;
        }
        if (range.form_id == formId && range.field_id === null) {
            return true;
        }
        return range.form_id == formId && range.field_id == fieldId;
    }

    /**
     * Whether a Y-m-d date is inside any applicable blocked range.
     *
     * @param {string} dateString Y-m-d date.
     * @param {number} formId     Form ID.
     * @param {number} fieldId    Field ID.
     * @return {boolean}
     */
    function isInBlockedRange(dateString, formId, fieldId) {
        for (var i = 0; i < blockedRanges.length; i++) {
            var r = blockedRanges[i];
            if (dateString >= r.start && dateString <= r.end && rangeApplies(r, formId, fieldId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a date is blocked for any reason (range, past/advance, weekday).
     *
     * @param {string} dateString Y-m-d date.
     * @param {number} formId     Form ID.
     * @param {number} fieldId    Field ID.
     * @return {boolean}
     */
    function isDateBlocked(dateString, formId, fieldId) {
        if (isDateTooSoon(dateString, formId, fieldId)) {
            return true;
        }
        if (isWeekdayBlocked(dateString, formId, fieldId)) {
            return true;
        }
        return isInBlockedRange(dateString, formId, fieldId);
    }

    /**
     * Whether a date is in the past or within the advance-booking window.
     *
     * "Today" is the site's date, not the visitor's — bookings follow the
     * business clock.
     *
     * @param {string} dateString Date string.
     * @param {number} formId     Form ID.
     * @param {number} fieldId    Field ID.
     * @return {boolean}
     */
    function isDateTooSoon(dateString, formId, fieldId) {
        var normalized = normalizeDateString(dateString);
        if (!normalized) {
            return false;
        }

        var parts = normalized.split('-');
        if (parts.length !== 3) {
            return false;
        }

        var selected = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 0, 0, 0, 0);
        var today = getSiteToday();

        if (selected < today) {
            return true;
        }

        var advanceDays = getAdvanceBookingDays(formId, fieldId);
        if (advanceDays === 0) {
            return false;
        }

        var minAllowed = new Date(today);
        minAllowed.setDate(minAllowed.getDate() + advanceDays);

        return selected < minAllowed;
    }

    /**
     * Whether a date's weekday is blocked (additive: global + form + field).
     *
     * @param {string} dateString Date string.
     * @param {number} formId     Form ID.
     * @param {number} fieldId    Field ID.
     * @return {boolean}
     */
    function isWeekdayBlocked(dateString, formId, fieldId) {
        var normalized = normalizeDateString(dateString);
        if (!normalized) {
            return false;
        }

        var parts = normalized.split('-');
        if (parts.length !== 3) {
            return false;
        }

        var weekday = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)).getDay();
        var blocked = [];

        for (var i = 0; i < weekdayRestrictions.length; i++) {
            var restriction = weekdayRestrictions[i];
            var applies =
                (restriction.form_id === null && restriction.field_id === null) ||
                (restriction.form_id == formId && restriction.field_id === null) ||
                (restriction.form_id == formId && restriction.field_id == fieldId);

            if (applies) {
                try {
                    blocked = blocked.concat(JSON.parse(restriction.blocked_weekdays));
                } catch (e) {
                    // Ignore malformed data.
                }
            }
        }

        return blocked.indexOf(weekday) !== -1;
    }

    /**
     * Effective advance-booking days for a form/field.
     *
     * A plain read: the server already resolved the field > form > global
     * priority and shipped the number per field, so client and server can
     * never disagree.
     *
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     * @return {number}
     */
    function getAdvanceBookingDays(formId, fieldId) {
        var form = formsMap[String(formId)];
        if (!form || !form.advance) {
            return 0;
        }
        var days = form.advance[String(fieldId)];
        return typeof days === 'number' ? days : (parseInt(days, 10) || 0);
    }

    /**
     * The earliest selectable date given advance-booking restrictions.
     *
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     * @return {Date}
     */
    function getMinimumAllowedDate(formId, fieldId) {
        var minDate = getSiteToday();
        minDate.setDate(minDate.getDate() + getAdvanceBookingDays(formId, fieldId));
        return minDate;
    }

    /**
     * GF dateFormat token => semantic order of the three date parts.
     * Mirrors the format tokens Gravity Forms defines for its date fields
     * (the server parses them with GFCommon::parse_date()).
     */
    var GF_TOKEN_ORDERS = {
        mdy: ['m', 'd', 'y'],
        dmy: ['d', 'm', 'y'],
        dmy_dash: ['d', 'm', 'y'],
        dmy_dot: ['d', 'm', 'y'],
        ymd_slash: ['y', 'm', 'd'],
        ymd_dash: ['y', 'm', 'd'],
        ymd_dot: ['y', 'm', 'd']
    };

    /**
     * The GF dateFormat token carried as a CSS class on the datepicker input
     * (GF renders class='datepicker gform-datepicker {format} ...').
     *
     * This is what covers values typed BEFORE the picker's lazy init: modern
     * GF only initializes jQuery UI on first interaction, so neither the
     * pre-init capture nor the live picker option exist yet — the class does.
     *
     * @param {jQuery} field Input element.
     * @return {string|null}
     */
    function tokenFromInput(field) {
        var classes = (field.attr('class') || '').split(/\s+/);
        for (var i = 0; i < classes.length; i++) {
            if (Object.prototype.hasOwnProperty.call(GF_TOKEN_ORDERS, classes[i])) {
                return classes[i];
            }
        }
        return null;
    }

    /**
     * Parse a value using a GF dateFormat token's semantic part order.
     *
     * @param {string} token GF dateFormat token.
     * @param {string} value Raw value.
     * @return {string|false}
     */
    function parseWithToken(token, value) {
        var order = GF_TOKEN_ORDERS[token];
        if (!order) {
            return false;
        }

        var parts = value.split(/[/.\-]/);
        if (parts.length !== 3) {
            return false;
        }

        var map = {};
        for (var i = 0; i < 3; i++) {
            map[order[i]] = parseInt(parts[i], 10);
        }

        return partsToYmd(map.y, map.m, map.d);
    }

    /**
     * Parse a typed/selected single-input value to Y-m-d using the FIELD's
     * own format.
     *
     * Resolution order: ISO (native date inputs always hold Y-m-d), the
     * format captured from GF's pre-init options, the live picker option,
     * the token class GF prints on the input, and only then the plugin's
     * global format as a legacy fallback.
     *
     * @param {jQuery}        field   Input element.
     * @param {number|string} formId  Form ID.
     * @param {number|string} fieldId Field ID.
     * @param {string}        value   Raw value.
     * @return {string|false}
     */
    function parseTypedDate(field, formId, fieldId, value) {
        value = String(value).trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return isNaN(new Date(value + 'T00:00:00').getTime()) ? false : value;
        }

        var uiFormat = fieldFormats[scopeKey(formId, fieldId)];

        if (!uiFormat && field.hasClass('hasDatepicker')) {
            try {
                uiFormat = field.datepicker('option', 'dateFormat');
            } catch (e) {
                uiFormat = null;
            }
        }

        if (uiFormat && $.datepicker && typeof $.datepicker.parseDate === 'function') {
            try {
                return formatDateForComparison($.datepicker.parseDate(uiFormat, value));
            } catch (e) {
                return false; // Strict: the value does not match the field's own format.
            }
        }

        var token = tokenFromInput(field);
        if (token) {
            return parseWithToken(token, value);
        }

        return normalizeDateString(value);
    }

    /**
     * Validate the currently selected date, clearing + messaging if blocked.
     *
     * @param {jQuery} field   Input element.
     * @param {number} formId  Form ID.
     * @param {number} fieldId Field ID.
     * @return {boolean}
     */
    function validateSelectedDate(field, formId, fieldId) {
        var selectedDate = field.val();
        if (!selectedDate) {
            hideBlockedDateMessage(field);
            return true;
        }

        var normalized = parseTypedDate(field, formId, fieldId, selectedDate);
        if (!normalized) {
            hideBlockedDateMessage(field);
            return true;
        }

        if (isDateBlocked(normalized, formId, fieldId)) {
            field.val('');

            // Marked synthetic change so Gravity Forms' conditional logic and
            // calculations see the cleared value; our own handler ignores it
            // (a bare trigger would re-enter and wipe the message below).
            var evt = $.Event('change');
            evt.paxrankInternal = true;
            field.trigger(evt);

            showBlockedDateMessage(field, getBlockedDateMessage(normalized, formId, fieldId));
            return false;
        }

        hideBlockedDateMessage(field);
        return true;
    }

    /**
     * Human-readable reason a date is blocked.
     *
     * @param {string} dateString Y-m-d date.
     * @param {number} formId     Form ID.
     * @param {number} fieldId    Field ID.
     * @return {string}
     */
    function getBlockedDateMessage(dateString, formId, fieldId) {
        var t = (window.paxrankGFBlocker && window.paxrankGFBlocker.i18n) || {};
        var unavailable = t.unavailable || '';

        if (isInBlockedRange(dateString, formId, fieldId)) {
            return unavailable;
        }

        if (isWeekdayBlocked(dateString, formId, fieldId)) {
            var names = t.weekdays || [];
            var parts = normalizeDateString(dateString).split('-');
            var wd = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)).getDay();
            return (t.weekdayBlocked || '').replace('%s', names[wd] || '');
        }

        if (isDateTooSoon(dateString, formId, fieldId)) {
            var np = normalizeDateString(dateString).split('-');
            var selected = new Date(parseInt(np[0], 10), parseInt(np[1], 10) - 1, parseInt(np[2], 10), 0, 0, 0, 0);

            if (selected < getSiteToday()) {
                return t.pastDate || unavailable;
            }

            var advanceDays = getAdvanceBookingDays(formId, fieldId);
            var template = advanceDays === 1 ? t.advanceOne : t.advanceMany;
            return (template || '').replace('%d', advanceDays);
        }

        return unavailable;
    }

    /**
     * Show the blocked-date message under a field.
     *
     * @param {jQuery} field   Input element.
     * @param {string} message Message text.
     */
    function showBlockedDateMessage(field, message) {
        message = message || ((window.paxrankGFBlocker && window.paxrankGFBlocker.i18n) || {}).unavailable || '';

        var container = field.closest('.gfield');
        var existing = container.find('.paxrank-blocked-date-message');

        if (existing.length === 0) {
            field.after($('<div class="paxrank-blocked-date-message"></div>').text(message));
        } else {
            existing.text(message);
        }

        field.addClass('paxrank-blocked-date-input');
        setTimeout(function () {
            field.focus();
        }, 100);
    }

    /**
     * Remove the blocked-date message from a field.
     *
     * @param {jQuery} field Input element.
     */
    function hideBlockedDateMessage(field) {
        field.closest('.gfield').find('.paxrank-blocked-date-message').remove();
        field.removeClass('paxrank-blocked-date-input');
    }

    /**
     * Extract the Gravity Forms field ID from an input's id attribute.
     *
     * @param {jQuery} field Input element.
     * @return {number|null}
     */
    function getFieldIdFromInput(field) {
        var inputId = field.attr('id') || '';
        var matches = inputId.match(/input_\d+_(\d+)/);
        if (matches && matches[1]) {
            return matches[1];
        }
        matches = inputId.match(/input_(\d+)/);
        return matches && matches[1] ? matches[1] : null;
    }

    /**
     * Extract the Gravity Forms form ID from an input's enclosing form.
     *
     * @param {jQuery} field Input element.
     * @return {string|null}
     */
    function getFormIdFromInput(field) {
        var formElement = field.closest('form[id^="gform_"]');
        return formElement.length > 0 ? formElement.attr('id').replace('gform_', '') : null;
    }

    /**
     * Format a Date object as Y-m-d.
     *
     * @param {Date} date Date object.
     * @return {string|null}
     */
    function formatDateForComparison(date) {
        if (!date || isNaN(date.getTime())) {
            return null;
        }
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0');
    }

    /**
     * Normalize a display-format date string to Y-m-d using the plugin's
     * global format (legacy fallback; per-field parsing lives in
     * parseTypedDate).
     *
     * @param {string} dateString Raw date string.
     * @return {string|false}
     */
    function normalizeDateString(dateString) {
        if (!dateString) {
            return false;
        }
        dateString = dateString.trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
            return isNaN(new Date(dateString + 'T00:00:00').getTime()) ? false : dateString;
        }

        var parts;
        var day;
        var month;
        var year;

        switch (dateFormat) {
            case 'DD/MM/YYYY':
                parts = dateString.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (parts) { day = +parts[1]; month = +parts[2]; year = +parts[3]; }
                break;
            case 'MM/DD/YYYY':
                parts = dateString.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (parts) { month = +parts[1]; day = +parts[2]; year = +parts[3]; }
                break;
            case 'DD-MM-YYYY':
                parts = dateString.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                if (parts) { day = +parts[1]; month = +parts[2]; year = +parts[3]; }
                break;
            case 'MM-DD-YYYY':
                parts = dateString.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                if (parts) { month = +parts[1]; day = +parts[2]; year = +parts[3]; }
                break;
            case 'YYYY-MM-DD':
                parts = dateString.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
                if (parts) { year = +parts[1]; month = +parts[2]; day = +parts[3]; }
                break;
            default:
                return false;
        }

        if (!parts || !day || !month || !year) {
            return false;
        }
        if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2100) {
            return false;
        }

        var test = new Date(year, month - 1, day);
        if (test.getFullYear() !== year || test.getMonth() !== month - 1 || test.getDate() !== day) {
            return false;
        }

        return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    }

    /**
     * Re-apply restrictions to all date fields (e.g. after data changes).
     */
    function updateDateRestrictions() {
        loadRestrictions();

        $('input[type="date"], input.datepicker, input.hasDatepicker').each(function () {
            var field = $(this);
            var formId = getFormIdFromInput(field);
            var fieldId = getFieldIdFromInput(field);
            if (!formId || !fieldId || !isRecognizedField(formId, fieldId)) {
                return;
            }

            var minDate = getMinimumAllowedDate(formId, fieldId);
            if (field.is('[type="date"]')) {
                field.attr('min', formatDateForComparison(minDate));
            }
            if (field.hasClass('hasDatepicker')) {
                field.datepicker('option', 'minDate', minDate);
            }
        });
    }

    // Self-initialization: gform_post_render is Gravity Forms' own init
    // pipeline, fired for every rendered form (including AJAX page changes
    // and forms injected later that run GF's init), so no per-form inline
    // bootstrap is needed.
    $(document).on('gform_post_render', function (event, formId) {
        var id = parseInt(formId, 10);
        if (id) {
            initForm(id);
        }
    });

    // Cheap safety net for forms whose render event fired before this script
    // evaluated (initForm is idempotent).
    $(function () {
        $('form[id^="gform_"]').each(function () {
            var id = parseInt(($(this).attr('id') || '').replace('gform_', ''), 10);
            if (id) {
                initForm(id);
            }
        });
    });

    // Public API (stable name).
    window.paxrankGFBlockerAPI = {
        initForm: initForm,
        isDateBlocked: isDateBlocked,
        isWeekdayBlocked: isWeekdayBlocked,
        validateSelectedDate: validateSelectedDate,
        updateDateRestrictions: updateDateRestrictions,
        getMinimumAllowedDate: getMinimumAllowedDate
    };

})(jQuery);
