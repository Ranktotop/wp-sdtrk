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

    // Attribute-context escape: esc() encodes < > &, but not quotes — add them
    // so a value can be placed safely inside a double-quoted HTML attribute.
    function escAttr(value) {
        return esc(value).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function sprintf(tpl, a, b) {
        return String(tpl)
            .replace('%1$d', a)
            .replace('%2$d', b);
    }

    // "%s" single-token substitution for badge tooltips.
    function sprintfS(tpl, a) {
        return String(tpl).replace('%s', a);
    }

    function fmtMB(bytes) {
        var mb = (parseInt(bytes, 10) || 0) / (1024 * 1024);
        return (Math.round(mb * 10) / 10) + ' MB';
    }

    // A red hover icon carrying the reason a field is highlighted (hard feed error).
    function fieldIcon(title) {
        return ' <span class="wpsdtrk-field-icon wpsdtrk-field-icon-error"' +
            ' title="' + escAttr(title) + '" aria-label="' + escAttr(title) + '">⛔</span>';
    }

    // The image's problems (against Meta's constraints) as tooltip lines; [] when ok.
    function imageIssues(p) {
        var img = p.image_status || {};
        var issues = img.issues || [];
        var msgs = [];
        if (issues.indexOf('no_image') !== -1) {
            msgs.push(i18n.imgNoImage || 'No image');
        }
        if (issues.indexOf('too_small') !== -1) {
            msgs.push(sprintfS(i18n.imgTooSmallTip || 'Image %s px — below 500×500',
                (img.width || 0) + '×' + (img.height || 0)));
        }
        if (issues.indexOf('too_large') !== -1) {
            msgs.push(sprintfS(i18n.imgTooLargeTip || 'Image %s — above 8 MB', fmtMB(img.size)));
        }
        return msgs;
    }

    // The description's problems as tooltip lines; [] when ok.
    function descriptionIssues(p) {
        var d = p.description_status || {};
        var issues = d.issues || [];
        var msgs = [];
        if (issues.indexOf('no_description') !== -1) {
            msgs.push(i18n.descMissing || 'Description is empty');
        }
        if (issues.indexOf('too_long') !== -1) {
            msgs.push(sprintfS(i18n.descTooLongTip || 'Description too long (%s chars, max 5000)', d.length || 0));
        }
        return msgs;
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
            ? '<img src="' + escAttr(p.image) + '" alt="" width="40" height="40" style="object-fit:cover;border-radius:4px;">'
            : '';
        var checked = p.excluded ? '' : 'checked';

        // Per-field problems: the offending cell gets .wpsdtrk-cell-error (bright
        // red) plus a hover icon explaining why; the whole row gets a subtle red
        // tint. Only hard feed errors count here (image / SKU / price /
        // description). A missing Google category is not flagged in this list —
        // unmapped categories are highlighted in the mapping panel below instead.
        var imgMsgs   = imageIssues(p);
        var descMsgs  = descriptionIssues(p);
        var imgErr    = imgMsgs.length > 0;
        var skuErr    = !!p.sku_missing;
        var priceErr  = !!p.price_missing;
        var descErr   = descMsgs.length > 0;
        var hasError  = imgErr || skuErr || priceErr || descErr;

        var rowClass = (p.excluded ? 'is-excluded' : 'is-in-feed') + (hasError ? ' wpsdtrk-has-error' : '');

        var imgCell = '<td' + (imgErr ? ' class="wpsdtrk-cell-error"' : '') + '>' +
            img + (imgErr ? fieldIcon(imgMsgs.join(' · ')) : '') + '</td>';
        // Product name links to its edit screen (new tab) so problems can be
        // fixed quickly. Falls back to plain text if there's no edit URL.
        var nameCell = '<td>' + (p.edit_url
            ? '<a class="wpsdtrk-feed-name-link" href="' + escAttr(p.edit_url) + '" target="_blank" rel="noopener noreferrer">' + esc(p.name) + '</a>'
            : esc(p.name)) + '</td>';
        var skuCell = '<td' + (skuErr ? ' class="wpsdtrk-cell-error"' : '') + '>' +
            esc(p.sku) + (skuErr ? fieldIcon(i18n.skuMissing || 'SKU is empty') : '') + '</td>';
        var priceCell = '<td' + (priceErr ? ' class="wpsdtrk-cell-error"' : '') + '>' +
            esc(p.price) + (priceErr ? fieldIcon(i18n.priceZero || 'Price is 0') : '') + '</td>';
        var descCell = '<td class="wpsdtrk-feed-desc' + (descErr ? ' wpsdtrk-cell-error' : '') + '">' +
            '<span class="wpsdtrk-feed-desc-text">' + esc(p.description_preview) + '</span>' +
            (descErr ? fieldIcon(descMsgs.join(' · ')) : '') + '</td>';

        // The status toggle is a checkbox (checked = in feed); the custom-pages
        // CSS renders it as a switch. Change/bulk wiring lives below.
        return '' +
            '<tr data-product-id="' + esc(p.id) + '" class="' + rowClass + '">' +
                '<td><input type="checkbox" class="wpsdtrk-feed-select" value="' + esc(p.id) + '"></td>' +
                imgCell +
                nameCell +
                descCell +
                priceCell +
                skuCell +
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
            $rows.html('<tr><td colspan="7">' + esc(i18n.noProducts || 'No products found.') + '</td></tr>');
            return;
        }
        $rows.html(rows.map(rowHtml).join(''));
    }

    function notice(message, type) {
        if (typeof window.wpsdtrk_show_notice === 'function') {
            window.wpsdtrk_show_notice(message, type);
        }
    }

    // Reflect a product's feed status in its row (label + class + toggle).
    function applyRowState($tr, excluded) {
        $tr.toggleClass('is-excluded', excluded).toggleClass('is-in-feed', !excluded);
        $tr.find('.wpsdtrk-feed-status').prop('checked', !excluded);
        $tr.find('.wpsdtrk-feed-status-label')
            .text(excluded ? (i18n.excluded || 'Excluded') : (i18n.inFeed || 'In feed'));
    }

    function updateBulkButtons() {
        var any = $rows.find('.wpsdtrk-feed-select:checked').length > 0;
        $bulkExclude.prop('disabled', !any);
        $bulkInclude.prop('disabled', !any);
    }

    // Persist a set of {id, excluded} changes; reconcile counters from the
    // authoritative response. onFail rolls the optimistic UI back. Returns the
    // promise so callers can re-enable controls once the request settles.
    function save(changes, onFail) {
        return ajax('save_feed_exclusion', { changes: changes }).then(function (r) {
            if (!r || !r.state) {
                if (onFail) { onFail(); }
                notice((r && r.message) || i18n.saveError || 'Could not save the change.', 'error');
                return;
            }
            state.totalProducts = parseInt(r.totalProducts, 10) || state.totalProducts;
            state.excludedCount = parseInt(r.excludedCount, 10) || 0;
            renderCounter();
            notice(r.message || i18n.saved || 'Saved.', 'success');
            // With an active status filter the changed row(s) may no longer match
            // it; refresh from the server so the list stays consistent.
            if (state.status !== 'all') {
                load();
            }
        }, function () {
            if (onFail) { onFail(); }
            notice(i18n.saveError || 'Could not save the change.', 'error');
        });
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
                $rows.html('<tr><td colspan="7">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
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
            $rows.html('<tr><td colspan="7">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
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

    /* -----------------------------------------------------------------------
     * Google product category mapping panel (lazy — only touched when opened)
     * --------------------------------------------------------------------- */
    function initGpcPanel() {
        var $panel = $('#wpsdtrk-gpc-panel');
        if (!$panel.length) {
            return;
        }
        var $gpcRows  = $('#wpsdtrk-gpc-rows');
        var $datalist = $('#wpsdtrk-gpc-taxonomy');
        var taxonomyLoaded = false;
        var categoriesLoaded = false;

        function gpcRowHtml(c) {
            // Categories with no mapping yet are highlighted so the shop owner
            // can see at a glance what still needs a Google category.
            var unmapped = !c.google_category;
            return '' +
                '<tr' + (unmapped ? ' class="wpsdtrk-gpc-unmapped"' : '') + '>' +
                    '<td>' + esc(c.label) + '</td>' +
                    '<td>' + esc(c.count) + '</td>' +
                    '<td>' +
                        '<input type="text" class="regular-text wpsdtrk-gpc-input" ' +
                            'list="wpsdtrk-gpc-taxonomy" ' +
                            'data-term-id="' + esc(c.term_id) + '" ' +
                            'data-saved="' + escAttr(c.google_category) + '" ' +
                            'value="' + escAttr(c.google_category) + '" ' +
                            'placeholder="' + escAttr(i18n.gpcPlaceholder || 'e.g. Apparel & Accessories > Clothing') + '">' +
                    '</td>' +
                '</tr>';
        }

        // The bundled Google taxonomy is ~5.5k lines; fetch it once and turn it
        // into <option>s so the inputs get native autocomplete. Failure is
        // non-fatal — the inputs still work as free text.
        function loadTaxonomy() {
            if (taxonomyLoaded || !cfg.gpcTaxonomyUrl) {
                return;
            }
            taxonomyLoaded = true;
            $.get(cfg.gpcTaxonomyUrl).then(function (text) {
                var lines = String(text).split('\n');
                var html = '';
                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i].trim();
                    if (line === '' || line.charAt(0) === '#') {
                        continue;
                    }
                    html += '<option value="' + escAttr(line) + '"></option>';
                }
                $datalist.html(html);
            }, function () {
                taxonomyLoaded = false; // allow a retry on next open
            });
        }

        function loadCategories() {
            if (categoriesLoaded) {
                return;
            }
            ajax('list_gpc_categories', {}).then(function (r) {
                if (!r || !r.state) {
                    $gpcRows.html('<tr><td colspan="3">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
                    return;
                }
                categoriesLoaded = true;
                if (!r.rows || !r.rows.length) {
                    $gpcRows.html('<tr><td colspan="3">' + esc(i18n.gpcNoCategories || 'No product categories found.') + '</td></tr>');
                    return;
                }
                $gpcRows.html(r.rows.map(gpcRowHtml).join(''));
            }, function () {
                $gpcRows.html('<tr><td colspan="3">' + esc(i18n.loadError || 'Could not load products.') + '</td></tr>');
            });
        }

        // Populate on first open (details toggles the `open` attribute).
        $panel.on('toggle', function () {
            if ($panel.prop('open')) {
                loadTaxonomy();
                loadCategories();
            }
        });

        // Persist a single mapping on change; only when the value actually moved.
        // On success re-highlight the row: an empty value flags it as unmapped.
        $gpcRows.on('change', '.wpsdtrk-gpc-input', function () {
            var $inp = $(this);
            var termId = parseInt($inp.data('term-id'), 10);
            var value = $.trim($inp.val());
            if (value === String($inp.data('saved'))) {
                return;
            }
            $inp.prop('disabled', true);
            ajax('save_gpc_map', { changes: [{ term_id: termId, category: value }] }).then(function (r) {
                if (!r || !r.state) {
                    notice((r && r.message) || i18n.saveError || 'Could not save the change.', 'error');
                    return;
                }
                $inp.data('saved', value);
                $inp.closest('tr').toggleClass('wpsdtrk-gpc-unmapped', value === '');
                notice(r.message || i18n.saved || 'Saved.', 'success');
            }, function () {
                notice(i18n.saveError || 'Could not save the change.', 'error');
            }).always(function () {
                $inp.prop('disabled', false);
            });
        });
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

        // Per-row status toggle — optimistic, rolls back on failure. The toggle
        // is disabled while its save is in flight so a rapid re-toggle of the
        // same row can't race its own request (out-of-order responses).
        $rows.on('change', '.wpsdtrk-feed-status', function () {
            var $cb = $(this);
            var $tr = $cb.closest('tr');
            var id = parseInt($tr.data('product-id'), 10);
            var excluded = !$cb.prop('checked'); // checked = in feed
            $cb.prop('disabled', true);
            applyRowState($tr, excluded);
            save([{ id: id, excluded: excluded }], function () {
                applyRowState($tr, !excluded); // rollback
            }).always(function () {
                $cb.prop('disabled', false);
            });
        });

        // Selection → bulk button availability.
        $rows.on('change', '.wpsdtrk-feed-select', updateBulkButtons);
        $selectAll.on('change', function () {
            $rows.find('.wpsdtrk-feed-select').prop('checked', $(this).prop('checked'));
            updateBulkButtons();
        });

        function bulk(excluded) {
            var $sel = $rows.find('.wpsdtrk-feed-select:checked');
            if (!$sel.length) { return; }
            var changes = [];
            var rollback = [];
            $sel.each(function () {
                var $tr = $(this).closest('tr');
                var id = parseInt($tr.data('product-id'), 10);
                var was = $tr.hasClass('is-excluded');
                changes.push({ id: id, excluded: excluded });
                rollback.push({ $tr: $tr, was: was });
                applyRowState($tr, excluded);
            });
            $selectAll.prop('checked', false);
            $sel.prop('checked', false);
            updateBulkButtons();
            save(changes, function () {
                rollback.forEach(function (r) { applyRowState(r.$tr, r.was); });
            });
        }

        $bulkExclude.on('click', function () { bulk(true); });
        $bulkInclude.on('click', function () { bulk(false); });

        initGpcPanel();
        load();
    });

})(jQuery);
