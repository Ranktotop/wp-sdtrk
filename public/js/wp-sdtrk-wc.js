/**
 * WooCommerce purchase tracker (browser side).
 *
 * Fires a browser-only Purchase across all active platform catchers on the
 * WooCommerce order-received page, fed from the order data localized in
 * `wp_sdtrk_wc`. The server side is fired separately on the order-status hook
 * (see Wp_Sdtrk_WC_Integration), so this stays browser-only (target 0).
 *
 * It reuses the engine's already-constructed catchers (pixels are loaded once
 * by the engine) and temporarily swaps in a dedicated purchase event for the
 * fire, so no pixel is re-initialised (e.g. no duplicate GA page_view) and the
 * engine's own page event is left untouched. The shared eventID (= order id)
 * deduplicates reloads at the platform level.
 */

/**
 * Build a purchase event from the localized order data.
 * @param {Object} o The localized order object
 * @return {Wp_Sdtrk_Event}
 */
function wp_sdtrk_wc_buildEvent(o) {
	var event = new Wp_Sdtrk_Event();
	event.disableForce();
	event.setUtm(o.utm || {});
	event.setProdId({ wc: String(o.prodId || '') });
	event.setProdName({ wc: String(o.prodName || '') });
	event.setOrderId({ wc: String(o.orderId) });
	event.setEventId(String(o.orderId));
	event.setValue({ wc: String(o.value) });
	event.setEventName({ wc: 'purchase' });
	event.setUserEmail({ wc: String(o.email || '') });
	event.setUserFirstName({ wc: String(o.firstName || '') });
	event.setUserLastName({ wc: String(o.lastName || '') });
	event.setEventSource(o.source || window.location.href);
	event.setEventSourceAdress(o.ip || '');
	event.setEventSourceAgent(o.agent || navigator.userAgent);
	return event;
}

/**
 * Fire the purchase browser-only across all engine catchers.
 * @param {Wp_Sdtrk_Engine} engine The running engine instance
 * @param {Wp_Sdtrk_Event} purchaseEvent The purchase event
 */
function wp_sdtrk_wc_fire(engine, purchaseEvent) {
	var catchers = [
		engine.get_catcher_meta(),
		engine.get_catcher_ga(),
		engine.get_catcher_tt(),
		engine.get_catcher_lin(),
		engine.get_catcher_fl(),
		engine.get_catcher_mtc(),
		engine.get_catcher_mtm()
	];
	catchers.forEach(function (c) {
		if (!c || typeof c.catchEventHit !== 'function') {
			return;
		}
		var original = c.event;
		c.event = purchaseEvent;
		try {
			c.catchEventHit(0); // browser-only Purchase
		} finally {
			c.event = original;
		}
	});
}

/**
 * Persist the consent snapshot + the exact browser identifiers onto the order,
 * so the order-status server hook can gate firing and reuse them for dedup.
 * @param {Wp_Sdtrk_Engine} engine The running engine instance
 * @param {Object} o The localized order object
 */
function wp_sdtrk_wc_persist(engine, o) {
	var cm = engine.get_catcher_meta();
	var cg = engine.get_catcher_ga();
	var ct = engine.get_catcher_tt();

	var snapshot = {
		orderId: String(o.orderId),
		key: o.key || '',
		consent: {
			meta: !!(cm && typeof cm.isEnabled === 'function' && cm.isEnabled('s')),
			ga: !!(cg && typeof cg.isEnabled === 'function' && cg.isEnabled('s')),
			tt: !!(ct && typeof ct.isEnabled === 'function' && ct.isEnabled('s'))
		},
		ids: {
			fbp: (cm && cm.fbp) ? cm.fbp : '',
			fbc: (cm && cm.fbc) ? cm.fbc : '',
			cid: (cg && cg.cid) ? cg.cid : '',
			ttp: (ct && ct.ttp) ? ct.ttp : '',
			ttc: (ct && ct.ttc) ? ct.ttc : '',
			hash: (ct && typeof ct.get_hashId === 'function') ? ct.get_hashId() : ''
		}
	};

	if (typeof wp_sdtrk_engine === 'undefined') {
		return;
	}
	jQuery.ajax({
		cache: false,
		type: 'POST',
		url: wp_sdtrk_engine.ajax_url,
		data: {
			action: 'wp_sdtrk_wc_persist',
			_nonce: wp_sdtrk_engine._nonce,
			snapshot: snapshot
		}
	});
}

/**
 * Entry point. Waits briefly for the engine (its construction loads the pixels)
 * and then fires the purchase and persists the snapshot.
 * @param {Number} attempt Retry counter
 */
function wp_sdtrk_wc_trackPurchase(attempt) {
	attempt = attempt || 0;
	if (typeof wp_sdtrk_wc === 'undefined' || !wp_sdtrk_wc.order) {
		return;
	}
	if (typeof window.wp_sdtrk_engine_class === 'undefined') {
		if (attempt < 50) { // wait up to ~5s for the engine to come up
			setTimeout(function () { wp_sdtrk_wc_trackPurchase(attempt + 1); }, 100);
		}
		return;
	}
	var engine = window.wp_sdtrk_engine_class;
	wp_sdtrk_wc_fire(engine, wp_sdtrk_wc_buildEvent(wp_sdtrk_wc.order));
	wp_sdtrk_wc_persist(engine, wp_sdtrk_wc.order);
}

jQuery(document).ready(function () {
	wp_sdtrk_wc_trackPurchase(0);
});
