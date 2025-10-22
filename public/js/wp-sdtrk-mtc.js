class Wp_Sdtrk_Catcher_Mtc {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_mtc;
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;
		this.b_enabled = false;
		this.pixelLoaded = false;
		this.validate();
	}

	/**
	* Validate if tt is enabled 0 = browser, 1 = server, 2 = both
	 */
	validate(target = 2) {
		if (this.localizedData.pid === "" || !this.event) {
			return;
		}
		//Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (mtc)');
			return;
		}
		if ((target === 2 || target === 0) && this.helper.has_consent(this.localizedData.b_ci, this.localizedData.b_cs, this.event) !== false && this.localizedData.b_e !== "") {
			this.b_enabled = true;
			//load the base pixel
			this.loadPixel();
		}
		if ((target === 2 || target === 1) && this.helper.has_consent(this.localizedData.s_ci, this.localizedData.s_cs, this.event) !== false && this.localizedData.s_e !== "") {
			this.s_enabled = true;
		}
	}

	/**
	* This method checks if the backload shall be done for given type
	* @param {String} type The type which shall be checked
	 */
	isOngoingBackload(type) {
		//init
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
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchPageHit(target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Page', { state: true });
		}
		this.catchEventHit(target);
	}

	/**
	* Catch event hit - These hits are only fired if there is an event-name given
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchEventHit(target = 2) {
		if (this.event.grabEventName()) {
			if (target === 0 || target === 2) {
				this.fireData('Event', { state: true });
			}
		}
	}

	/**
	* Catch scroll hit
	* @param {String} percent The % of the hit
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchScrollHit(percent, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Scroll', { percent: percent });
		}
	}

	/**
	* Catch time hit
	* @param {String} time The time of the hit
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchTimeHit(time, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Time', { time: time });
		}
	}

	/**
	* Catch click hit
	* @param {String} tag The tag of the hit
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchClickHit(tag, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Click', { tag: tag });
		}
	}

	/**
	* Catch visibility hit
	* @param {String} tag The tag of the hit
	* @param {Integer} target 0 = browser 1= server 2 =both // doesnt overwrite consent
	 */
	catchVisibilityHit(tag, target = 2) {
		if (target === 0 || target === 2) {
			this.fireData('Visibility', { tag: tag });
		}
	}

	/**
	* Load the base pixel
	 */
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			var mauticUrl = this.localizedData.pid;
			//Check if string ends with slash
			if (mauticUrl.substr(-1) !== '/') {
				mauticUrl = mauticUrl + "/mtc.js";
			}
			else {
				mauticUrl = mauticUrl + "mtc.js";
			}
			(function (w, d, t, u, n, a, m) {
				w['MauticTrackingObject'] = n;
				w[n] = w[n] || function () { (w[n].q = w[n].q || []).push(arguments) }, a = d.createElement(t),
					m = d.getElementsByTagName(t)[0]; a.async = 1; a.src = u; m.parentNode.insertBefore(a, m)
			})(window, document, 'script', mauticUrl, 'mt');
			this.pixelLoaded = true;
		}
	}

	/**
	* Fire data in browser
	* @param {String} handler The handler of event
	* @param {Object} data Additional data to send
	 */
	fireData(handler, data) {
		if (this.isEnabled('b') && this.pixelLoaded) {
			//Fire the desired event
			switch (handler) {
				case 'Page':
					mt('send', 'pageview', this.get_data_custom('pageview'));
					this.helper.debugLog(this.localizedData.dbg, { event: 'pageview' }, 'Fired in Browser (mtc-' + handler + ')');
					break;
				case 'Event':
					mt('send', this.event.grabEventName(), this.get_data_custom(this.event.grabEventName()));
					this.helper.debugLog(this.localizedData.dbg, { event: this.event.grabEventName(), data: this.get_data_custom(this.event.grabEventName()), meta: {} }, 'Fired in Browser (mtc-' + handler + ')');
					break;
				case 'Time':
					mt('send', this.helper.get_EventName(handler, data.time), this.get_data_custom(this.helper.get_EventName(handler, data.time), ['currency', 'value', 'transaction_id'], { transaction_id: this.event.grabOrderId() + "-t" + data.time }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler, data.time), data: this.get_data_custom(this.helper.get_EventName(handler, data.time), ['currency', 'value', 'transaction_id'], { transaction_id: this.event.grabOrderId() + "-t" + data.time }) }, 'Fired in Browser (mtc-' + handler + ')');
					break;
				case 'Scroll':
					mt('send', this.helper.get_EventName(handler, data.percent), this.get_data_custom(this.helper.get_EventName(handler, data.percent), ['currency', 'value', 'transaction_id'], { transaction_id: this.event.grabOrderId() + "-s" + data.percent }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler, data.percent), data: this.get_data_custom(this.helper.get_EventName(handler, data.percent), ['currency', 'value', 'transaction_id'], { transaction_id: this.event.grabOrderId() + "-s" + data.percent }) }, 'Fired in Browser (mtc-' + handler + ')');
					break;
				case 'Click':
					mt('send', this.helper.get_EventName(handler, data.tag), this.get_data_custom(this.helper.get_EventName(handler, data.tag), ['currency', 'value', 'transaction_id'], { buttonTag: data.tag, transaction_id: this.event.grabOrderId() + "-b" + data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler, data.tag), data: this.get_data_custom(this.helper.get_EventName(handler, data.tag), ['currency', 'value', 'transaction_id'], { buttonTag: data.tag, transaction_id: this.event.grabOrderId() + "-b" + data.tag }) }, 'Fired in Browser (mtc-' + handler + ')');
					break;
				case 'Visibility':
					mt('send', this.helper.get_EventName(handler, data.tag), this.get_data_custom(this.helper.get_EventName(handler, data.tag), ['currency', 'value', 'transaction_id'], { itemTag: data.tag, transaction_id: this.event.grabOrderId() + "-v" + data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler, data.tag), data: this.get_data_custom(this.helper.get_EventName(handler, data.tag), ['currency', 'value', 'transaction_id'], { itemTag: data.tag, transaction_id: this.event.grabOrderId() + "-v" + data.tag }) }, 'Fired in Browser (mtc-' + handler + ')');
					break;
			}
		}
	}

	/**
	* Get custom data
	* @return  {Array} The custom object
	 */
	get_data_custom(eventname = false, fieldsToKill = [], fieldsToAppend = {}) {
		//Collect the Custom-Data
		var customData = {};
		//Value
		if (this.event.grabValue() > 0 || this.event.grabEventName() === 'purchase') {
			customData.currency = "EUR";
			customData.value = this.event.grabValue();
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData.item_id = this.event.grabProdId();
			customData.item_name = this.event.grabProdName();
			customData.item_quantity = 1;
			customData.item_price = this.event.grabValue();
			customData.item_brand = this.event.getBrandName();
			//if there is a prod-id add tags like lead_12345
			if (eventname !== false) {
				customData.tags = eventname + '_' + this.event.grabProdId();
			}
		}
		//Meta
		customData.transaction_id = this.event.grabOrderId();
		customData.page_title = this.event.getPageName();
		customData.post_id = this.event.getPageId();
		customData.plugin = "Wp-Sdtrk";
		customData.event_url = this.event.getEventSource();
		customData.user_role = "guest";
		customData.landing_page = this.event.getLandingPage();
		customData.event_time = this.helper.get_time()[0];
		customData.event_day = this.helper.get_time()[1];
		customData.event_month = this.helper.get_time()[2];

		//UTM
		for (var k in this.event.getUtm()) {
			if (this.event.getUtm()[k] !== "") {
				customData[k] = this.event.getUtm()[k];
			}
		}

		//if given, remove unwanted fields
		for (var i = 0; i < fieldsToKill.length; i++) {
			var fieldName = fieldsToKill[i];
			if (customData.hasOwnProperty(fieldName)) {
				delete customData[fieldName];
			}
		}

		//if given, add some fields
		for (const [key, value] of Object.entries(fieldsToAppend)) {
			customData[key] = value;
		}
		return customData;
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_mtc_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_mtc = window.wp_sdtrk_engine_class.get_catcher_mtc();
		if (catcher_mtc.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_mtc.catchPageHit(0);
						break;
					case 'Time':
						catcher_mtc.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_mtc.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_mtc.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_mtc.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

