(function ($) {
    'use strict';

    // Verfügbare Rule-Optionen
    const availableRules = {
        'prodid': wp_sdtrk.label_product_id,
        'prodname': wp_sdtrk.label_product_name
    };

    /**
     * Prüft welche Rules bereits verwendet werden
     */
    function getUsedRules() {
        const used = [];
        $('#rules-container .rule-param').each(function () {
            const value = $(this).val();
            if (value) {
                used.push(value);
            }
        });
        return used;
    }

    /**
     * Prüft welche Rules noch verfügbar sind
     */
    function getAvailableRules() {
        const used = getUsedRules();
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
     */
    function updateRuleDropdowns() {
        const used = getUsedRules();

        $('#rules-container .rule-item').each(function () {
            const $select = $(this).find('.rule-param');
            const currentValue = $select.val();

            // Dropdown leeren
            $select.empty();

            // "Select" Option hinzufügen
            $select.append(`<option value="">${wp_sdtrk.label_dropdown_select}</option>`);

            // Verfügbare Optionen hinzufügen
            for (const [key, label] of Object.entries(availableRules)) {
                // Aktuelle Auswahl oder nicht verwendete Optionen anzeigen
                if (key === currentValue || !used.includes(key)) {
                    const selected = key === currentValue ? 'selected' : '';
                    $select.append(`<option value="${key}" ${selected}>${label}</option>`);
                }
            }
        });
    }

    /**
     * Zeigt/versteckt den "Add Rule" Button
     */
    function toggleAddRuleButton() {
        const available = getAvailableRules();
        const hasAvailable = Object.keys(available).length > 0;

        if (hasAvailable) {
            $('#add-rule-btn').show();
        } else {
            $('#add-rule-btn').hide();
        }
    }

    /**
     * Add Rule to Form
     */
    function handleAddRule() {
        $(document).on('click', '#add-rule-btn', function () {
            const available = getAvailableRules();

            // Keine verfügbaren Rules mehr
            if (Object.keys(available).length === 0) {
                alert(wp_sdtrk.msg_no_more_rules || 'All rule types are already in use.');
                return;
            }

            // Erstelle Dropdown mit verfügbaren Optionen
            let optionsHtml = `<option value="">${wp_sdtrk.label_dropdown_select}</option>`;
            for (const [key, label] of Object.entries(available)) {
                optionsHtml += `<option value="${key}">${label}</option>`;
            }

            // Eindeutiger Index für jede neue Rule
            const ruleIndex = Date.now(); // Oder: $('#rules-container .rule-item').length

            const ruleHtml = `
            <div class="rule-item" data-rule-index="${ruleIndex}">
                <select class="rule-param" name="rules[${ruleIndex}][param]">
                    ${optionsHtml}
                </select>
                <input type="text" class="rule-value" name="rules[${ruleIndex}][value]" placeholder="${wp_sdtrk.placeholder_leave_empty_ignore}">
                <button type="button" class="button button-small remove-rule-btn">× ${wp_sdtrk.label_delete}</button>
            </div>
        `;

            $('#rules-container').append(ruleHtml);

            // Button-Status aktualisieren
            toggleAddRuleButton();
        });
    }

    /**
     * Remove Rule from Form
     */
    function handleRemoveRule() {
        $(document).on('click', '.remove-rule-btn', function () {
            $(this).closest('.rule-item').remove();

            // Dropdowns aktualisieren
            updateRuleDropdowns();

            // Button-Status aktualisieren
            toggleAddRuleButton();
        });
    }

    /**
     * Wenn ein Dropdown geändert wird
     */
    function handleRuleChange() {
        $(document).on('change', '.rule-param', function () {
            // Alle Dropdowns aktualisieren
            updateRuleDropdowns();

            // Button-Status aktualisieren
            toggleAddRuleButton();
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
                                    location.reload(); // ganze Seite neu laden
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
            const event_name = $(this).data('event') || '';

            if (!mappingId) return;

            // Setze die Mapping-ID ins Hidden-Feld
            $('#wpsdtrk-edit-mapping-id').val(mappingId);

            // Setze conversion id ins Feld
            $('#sdtrk_edit_mapping_convid').val(mappingConvid);

            // Setze event in select
            $('#sdtrk_edit_mapping_event').val(event_name);

            // Lade Rules via AJAX
            $.post(wp_sdtrk.ajax_url, {
                action: 'wp_sdtrk_handle_admin_ajax_callback',
                func: 'get_linkedin_mapping',
                data: { mapping_id: mappingId },
                meta: {},
                _nonce: wp_sdtrk._nonce
            }, function (response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                console.log(result);
                if (!result.state || !result.mapping) return;

                // Hole NUR die Rules
                const rules = result.mapping.rules;

                // Add rules zum Modal
                const $rulesContainer = $('#wpsdtrk-edit-mapping-rules-container');
                $rulesContainer.empty();

                rules.forEach((rule, index) => {
                    const ruleHtml = `
                    <div class="rule-item" data-rule-index="${index}">
                        <label for="rule_${index}_key">${wp_sdtrk.labels.rule_key}</label>
                        <input type="text" id="rule_${index}_key" class="regular-text" value="${esc_html(rule.key)}">
                        <label for="rule_${index}_value">${wp_sdtrk.labels.rule_value}</label>
                        <input type="text" id="rule_${index}_value" class="regular-text" value="${esc_html(rule.value)}">
                    </div>
                    `;
                    $rulesContainer.append(ruleHtml);
                });

                wpsdtrk_show_modal('edit-mapping');
            });
        });
    }

    // Initialize
    $(document).ready(function () {
        handleAddRule();
        handleRemoveRule();
        handleRuleChange();
        wpsdtrk_handle_click_delete_mapping_btn();
        wpsdtrk_handle_click_edit_mapping_btn();

        // Initial Button-Status setzen
        toggleAddRuleButton();
    });

})(jQuery);