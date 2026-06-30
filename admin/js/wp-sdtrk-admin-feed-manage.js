/**
 * Product-feed management page.
 *
 * Lists published products (paginated, server-side search/status filter) and
 * shows each one's feed status. The mutating handlers (toggle/bulk save via
 * save_feed_exclusion) are wired in the second half of this file.
 *
 * Backed by SDTRK_FeedManage = { ajaxUrl, nonce, perPage, i18n }.
 */
(function ($) {
    'use strict';

    if (typeof SDTRK_FeedManage === 'undefined') {
        return;
    }

    var cfg = SDTRK_FeedManage;
    var i18n = cfg.i18n || {};

    // Current view state.
    var state = {
        search: '',
        status: 'all',
        page: 1,
        perPage: parseInt(cfg.perPage, 10) || 50,
        totalPages: 1,
        totalProducts: 0,
        excludedCount: 0,
        loading: false
    };

    // Cached DOM nodes.
    var $rows, $counter, $search, $statusFilter, $prev, $next, $pageInfo,
        $selectAll, $bulkExclude, $bulkInclude;

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function sprintf(tpl, a, b) {
        return String(tpl)
            .replace('%1$d', a)
            .replace('%2$d', b);
    }

    function ajax(func, data) {
        return $.post(cfg.ajaxUrl, {
            action: 'wp_sdtrk_handle_admin_ajax_callback',
            func: func,
            _nonce: cfg.nonce,
            data: data || {}
        }).then(function (response) {
            return (typeof response === 'string') ? JSON.parse(response) : response;
        });
    }

    function renderCounter() {
        var inFeed = Math.max(0, state.totalProducts - state.excludedCount);
        $counter.text(sprintf(i18n.counter || '%1$d / %2$d', inFeed, state.totalProducts));
    }

    function renderPagination() {
        $pageInfo.text(sprintf(i18n.page || '%1$d / %2$d', state.page, state.totalPages));
        $prev.prop('disabled', state.loading || state.page <= 1);
        $next.prop('disabled', state.loading || state.page >= state.totalPages);
    }

    function rowHtml(p) {
        var img = p.image
            ? '<img src="' + esc(p.image) + '" alt="" width="40" height="40" style="object-fit:cover;border-radius:4px;">'
            : '';
        var checked = p.excluded ? '' : 'checked';
        // The status toggle is a checkbox (checked = in feed); the custom-pages
        // CSS renders it as a switch. Change/bulk wiring lives below.
        return '' +
            '<tr data-product-id="' + esc(p.id) + '" class="' + (p.excluded ? 'is-excluded' : 'is-in-feed') + '">' +
                '<td><input type="checkbox" class="wpsdtrk-feed-select" value="' + esc(p.id) + '"></td>' +
                '<td>' + img + '</td>' +
                '<td>' + esc(p.name) + '</td>' +
                '<td>' + esc(p.sku) + '</td>' +
                '<td>' + esc(p.price) + '</td>' +
                '<td>' +
                    '<label class="wpsdtrk-feed-toggle">' +
                        '<input type="checkbox" class="wpsdtrk-feed-status" ' + checked + '> ' +
                        '<span class="wpsdtrk-feed-status-label">' +
                            (p.excluded ? esc(i18n.excluded || 'Excluded') : esc(i18n.inFeed || 'In feed')) +
                        '</span>' +
                    '</label>' +
                '</td>' +
            '</tr>';
    }

    function renderRows(rows) {
        if (!rows || !rows.length) {
            $rows.html('<tr><td colspan="6">' + esc(i18n.noProducts || 'No products found.') + '</td></tr>');
            return;
        }
        $rows.html(rows.map(rowHtml).join(''));
    }

    function load() {
        state.loading = true;
        renderPagination();
        ajax('list_feed_products', {
            search: state.search,
            status: state.status,
            page: state.page,
            per_page: state.perPage
        }).then(function (r) {
            state.loading = false;
            if (!r || !r.state) {
                $rows.html('<tr><td colspan="6">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
                return;
            }
            state.totalPages = parseInt(r.totalPages, 10) || 1;
            state.totalProducts = parseInt(r.totalProducts, 10) || 0;
            state.excludedCount = parseInt(r.excludedCount, 10) || 0;
            renderRows(r.rows);
            renderCounter();
            renderPagination();
            if ($selectAll) { $selectAll.prop('checked', false); }
        }, function () {
            state.loading = false;
            $rows.html('<tr><td colspan="6">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
            renderPagination();
        });
    }

    // Debounce so each keystroke doesn't fire a server request.
    function debounce(fn, wait) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    $(function () {
        $rows         = $('#wpsdtrk-feed-rows');
        $counter      = $('#wpsdtrk-feed-counter');
        $search       = $('#wpsdtrk-feed-search');
        $statusFilter = $('#wpsdtrk-feed-status-filter');
        $prev         = $('#wpsdtrk-feed-prev');
        $next         = $('#wpsdtrk-feed-next');
        $pageInfo     = $('#wpsdtrk-feed-page-info');
        $selectAll    = $('#wpsdtrk-feed-select-all');
        $bulkExclude  = $('#wpsdtrk-feed-bulk-exclude');
        $bulkInclude  = $('#wpsdtrk-feed-bulk-include');

        // Page is only present when the feed is enabled.
        if (!$rows.length) {
            return;
        }

        $search.on('input', debounce(function () {
            state.search = $(this).val();
            state.page = 1;
            load();
        }, 300));

        $statusFilter.on('change', function () {
            state.status = $(this).val();
            state.page = 1;
            load();
        });

        $prev.on('click', function () {
            if (state.page > 1) { state.page--; load(); }
        });
        $next.on('click', function () {
            if (state.page < state.totalPages) { state.page++; load(); }
        });

        load();
    });

    // Expose for the mutating handlers (toggle/bulk), added below.
    window.__sdtrkFeed = { state: state, ajax: ajax, load: load,
        renderCounter: renderCounter, esc: esc, i18n: i18n };

})(jQuery);
