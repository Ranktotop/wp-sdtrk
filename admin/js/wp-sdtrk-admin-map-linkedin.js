(function ($) {
    'use strict';

    // Verfügbare Rule-Optionen
    const availableRules = {
        'prodid': wp_sdtrk.label_product_id,
        'prodname': wp_sdtrk.label_product_name
    };

    // Events die Tags anstelle von Rules verwenden
    const tagBasedEvents = ['button_click', 'element_visible'];

    /**
     * Prüft ob das ausgewählte Event tag-basiert ist
     * @param {string} eventValue - Wert des Event-Dropdowns
     */
    function isTagBasedEvent(eventValue) {
        return tagBasedEvents.includes(eventValue);
    }

    /**
     * Zeigt/versteckt Rules oder Tag Section basierend auf Event
     * @param {string} eventSelector - CSS Selector des Event-Dropdowns
     * @param {string} rulesSectionSelector - CSS Selector der Rules Section
     * @param {string} tagSectionSelector - CSS Selector der Tag Section
     */
    function toggleSections(eventSelector, rulesSectionSelector, tagSectionSelector) {
        const eventValue = $(eventSelector).val();

        if (isTagBasedEvent(eventValue)) {
            $(rulesSectionSelector).hide();
            $(tagSectionSelector).show();
        } else {
            $(rulesSectionSelector).show();
            $(tagSectionSelector).hide();
        }
    }

    /**
     * Prüft welche Rules bereits verwendet werden
     * @param {string} containerSelector - CSS Selector des Containers
     */
    function getUsedRules(containerSelector) {
        const used = [];
        $(`${containerSelector} .rule-param`).each(function () {
            const value = $(this).val();
            if (value) {
                used.push(value);
            }
        });
        return used;
    }

    /**
     * Prüft welche Rules noch verfügbar sind
     * @param {string} containerSelector - CSS Selector des Containers
     */
    function getAvailableRules(containerSelector) {
        const used = getUsedRules(containerSelector);
        const available = {};

        for (const [key, label] of Object.entries(availableRules)) {
            if (!used.includes(key)) {
                available[key] = label;
            }
        }

        return available;
    }

    /**
     * Aktualisiert die Dropdowns aller Rules
     * @param {string} containerSelector - CSS Selector des Containers
     */
    function updateRuleDropdowns(containerSelector) {
        const used = getUsedRules(containerSelector);

        $(`${containerSelector} .rule-item`).each(function () {
            const $select = $(this).find('.rule-param');
            const currentValue = $select.val();

            $select.empty();
            $select.append(`<option value="">${wp_sdtrk.label_dropdown_select}</option>`);

            for (const [key, label] of Object.entries(availableRules)) {
                if (key === currentValue || !used.includes(key)) {
                    const selected = key === currentValue ? 'selected' : '';
                    $select.append(`<option value="${key}" ${selected}>${label}</option>`);
                }
            }
        });
    }

    /**
     * Zeigt/versteckt den "Add Rule" Button
     * @param {string} containerSelector - CSS Selector des Containers
     * @param {string} buttonSelector - CSS Selector des Buttons
     */
    function toggleAddRuleButton(containerSelector, buttonSelector) {
        const available = getAvailableRules(containerSelector);
        const hasAvailable = Object.keys(available).length > 0;

        if (hasAvailable) {
            $(buttonSelector).show();
        } else {
            $(buttonSelector).hide();
        }
    }

    /**
     * Add Rule to Form (generisch)
     * @param {string} buttonSelector - CSS Selector des Add-Buttons
     * @param {string} containerSelector - CSS Selector des Containers
     * @param {string} removeButtonClass - CSS Klasse des Remove-Buttons
     */
    function handleAddRule(buttonSelector, containerSelector, removeButtonClass) {
        $(document).on('click', buttonSelector, function () {
            const available = getAvailableRules(containerSelector);

            if (Object.keys(available).length === 0) {
                alert(wp_sdtrk.msg_no_more_rules || 'All rule types are already in use.');
                return;
            }

            let optionsHtml = `<option value="">${wp_sdtrk.label_dropdown_select}</option>`;
            for (const [key, label] of Object.entries(available)) {
                optionsHtml += `<option value="${key}">${label}</option>`;
            }

            const ruleIndex = Date.now();

            const ruleHtml = `
            <div class="rule-item" data-rule-index="${ruleIndex}">
                <select class="rule-param" name="rules[${ruleIndex}][param]">
                    ${optionsHtml}
                </select>
                <input type="text" class="rule-value" name="rules[${ruleIndex}][value]" placeholder="${wp_sdtrk.placeholder_leave_empty_ignore}">
                <button type="button" class="button button-small ${removeButtonClass}">× ${wp_sdtrk.label_delete}</button>
            </div>
        `;

            $(containerSelector).append(ruleHtml);
            toggleAddRuleButton(containerSelector, buttonSelector);
        });
    }

    /**
     * Remove Rule from Form (generisch)
     * @param {string} buttonClass - CSS Klasse des Remove-Buttons
     * @param {string} containerSelector - CSS Selector des Containers
     * @param {string} addButtonSelector - CSS Selector des Add-Buttons
     */
    function handleRemoveRule(buttonClass, containerSelector, addButtonSelector) {
        $(document).on('click', `.${buttonClass}`, function () {
            $(this).closest('.rule-item').remove();
            updateRuleDropdowns(containerSelector);
            toggleAddRuleButton(containerSelector, addButtonSelector);
        });
    }

    /**
     * Wenn ein Dropdown geändert wird (generisch)
     * @param {string} containerSelector - CSS Selector des Containers
     * @param {string} addButtonSelector - CSS Selector des Add-Buttons
     */
    function handleRuleChange(containerSelector, addButtonSelector) {
        $(document).on('change', `${containerSelector} .rule-param`, function () {
            updateRuleDropdowns(containerSelector);
            toggleAddRuleButton(containerSelector, addButtonSelector);
        });
    }

    /**
     * Delete Mapping
     */
    function wpsdtrk_handle_click_delete_mapping_btn() {
        $('.wpsdtrk_delete_linkedin_mapping_btn').click(function () {
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const mappingId = $btn.data('mapping-id');

            if (!mappingId) return;

            WPSDTRK_Modal.open({
                message: wp_sdtrk.msg_confirm_delete_mapping,
                context: {
                    row: $row,
                    mapping_id: mappingId
                },
                onConfirm: function (ctx) {
                    $.ajax({
                        method: 'POST',
                        url: wp_sdtrk.ajax_url,
                        data: {
                            action: 'wp_sdtrk_handle_admin_ajax_callback',
                            func: 'delete_linkedin_mapping',
                            data: {
                                mapping_id: ctx.mapping_id
                            },
                            meta: {},
                            _nonce: wp_sdtrk._nonce
                        },
                        success: function (response) {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.state) {
                                wpsdtrk_show_notice(wp_sdtrk.notice_success, 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 800);
                            } else {
                                wpsdtrk_show_notice(wp_sdtrk.notice_error + ': ' + result.message, 'error');
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr);
                            wpsdtrk_show_notice(wp_sdtrk.notice_error + ': ' + xhr.status, 'error');
                        }
                    });
                }
            });
        });
    }

    function wpsdtrk_handle_click_edit_mapping_btn() {
        $('.wpsdtrk_edit_mapping_btn').click(function () {
            const mappingId = $(this).data('mapping-id');
            const mappingConvid = $(this).data('mapping-convid') || '';
            var event_name = $(this).data('event') || '';
            var plain_tag = "";

            // if the event_name is tag based, extract plain tag and prefix
            const tag_based_prefixes = ['button_click_', 'element_visible_'];
            for (const prefix of tag_based_prefixes) {
                if (event_name.startsWith(prefix)) {
                    plain_tag = event_name.replace(prefix, '');
                    event_name = event_name.replace("_" + plain_tag, '');
                    break;
                }
            }

            if (!mappingId) return;

            $('#wpsdtrk-edit-mapping-id').val(mappingId);
            $('#sdtrk_edit_mapping_convid').val(mappingConvid);
            $('select[name="sdtrk_edit_mapping_event"]').val(event_name);
            $('#sdtrk_edit_mapping_element_tag').val(plain_tag);

            $.post(wp_sdtrk.ajax_url, {
                action: 'wp_sdtrk_handle_admin_ajax_callback',
                func: 'get_linkedin_mapping',
                data: { mapping_id: mappingId },
                meta: {},
                _nonce: wp_sdtrk._nonce
            }, function (response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;

                if (!result.state || !result.mapping) return;

                const rules = result.mapping.rules;
                const $rulesContainer = $('#edit-rules-container');
                $rulesContainer.empty();

                rules.forEach((rule, index) => {
                    const ruleIndex = Date.now() + index;
                    const ruleHtml = `
                    <div class="rule-item" data-rule-index="${ruleIndex}">
                        <select class="rule-param" name="rules[${ruleIndex}][param]">
                            <option value="">${wp_sdtrk.label_dropdown_select}</option>
                            ${Object.entries(availableRules).map(([key, label]) =>
                        `<option value="${key}" ${key === rule.key ? 'selected' : ''}>${label}</option>`
                    ).join('')}
                        </select>
                        <input type="text" class="rule-value" name="rules[${ruleIndex}][value]" value="${rule.value || ''}" placeholder="${wp_sdtrk.placeholder_leave_empty_ignore}">
                        <button type="button" class="button button-small remove-edit-rule-btn">× ${wp_sdtrk.label_delete}</button>
                    </div>
                    `;
                    $rulesContainer.append(ruleHtml);
                });

                updateRuleDropdowns('#edit-rules-container');
                toggleAddRuleButton('#edit-rules-container', '#add-edit-rule-btn');

                // Toggle sections in edit modal based on event type
                toggleSections('#sdtrk_edit_mapping_event', '.edit-rules-section', '.edit-tag-section');

                wpsdtrk_show_modal('edit-mapping');
            });
        });
    }

    // Initialize
    $(document).ready(function () {
        // CREATE Form - Event change handler
        $('#sdtrk_new_mapping_event').on('change', function () {
            toggleSections('#sdtrk_new_mapping_event', '.rules-section', '.tags-section');
        });

        // EDIT Modal - Event change handler
        $(document).on('change', '#sdtrk_edit_mapping_event', function () {
            toggleSections('#sdtrk_edit_mapping_event', '.edit-rules-section', '.edit-tag-section');
        });

        // CREATE Form
        handleAddRule('#add-rule-btn', '#rules-container', 'remove-rule-btn');
        handleRemoveRule('remove-rule-btn', '#rules-container', '#add-rule-btn');
        handleRuleChange('#rules-container', '#add-rule-btn');

        // EDIT Modal
        handleAddRule('#add-edit-rule-btn', '#edit-rules-container', 'remove-edit-rule-btn');
        handleRemoveRule('remove-edit-rule-btn', '#edit-rules-container', '#add-edit-rule-btn');
        handleRuleChange('#edit-rules-container', '#add-edit-rule-btn');

        wpsdtrk_handle_click_delete_mapping_btn();
        wpsdtrk_handle_click_edit_mapping_btn();

        toggleAddRuleButton('#rules-container', '#add-rule-btn');

        // Initial section toggle on page load
        toggleSections('#sdtrk_new_mapping_event', '.rules-section', '.tags-section');

        // Modal close handler
        $('.wpsdtrk-modal-close').on('click', function () {
            wpsdtrk_close_modal('edit-mapping');
        });
    });

})(jQuery);