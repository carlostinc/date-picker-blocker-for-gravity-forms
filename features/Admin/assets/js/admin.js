/**
 * Date Picker Blocker for Gravity Forms - Admin JavaScript.
 *
 * The screen is native forms (admin-post + redirect); this script only
 * covers the parts that are genuinely dynamic: the dependent field
 * selector, the client-side table filters, small UI toggles, and the
 * delete-confirmation dialogs.
 */
jQuery(document).ready(function ($) {
    'use strict';

    /**
     * Look up a string localized by PHP. Returns '' when absent, never undefined.
     *
     * @param {string} key Key in the i18n payload.
     * @return {string}
     */
    function t(key) {
        var strings = (window.paxrankGFAdmin && window.paxrankGFAdmin.i18n) || {};
        return strings[key] || '';
    }

    var ALL_FIELDS_OPTION = $('<option>').val('').text(t('allDateFields')).prop('outerHTML');

    /**
     * Populate a dependent date-field selector for a chosen form.
     */
    function loadFields(formId, $fieldSelect) {
        if (!formId) {
            $fieldSelect.prop('disabled', true).html(ALL_FIELDS_OPTION);
            return;
        }

        $fieldSelect.prop('disabled', false).empty().append($('<option>').val('').text(t('loadingFields')));

        $.post(paxrankGFAdmin.ajaxurl, {
            action: 'paxrank_gf_get_form_fields',
            nonce: paxrankGFAdmin.nonce,
            form_id: formId
        }, function (response) {
            if (response.success) {
                // Build options as DOM nodes with .text() so a Gravity Forms
                // field label containing markup can never become an HTML sink.
                $fieldSelect.empty().append($('<option>').val('').text(t('allDateFields')));
                if (response.data && Object.keys(response.data).length > 0) {
                    $.each(response.data, function (id, label) {
                        $fieldSelect.append($('<option>').val(id).text(label));
                    });
                } else {
                    $fieldSelect.append($('<option>').val('').prop('disabled', true).text(t('noDateFields')));
                }
            } else {
                $fieldSelect.empty().append($('<option>').val('').prop('disabled', true).text(t('loadFieldsError')));
            }
        }).fail(function () {
            $fieldSelect.empty().append($('<option>').val('').prop('disabled', true).text(t('connectionError')));
        });
    }

    $('#gravity-form-id').on('change', function () {
        loadFields($(this).val(), $('#date-field-id'));
    });
    $('#advance-gravity-form-id').on('change', function () {
        loadFields($(this).val(), $('#advance-date-field-id'));
    });
    $('#weekday-gravity-form-id').on('change', function () {
        loadFields($(this).val(), $('#weekday-date-field-id'));
    });

    // Delete links: confirm with the server-rendered, translated message.
    $(document).on('click', '.paxrank-delete-link', function (e) {
        if (!window.confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });

    // Minimum selectable date = today for the blocked-date inputs.
    var now = new Date();
    var todayStr = now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0');
    $('#blocked-date, #blocked-end-date').attr('min', todayStr);

    /**
     * Filter a restrictions table by form scope + free text.
     */
    function filterTable(tableSelector, formFilterId, textFilterId) {
        var formFilter = $(formFilterId).val();
        var textFilter = $(textFilterId).val().toLowerCase();

        $(tableSelector + ' tbody tr').each(function () {
            var row = $(this);
            var scopeText = row.find('td:eq(1)').text().toLowerCase();
            var allText = row.text().toLowerCase();

            var formMatch = true;
            if (formFilter) {
                if (formFilter === 'global') {
                    // Match the badge's own class, not its rendered text: that
                    // is independent of both the icon and the active locale.
                    formMatch = row.find('td:eq(1) .paxrank-badge--global').length > 0;
                } else {
                    var formTitle = $(formFilterId + ' option[value="' + formFilter + '"]').text().toLowerCase();
                    formMatch = scopeText.indexOf(formFilter) !== -1 || (formTitle && scopeText.indexOf(formTitle) !== -1);
                }
            }

            var textMatch = !textFilter || allText.indexOf(textFilter) !== -1;
            row.toggle(formMatch && textMatch);
        });
    }

    function wireFilters(listSelector, formFilterId, textFilterId, clearId) {
        $(formFilterId + ', ' + textFilterId).on('input change', function () {
            filterTable(listSelector, formFilterId, textFilterId);
        });
        $(clearId).on('click', function () {
            $(formFilterId).val('');
            $(textFilterId).val('');
            $(listSelector + ' tbody tr').show();
        });
    }

    wireFilters('.paxrank-blocked-dates-list table', '#blocked-dates-form-filter', '#blocked-dates-text-filter', '#clear-blocked-dates-filters');
    wireFilters('.paxrank-advance-restrictions-list table', '#advance-restrictions-form-filter', '#advance-restrictions-text-filter', '#clear-advance-restrictions-filters');
    wireFilters('.paxrank-weekday-restrictions-list table', '#weekday-restrictions-form-filter', '#weekday-restrictions-text-filter', '#clear-weekday-restrictions-filters');

    // Debug panel toggle.
    $('#enable-debug').on('change', function () {
        $('#debug-information').slideToggle(300);
    });

    // Date format live example.
    $('#global-date-format').on('change', function () {
        var format = $(this).val();
        var d = new Date();
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        var example;
        switch (format) {
            case 'MM/DD/YYYY': example = mm + '/' + dd + '/' + yyyy; break;
            case 'YYYY-MM-DD': example = yyyy + '-' + mm + '-' + dd; break;
            case 'DD-MM-YYYY': example = dd + '-' + mm + '-' + yyyy; break;
            case 'MM-DD-YYYY': example = mm + '-' + dd + '-' + yyyy; break;
            default: example = dd + '/' + mm + '/' + yyyy;
        }
        $('#date-format-example').text(example);
    });
});
