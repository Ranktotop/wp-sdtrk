class Wp_Sdtrk_Catcher_Oai {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_oai;
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;
		this.b_enabled = false;
		this.pixelLoaded = false;
		this.oppref = "";
		this.validate();
	}

	/**
	* Validate if oai is enabled 0 = browser, 1 = server, 2 = both
	 */
	validate(target = 2) {
		if (this.localizedData.pid === "" || !this.event) {
			return;
		}
		//Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (oai)');
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
		//Attribution ids are only collected once a path has consent
		if (this.b_enabled || this.s_enabled) {
			this.oppref = this.get_Oppref();
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
		if (target === 1 || target === 2) {
			this.sendData('Page', { state: true });
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
			if (target === 1 || target === 2) {
				this.sendData('Event', { state: true });
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
		if (target === 1 || target === 2) {
			this.sendData('Scroll', { percent: percent });
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
		if (target === 1 || target === 2) {
			this.sendData('Time', { time: time });
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
		if (target === 1 || target === 2) {
			this.sendData('Click', { tag: tag });
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
		if (target === 1 || target === 2) {
			this.sendData('Visibility', { tag: tag });
		}
	}

	/**
	* Load the base pixel (official ChatGPT Ads Measurement Pixel snippet)
	 */
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			//Base Pixel
			(function (w, d, s, u) {
				if (w.oaiq) return;
				var q = function () {
					q.q.push(arguments);
				};
				q.q = [];
				w.oaiq = q;
				var js = d.createElement(s);
				js.async = true;
				js.src = u;
				var f = d.getElementsByTagName(s)[0];
				f.parentNode.insertBefore(js, f);
			})(window, document, "script", "https://bzrcdn.openai.com/sdk/oaiq.min.js");

			//Init — no user object: the pixel only accepts pre-hashed identifiers
			oaiq("init", {
				pixelId: this.localizedData.pid,
				debug: (this.localizedData.dbg === true || this.localizedData.dbg === '1'),
			});
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
					this.measure('page_viewed', this.get_data_page(), { event_id: this.event.grabOrderId() }, handler);
					break;
				case 'Event':
					var name = this.convert_eventname(this.event.grabEventName());
					if (name !== false) {
						this.measure(name, this.get_data_event(), { event_id: this.event.grabOrderId() }, handler);
					}
					break;
				case 'Time':
					this.measureCustom(this.helper.get_EventName(handler, data.time), this.event.grabOrderId() + "-t" + data.time, handler);
					break;
				case 'Scroll':
					this.measureCustom(this.helper.get_EventName(handler, data.percent), this.event.grabOrderId() + "-s" + data.percent, handler);
					break;
				case 'Click':
					this.measureCustom(this.helper.get_EventName(handler, data.tag), this.event.grabOrderId() + "-b" + data.tag, handler);
					break;
				case 'Visibility':
					this.measureCustom(this.helper.get_EventName(handler, data.tag), this.event.grabOrderId() + "-v" + data.tag, handler);
					break;
			}
		}
	}

	/**
	* Queue a measure call and log it
	* @param {String} name The OpenAI event name
	* @param {Object} eventData The event data object
	* @param {Object} options The options object (event_id, custom_event_name)
	* @param {String} handler The handler of event (for the log)
	 */
	measure(name, eventData, options, handler) {
		oaiq("measure", name, eventData, options);
		this.helper.debugLog(this.localizedData.dbg, { event: name, data: eventData, options: options }, 'Fired in Browser (oai-' + handler + ')');
	}

	/**
	* Queue a custom (signal) event — skipped if the name breaks OpenAI's rules
	* @param {String} customName The custom_event_name
	* @param {String} eventId The event_id shared with the server
	* @param {String} handler The handler of event (for the log)
	 */
	measureCustom(customName, eventId, handler) {
		if (!this.isValidCustomEventName(customName)) {
			this.helper.debugLog(this.localizedData.dbg, { event: customName }, 'Skipped invalid custom event name (oai-' + handler + ')');
			return;
		}
		this.measure('custom', { type: "custom" }, { custom_event_name: customName, event_id: eventId }, handler);
	}

	/**
	* Checks a custom_event_name: 1–64 letters, digits, underscores or dashes,
	* starting and ending alphanumeric, not a standard event name
	* @param {String} name The custom event name
	* @return  {Boolean} If the name is valid
	 */
	isValidCustomEventName(name) {
		var standard = ['app_installed', 'app_opened', 'appointment_scheduled', 'checkout_started', 'contents_viewed', 'custom', 'items_added', 'lead_created', 'order_created', 'page_viewed', 'registration_completed', 'subscription_created', 'trial_started'];
		if (typeof name !== 'string' || name.length > 64 || !/^[A-Za-z0-9](?:[A-Za-z0-9_-]*[A-Za-z0-9])?$/.test(name)) {
			return false;
		}
		return !standard.includes(name.toLowerCase());
	}

	/**
	* Send data to server
	* @param {String} handler The handler of event
	* @param {Object} data The data to send
	 */
	sendData(handler, data) {
		if (this.isEnabled('s')) {
			//add attribution ids
			if (this.oppref !== "") {
				data.oppref = this.oppref;
			}
			//read at send time: the async pixel writes __obref after the first hit
			var obref = this.get_Obref();
			if (obref !== "") {
				data.obref = obref;
			}
			this.helper.send_ajax({ event: this.event, type: 'oai', handler: handler, data: data }, this.localizedData.dbg);
		}
	}

	/**
	* Get the ChatGPT Ads attribution id (oppref) if available. The Conversions
	* API doesn't capture it, so it is kept in an own cookie for server-only
	* setups; the pixel's __oppref cookie is the fallback.
	* @return  {String} The oppref
	*/
	get_Oppref() {
		// Same lifetime as the pixel's __oppref: 30 days, reset on every new param
		var validDays = 30;
		if (this.helper.get_Param("oppref")) {
			var oppref = this.helper.get_Param("oppref");
			this.helper.save_cookie('_oai_oppref', oppref, validDays, false);
			return oppref;
		}
		if (this.helper.get_Cookie('_oai_oppref', false)) {
			return this.helper.get_Cookie('_oai_oppref', false);
		}
		if (this.helper.get_Cookie('__oppref', false)) {
			return this.helper.get_Cookie('__oppref', false);
		}
		return "";
	}

	/**
	* Get the browser reference of the pixel (__obref cookie) if available
	* @return  {String} The obref
	*/
	get_Obref() {
		return this.helper.get_Cookie('__obref', false) || "";
	}

	/**
	* Get the data object of the page_viewed event
	* @return  {Object} The event data
	*/
	get_data_page() {
		return {
			type: "contents",
			contents: [{
				id: String(this.event.getPageId() || ''),
				name: String(this.event.getPageName() || ''),
				content_type: "page",
			}],
		};
	}

	/**
	* Get the data object of a conversion event. Its type selects the OpenAI data
	* shape; amounts are integers in the currency's minor unit.
	* @return  {Object} The event data
	*/
	get_data_event() {
		var name = this.convert_eventname(this.event.grabEventName());
		var eventData = { type: this.get_data_type(name) };
		var currency = this.event.getCurrency() || "EUR";
		//Value
		if (this.event.grabValue() > 0 || name === 'order_created') {
			eventData.amount = this.toMinorUnits(this.event.grabValue(), currency);
			eventData.currency = currency;
		}
		//customer_action carries no contents
		if (eventData.type !== 'contents') {
			return eventData;
		}
		//Product(s) — the whole cart as contents[] when present
		var items = this.event.getItems();
		if (items.length > 0) {
			eventData.contents = [];
			for (var i = 0; i < items.length; i++) {
				var content = {
					id: String(items[i].id || ''),
					name: String(items[i].name || ''),
					content_type: "product",
					quantity: Math.max(1, parseInt(items[i].qty, 10) || 1),
				};
				var price = Number(items[i].price) || 0;
				if (price > 0) {
					content.amount = this.toMinorUnits(price, currency);
					content.currency = currency;
				}
				eventData.contents.push(content);
			}
		}
		else if (this.event.grabProdId() !== "") {
			eventData.contents = [{
				id: String(this.event.grabProdId()),
				name: String(this.event.grabProdName() || ''),
				content_type: "product",
				quantity: 1,
			}];
		}
		return eventData;
	}

	/**
	* Converts an amount to an integer in the currency's ISO 4217 minor unit
	* (e.g. 25.99 EUR -> 2599, 1500 JPY -> 1500, 1.5 KWD -> 1500)
	* @param {Number} value The amount in major units
	* @param {String} currency The ISO 4217 currency code
	* @return  {Number} The amount in minor units
	*/
	toMinorUnits(value, currency) {
		var exponents = {
			BIF: 0, CLP: 0, DJF: 0, GNF: 0, ISK: 0, JPY: 0, KMF: 0, KRW: 0, PYG: 0,
			RWF: 0, UGX: 0, UYI: 0, VND: 0, VUV: 0, XAF: 0, XOF: 0, XPF: 0,
			BHD: 3, IQD: 3, JOD: 3, KWD: 3, LYD: 3, OMR: 3, TND: 3,
			CLF: 4, UYW: 4,
		};
		var code = String(currency || '').toUpperCase();
		var exponent = exponents.hasOwnProperty(code) ? exponents[code] : 2;
		return Math.round((Number(value) || 0) * Math.pow(10, exponent));
	}

	/**
	* Returns the OpenAI data shape for an OpenAI event name
	* @param {String} name The OpenAI event name
	* @return  {String} The data type
	*/
	get_data_type(name) {
		switch (name) {
			case 'lead_created':
			case 'registration_completed':
				return 'customer_action';
			default:
				return 'contents';
		}
	}

	/**
	* Converts an EventName to an OpenAI event name
	* @param {String} name The given event-name
	* @return  {String|Boolean} The OpenAI event name or false if unsupported
	 */
	convert_eventname(name) {
		switch (name) {
			case 'view_item':
				return 'contents_viewed';
			case 'generate_lead':
				return 'lead_created';
			case 'sign_up':
				return 'registration_completed';
			case 'add_to_cart':
				return 'items_added';
			case 'begin_checkout':
				return 'checkout_started';
			case 'purchase':
				return 'order_created';
			default:
				return false;
		}
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_oai_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_oai = window.wp_sdtrk_engine_class.get_catcher_oai();
		if (catcher_oai.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				var data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_oai.catchPageHit(0);
						break;
					case 'Time':
						catcher_oai.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_oai.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_oai.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_oai.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

/**
* Backload the Server
**/
function wp_sdtrk_backload_oai_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_oai = window.wp_sdtrk_engine_class.get_catcher_oai();
		if (catcher_oai.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				var data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_oai.catchPageHit(1);
						break;
					case 'Time':
						catcher_oai.catchTimeHit(data[1], 1);
						break;
					case 'Scroll':
						catcher_oai.catchScrollHit(data[1], 1);
						break;
					case 'Click':
						catcher_oai.catchClickHit(data[1], 1);
						break;
					case 'Visited':
						catcher_oai.catchVisibilityHit(data[1], 1);
						break;
				}
			}
		}
	}
}
