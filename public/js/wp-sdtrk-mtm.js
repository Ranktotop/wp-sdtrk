class Wp_Sdtrk_Catcher_Mtm {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_mtm;
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;
		this.b_enabled = false;
		this.pixelLoaded = false;
		this.cid = false;
		this.gclid = false;
		this.validate();
	}

	/**
	* Validate if mtm is enabled 0 = browser, 1 = server, 2 = both
	*/
	validate(target = 2) {
		if (this.localizedData.pid === "" || this.localizedData.sid === "" || !this.event) {
			return;
		}
		//Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (mtm)');
			return;
		}
		// Matomo can also work with a client id concept if you want to tie server/browser together
		if (this.get_Cid()) {
			this.cid = this.get_Cid();
		}
		if ((target === 2 || target === 0) && this.helper.has_consent(this.localizedData.b_ci, this.localizedData.b_cs, this.event) !== false && this.localizedData.b_e !== "") {
			this.b_enabled = true;
			//load the base tracker
			this.loadPixel();
		}
		if ((target === 2 || target === 1) && this.helper.has_consent(this.localizedData.s_ci, this.localizedData.s_cs, this.event) !== false && this.localizedData.s_e !== "") {
			this.s_enabled = true;
		}
		if (this.get_Gclid()) {
			this.gclid = this.get_Gclid();
		}
	}

	/**
	* This method checks if the backload shall be done for given type
	* @param {String} type The type which shall be checked
	*/
	isOngoingBackload(type) {
		var oldState = true;
		var newState = false;

		if (type === 'b') {
			oldState = this.pixelLoaded;
			if (!oldState) {
				this.validate(0);
				newState = this.pixelLoaded;
			}
		}
		if (type === 's') {
			oldState = this.isEnabled('s');
			if (!oldState) {
				this.validate(1);
				newState = this.isEnabled('s');
			}
		}
		return (oldState === false && newState === true);
	}

	/**
	* Check if enabled
	* @param {String} type The type which shall be checked
	* @return  {Boolean} If the given type is enabled
	*/
	isEnabled(type) {
		switch (type) {
			case 'b':
				return this.b_enabled;
			case 's':
				return this.s_enabled;
		}
		return false;
	}

	/**
	* Catch page hit
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchPageHit(target = 2) {
		if (target === 0 || target === 2) {
			// Pageview is fired on pixel-init automatically
		}
		if (target === 1 || target === 2) {
			this.sendData('Page', { state: true });
		}
		this.catchEventHit(target);
	}

	/**
	* Catch event hit (requires event-name)
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchEventHit(target = 2) {
		if (this.event.grabEventName()) {
			if (target === 0 || target === 2) {
				this.fireData('Event', { state: true });
			}
			if (target === 1 || target === 2) {
				this.sendData('Event', { state: true });
			}
		}
	}

	/**
	* Catch scroll hit
	* @param {String} percent
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchScrollHit(percent, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Scroll', { percent: percent });
		}
		if (target === 1 || target === 2) {
			this.sendData('Scroll', { percent: percent });
		}
	}

	/**
	* Catch time hit
	* @param {String} time
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchTimeHit(time, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Time', { time: time });
		}
		if (target === 1 || target === 2) {
			this.sendData('Time', { time: time });
		}
	}

	/**
	* Catch click hit
	* @param {String} tag
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchClickHit(tag, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Click', { tag: tag });
		}
		if (target === 1 || target === 2) {
			this.sendData('Click', { tag: tag });
		}
	}

	/**
	* Catch visibility hit
	* @param {String} tag
	* @param {Integer} target 0 = browser 1 = server 2 = both
	*/
	catchVisibilityHit(tag, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Visibility', { tag: tag });
		}
		if (target === 1 || target === 2) {
			this.sendData('Visibility', { tag: tag });
		}
	}

	/**
	* Load the base tracker - This fires a pageview hit automatically
	*/
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			//Save campaign data (same as GA implementation)
			this.set_storedCampaign();

			// Prepare Matomo queue
			window._paq = window._paq || [];

			// Set basic dimensions/variables before the first trackPageView
			this.set_dimensions();

			// Link tracking
			_paq.push(['enableLinkTracking']);

			// Configure URL & Site ID from localizedData
			(function (window, document, srcBase, siteId) {
				_paq.push(['setTrackerUrl', srcBase + 'matomo.php']);
				_paq.push(['setSiteId', siteId]);

				// Defer initial pageview until after campaign restoration
				_paq.push(['trackPageView']);

				var g = document.createElement('script'), s = document.getElementsByTagName('script')[0];
				g.async = true;
				g.src = srcBase + 'matomo.js';
				s.parentNode.insertBefore(g, s);
			})(window, document, this.normalizeBaseUrl(this.localizedData.pid), this.localizedData.sid);

			this.helper.debugLog(this.localizedData.dbg, { event: 'page_view', data: this.get_config() }, 'Fired in Browser (mtm-Page)');
			this.pixelLoaded = true;
		}
	}

	/**
	* Map custom dimensions (indices 1..6 analogous to GA dimension1..dimension6)
	* 1: event_hour, 2: event_day, 3: event_month, 4..6: dynx/ecomm
	*/
	set_dimensions(extra = {}) {
		if (!window._paq) { window._paq = []; }
		var cd = this.get_dimensions();
		// Matomo requires numeric indices; we mirror GA mapping 1..6
		if (typeof cd.dimension1 !== 'undefined') _paq.push(['setCustomDimension', 1, cd.dimension1]);
		if (typeof cd.dimension2 !== 'undefined') _paq.push(['setCustomDimension', 2, cd.dimension2]);
		if (typeof cd.dimension3 !== 'undefined') _paq.push(['setCustomDimension', 3, cd.dimension3]);
		if (typeof cd.dimension4 !== 'undefined') _paq.push(['setCustomDimension', 4, cd.dimension4]);
		if (typeof cd.dimension5 !== 'undefined') _paq.push(['setCustomDimension', 5, cd.dimension5]);
		if (typeof cd.dimension6 !== 'undefined') _paq.push(['setCustomDimension', 6, cd.dimension6]);

		// Optional: set referrer / custom URL from restored campaign
		var cfg = this.get_config();
		if (cfg.page_referrer) { _paq.push(['setReferrerUrl', cfg.page_referrer]); }
		if (cfg.page_location) { _paq.push(['setCustomUrl', cfg.page_location]); }
		if (cfg.page_path) { _paq.push(['setCustomUrl', cfg.page_path]); } // path overrides location if present

		// Append any extra dimensions for one-off events
		for (const [k, v] of Object.entries(extra)) {
			if (/^dimension[1-9][0-9]*$/.test(k)) {
				var idx = parseInt(k.replace('dimension', ''), 10);
				_paq.push(['setCustomDimension', idx, v]);
			}
		}
	}

	/**
	* Get custom dimensions for Matomo (mirror GA mapping)
	* @return  {Object}
	*/
	get_dimensions() {
		var isEcom = false;
		var cd = {
			'dimension1': this.event.getEventTimeHour(),
			'dimension2': this.event.getEventTimeDay(),
			'dimension3': this.event.getEventTimeMonth()
		};
		if (isEcom) {
			cd.dimension4 = this.event.grabProdId() || '';
			cd.dimension5 = 'ecomm_pagetype';
			cd.dimension6 = String(this.event.grabValue() || 0);
		} else {
			cd.dimension4 = this.event.grabProdId() || '';
			cd.dimension5 = 'dynx_pagetype';
			cd.dimension6 = String(this.event.grabValue() || 0);
		}
		return cd;
	}

	/**
	* Get config (used to restore referrer / location / path)
	* @return  {Object}
	*/
	get_config() {
		var config = {
			'debug_mode': this.localizedData.debug === "1"
		};
		var campaignData = this.get_storedCampaign();
		if (campaignData) {
			if (typeof campaignData.referrer !== 'undefined') {
				config['page_referrer'] = campaignData.referrer;
			}
			if (typeof campaignData.location !== 'undefined') {
				config['page_location'] = campaignData.location;
			}
			if (typeof campaignData.page !== 'undefined') {
				config['page_path'] = campaignData.page;
			}
		}
		return config;
	}

	/**
	* Fire data in browser
	* @param {String} handler The handler of event
	* @param {Object} data Additional data to send
	*/
	fireData(handler, data) {
		if (this.isEnabled('b') && this.pixelLoaded) {
			window._paq = window._paq || [];

			// Ensure base dimensions are set freshly per hit
			this.set_dimensions();

			switch (handler) {
				case 'Event': {
					// category, action, name, value
					var evName = this.event.grabEventName();
					var info = this.get_data_custom();
					_paq.push(['trackEvent', 'Event', evName, info.post_id ? String(info.post_id) : undefined, info.value ? Number(info.value) : undefined]);
					this.helper.debugLog(this.localizedData.dbg, { event: evName, data: info }, 'Fired in Browser (mtm-Event)');
					break;
				}
				case 'Time': {
					var infoT = this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-t" + data.time });
					_paq.push(['trackEvent', 'Time', String(data.time), this.event.grabOrderId(), infoT.value ? Number(infoT.value) : undefined]);
					this.helper.debugLog(this.localizedData.dbg, { event: 'Time_' + data.time, data: infoT }, 'Fired in Browser (mtm-Time)');
					break;
				}
				case 'Scroll': {
					var infoS = this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-s" + data.percent });
					_paq.push(['trackEvent', 'Scroll', String(data.percent), this.event.grabOrderId(), infoS.value ? Number(infoS.value) : undefined]);
					this.helper.debugLog(this.localizedData.dbg, { event: 'Scroll_' + data.percent, data: infoS }, 'Fired in Browser (mtm-Scroll)');
					break;
				}
				case 'Click': {
					var infoC = this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-b" + data.tag, buttonTag: data.tag });
					_paq.push(['trackEvent', 'Click', String(data.tag), this.event.grabOrderId(), infoC.value ? Number(infoC.value) : undefined]);
					this.helper.debugLog(this.localizedData.dbg, { event: 'Click_' + data.tag, data: infoC }, 'Fired in Browser (mtm-Click)');
					break;
				}
				case 'Visibility': {
					var infoV = this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-v" + data.tag, itemTag: data.tag });
					_paq.push(['trackEvent', 'Visibility', String(data.tag), this.event.grabOrderId(), infoV.value ? Number(infoV.value) : undefined]);
					this.helper.debugLog(this.localizedData.dbg, { event: 'Visibility_' + data.tag, data: infoV }, 'Fired in Browser (mtm-Visibility)');
					break;
				}
			}
		}
	}

	/**
	* Send data to server
	* @param {String} handler The handler of event
	* @param {Object} data The data to send
	*/
	sendData(handler, data) {
		if (this.isEnabled('s')) {
			//add client id and click id (if you want to stitch sessions server-side)
			if (this.cid !== false) {
				data.cid = this.cid;
			}
			if (this.gclid !== false) {
				data.gclid = this.gclid;
			}
			this.helper.send_ajax({ event: this.event, type: 'mtm', handler: handler, data: data }, this.localizedData.dbg);
		}
	}

	/**
	* Build custom data (mirrors GA variant)
	* @return  {Object}
	*/
	get_data_custom(fieldsToKill = [], fieldsToAppend = {}) {
		var customData = {};

		//Value
		if (this.event.grabValue() > 0 || this.event.grabEventName() === 'purchase') {
			customData['value'] = this.event.grabValue();
			customData['currency'] = "EUR";
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData['items'] = [{
				'id': this.event.grabProdId(),
				'name': this.event.grabProdName(),
				'quantity': 1,
				'price': this.event.grabValue(),
				'brand': this.event.getBrandName(),
			}];
		}
		//UTM
		for (var k in this.event.getUtm()) {
			var value = this.event.getUtm()[k];
			if (value !== "") {
				customData[k] = value;
			}
		}
		//Transaction and source
		customData['transaction_id'] = this.event.grabOrderId();
		customData['non_interaction'] = true;
		customData['page_title'] = this.event.getPageName();
		customData['post_type'] = "product";
		customData['post_id'] = this.event.getPageId();
		customData['plugin'] = "Wp-Sdtrk";
		customData['event_url'] = this.event.getEventSource();
		customData['user_role'] = "guest";
		customData['event_time'] = this.event.getEventTimeHour();
		customData['event_day'] = this.event.getEventTimeDay();
		customData['event_month'] = this.event.getEventTimeMonth();
		customData['landing_page'] = this.event.getLandingPage();
		customData['send_to'] = this.localizedData.sid;

		// remove unwanted fields
		for (var i = 0; i < fieldsToKill.length; i++) {
			var fieldName = fieldsToKill[i];
			if (customData.hasOwnProperty(fieldName)) {
				delete customData[fieldName];
			}
		}
		// append extra fields
		for (const [key, value] of Object.entries(fieldsToAppend)) {
			customData[key] = value;
		}

		return customData;
	}

	/**
	* Save campaign-data if one of the required params are given
	*/
	set_storedCampaign() {
		var paramsToFind = ['gclid']; // keep parity with GA variant
		for (var param of paramsToFind) {
			var value = this.helper.get_Param(param);
			if (value) {
				this.helper.save_cookie('_cd', this.event.getEventUrl(), 14, true);
			}
			break;
		}
		// Save referrer if it does not contain current hostname
		var referrerHost = this.event.getEventSourceReferer();
		if (referrerHost.indexOf(this.event.getEventDomain()) === -1 && referrerHost !== '') {
			this.helper.save_cookie('_rd', referrerHost, 14, true);
		}
	}

	/**
	* Get campaign-data if available
	* @return  {Array|Boolean} The campaign-data or false
	*/
	get_storedCampaign() {
		var ref = this.helper.get_Cookie('_rd', true);
		var cd = this.helper.get_Cookie('_cd', true);
		if (ref || cd) {
			var campaignData = {};
			if (ref) {
				campaignData['referrer'] = ref;
				this.helper.save_cookie('_rd', '', 0, true);
			}
			if (cd) {
				campaignData['location'] = cd;
			}
			campaignData['page'] = this.event.getEventPath();
			return campaignData;
		}
		return false;
	}

	/**
	* Get saved client-id or create a new one (parity with GA for server stitching)
	* @return  {String}
	*/
	get_Cid() {
		var validDays = 90;
		var value = this.helper.get_Cookie('_ga', false);
		if (value) {
			const regex = /[0-9]+\.[0-9]+$/gm;
			var userid = value.match(regex);
			if (Array.isArray(userid)) {
				userid = userid[0];
			}
			this.helper.save_cookie('_ga', value, validDays, false);
			return userid;
		} else {
			var version = 'GA1';
			var subdomainIndex = '1';
			var creationTime = + new Date();
			var randomNo = parseInt(Math.random() * 10000000000);
			var clientId = creationTime + '.' + randomNo;
			if (this.event.getUserFP()) {
				clientId = this.event.getUserFP() + '.' + this.event.getUserFP();
			}
			var cValue = version + '.' + subdomainIndex + '.' + clientId;
			this.helper.save_cookie('_ga', cValue, validDays, false);
			return clientId;
		}
	}

	/**
	* Get saved click-id if available
	* @return  {String}
	*/
	get_Gclid() {
		var validDays = 90;
		if (this.helper.get_Param("gclid")) {
			var clid = this.helper.get_Param("gclid");
			this.helper.save_cookie('_gc', clid, validDays, false);
			return clid;
		} else if (this.helper.get_Cookie('_gc', false)) {
			var value = this.helper.get_Cookie('_gc', false);
			this.helper.save_cookie('_gc', value, validDays, false);
			return value;
		}
		return "";
	}

	/**
	* Ensure base URL ends with a trailing slash
	*/
	normalizeBaseUrl(u) {
		if (!u) return '';
		if (u.slice(-1) !== '/') return u + '/';
		return u;
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_mtm_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_mtm = window.wp_sdtrk_engine_class.get_catcher_mtm();
		if (catcher_mtm.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				let data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_mtm.catchPageHit(0);
						break;
					case 'Time':
						catcher_mtm.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_mtm.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_mtm.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_mtm.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

/**
* Backload the Server
**/
function wp_sdtrk_backload_mtm_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_mtm = window.wp_sdtrk_engine_class.get_catcher_mtm();
		if (catcher_mtm.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				let data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_mtm.catchPageHit(1);
						break;
					case 'Time':
						catcher_mtm.catchTimeHit(data[1], 1);
						break;
					case 'Scroll':
						catcher_mtm.catchScrollHit(data[1], 1);
						break;
					case 'Click':
						catcher_mtm.catchClickHit(data[1], 1);
						break;
					case 'Visited':
						catcher_mtm.catchVisibilityHit(data[1], 1);
						break;
				}
			}
		}
	}
}
