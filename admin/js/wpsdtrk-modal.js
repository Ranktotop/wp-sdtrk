(function ($) {
    'use strict';

    window.WPSDTRK_Modal = {
        active: null,
        context: {},
        open: function (options) {
            // Erwartete Struktur:
            // {
            //   message: 'Bist du sicher?',
            //   onConfirm: function (ctx) {},
            //   context: {} // optional
            // }

            this.context = options.context || {};
            this.active = options;

            const $modal = $('#wpsdtrk-confirm-modal');
            $modal.find('.wpsdtrk-modal-content p').text(options.message || wp_sdtrk.label_confirm);
            $modal.removeClass('hidden');
        },
        close: function () {
            $('#wpsdtrk-confirm-modal').addClass('hidden');
            this.active = null;
            this.context = {};
        }
    };

    $(document).ready(function () {
        $('.wpsdtrk-cancel-btn').on('click', function () {
            WPSDTRK_Modal.close();
        });

        $('.wpsdtrk-confirm-btn').on('click', function () {
            if (typeof WPSDTRK_Modal.active?.onConfirm === 'function') {
                WPSDTRK_Modal.active.onConfirm(WPSDTRK_Modal.context);
            }
            WPSDTRK_Modal.close();
        });
    });
})(jQuery);
