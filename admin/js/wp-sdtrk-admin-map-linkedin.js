(function ($) {
    'use strict';

    // Clear Form
    function handleClearForm() {
        $('#clear-form-btn').on('click', function () {
            $('#edit-mapping-id').val('');
            $('#edit-event').val('');
            $('#edit-convid').val('');
            $('#rules-container').empty();
        });
    }

    // Add Rule to Form
    function handleAddRule() {
        $(document).on('click', '#add-rule-btn', function () {
            const ruleHtml = `
                <div class="rule-item">
                    <select class="rule-param" name="rules[][param]">
                        <option value="">-- Select Attribute --</option>
                        <option value="prodid">Product-ID</option>
                        <option value="prodname">Product-Name</option>
                    </select>
                    <input type="text" class="rule-value" name="rules[][value]" placeholder="Value (leave blank for any)">
                    <button type="button" class="button button-small remove-rule-btn">× Remove</button>
                </div>
            `;
            $('#rules-container').append(ruleHtml);
        });
    }

    // Remove Rule from Form
    function handleRemoveRule() {
        $(document).on('click', '.remove-rule-btn', function () {
            $(this).closest('.rule-item').remove();
        });
    }

    // Edit Mapping - Load into Form
    function handleEditMapping() {
        $(document).on('click', '.edit-mapping-btn', function () {
            const mappingId = $(this).data('id');
            const $row = $(this).closest('tr');

            // Fill form from table
            $('#edit-mapping-id').val(mappingId);
            // ... rest of loading logic
        });
    }

    // Initialize
    $(document).ready(function () {
        handleClearForm();
        handleAddRule();
        handleRemoveRule();
        handleEditMapping();
    });

})(jQuery);