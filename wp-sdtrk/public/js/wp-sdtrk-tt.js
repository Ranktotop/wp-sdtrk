class Wp_Sdtrk_Catcher_Tt {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_tt;
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;
		this.b_enabled = false;
		this.pixelLoaded = false;
		this.ttc = false;
		this.ttp = false;
		this.validate();
	}

	/**
	* Validate if tt is enabled 0 = browser, 1 = server, 2 = both
	 */
	validate(target = 2) {
		if (this.localizedData.pid === "" || !this.event) {
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
		if (this.get_Ttc()) {
			this.ttc = this.get_Ttc();
		}
		if (this.get_Ttp()) {
			this.ttp = this.get_Ttp();
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
	* Load the base pixel
	 */
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			//Base Pixel
			! function(w, d, t, pixelid) {
				w.TiktokAnalyticsObject = t;
				var ttq = w[t] = w[t] || [];
				ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"], ttq.setAndDefer = function(t, e) {
					t[e] = function() {
						t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
					}
				};
				for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
				ttq.instance = function(t) {
					for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
					return e
				}, ttq.load = function(e, n) {
					var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
					ttq._i = ttq._i || {}, ttq._i[e] = [], ttq._i[e]._u = i, ttq._t = ttq._t || {}, ttq._t[e] = +new Date, ttq._o = ttq._o || {}, ttq._o[e] = n || {};
					var o = document.createElement("script");
					o.type = "text/javascript", o.async = !0, o.src = i + "?sdkid=" + e + "&lib=" + t;
					var a = document.getElementsByTagName("script")[0];
					a.parentNode.insertBefore(o, a)
				};

				ttq.load(pixelid);
			}(window, document, 'ttq', this.localizedData.pid);

			//Identify
			ttq.identify(this.get_data_user());
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
					//The Page View Note: This Event is neither shown in PixelHelper nor in Events Test Webkit
					ttq.page();
					break;
				case 'Event':
					ttq.track(this.convert_eventname(this.event.grabEventName()), this.get_data_custom(), { event_id: this.event.grabOrderId() + "_" + this.get_hashId() });
					break;
				case 'Time':
					ttq.track('Watchtime-' + data.time + '-Seconds', this.get_data_custom(['value', 'currency'], {}), { event_id: this.event.grabOrderId() + "-t" + data.time + "_" + this.get_hashId() })
					break;
				case 'Scroll':
					ttq.track('Scrolldepth-' + data.percent + '-Percent', this.get_data_custom(['value', 'currency'], {}), { event_id: this.event.grabOrderId() + "-s" + data.percent + "_" + this.get_hashId() })
					break;
				case 'Click':
					ttq.track('ButtonClick', this.get_data_custom(['value', 'currency'], { buttonTag: data.tag }), { event_id: this.event.grabOrderId() + "-b" + data.tag + "_" + this.get_hashId() })
					break;
				case 'Visibility':
					ttq.track('ItemVisit', this.get_data_custom(['value', 'currency'], { itemTag: data.tag }), { event_id: this.event.grabOrderId() + "-v" + data.tag + "_" + this.get_hashId() })
					break;
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
			//add ttc and ttp
			if (this.ttc !== false) {
				data.ttc = this.ttc;
			}
			if (this.ttp !== false) {
				data.ttp = this.ttp;
			}
			//add hash
			data.hash = this.get_hashId();
			this.helper.send_ajax({ event: this.event, type: 'tt', handler: handler, data: data });
		}
	}

	/**
	* Get custom data
	* @return  {Array} The custom object
	 */
	get_data_custom(fieldsToKill = [], fieldsToAppend = {}) {
		//Collect the Custom-Data
		var customData = {};
		//Value
		if (this.event.grabValue() > 0 || this.convert_eventname(this.event.grabEventName()) === 'PlaceAnOrder') {
			customData.currency = "EUR";
			customData.value = this.event.grabValue();
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData.content_id = this.event.grabProdId();
			customData.content_type = "product";
			customData.content_name = this.event.grabProdName();
			customData.quantity = 1;
		}
		//Needed for preventing pixel-helper errors with empty contents
		else {
			customData.content_id = this.event.getPageId();
			customData.content_name = this.event.getPageName();
			customData.content_type = "product";
			customData.quantity = 1;
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

	/**
	* Get user data
	* @return  {Array} The user object
	 */
	get_data_user() {
		var userData = {};
		if (this.get_hashId() !== "") {
			userData.external_id = this.get_hashId();
		}
		if (this.event.getUserEmail() !== "") {
			userData.email = email;
		}
		return userData;
	}

	/**
	* Converts an EventName to TT-EventName
	* @param {String} name The given event-name
	 */
	convert_eventname(name) {
		switch (name) {
			case 'page_view':
				return 'ViewContent';
			case 'view_item':
				return 'ViewContent';
			case 'generate_lead':
				return 'SubmitForm';
			case 'sign_up':
				return 'CompleteRegistration';
			case 'add_to_cart':
				return 'AddToCart';
			case 'begin_checkout':
				return 'InitiateCheckout';
			case 'purchase':
				return 'PlaceAnOrder';
			default:
				return false;
		}
	}

	/**
	* Get hash from cookie or create a new one
	* @return  {Boolean} If the given type is enabled
	*/
	get_hashId() {
		var validDays = 90;
		if (this.helper.get_Cookie('_tthash', false)) {
			var value = this.helper.get_Cookie('_tthash', false);
			this.helper.save_cookie('_tthash', value, validDays, false);
			return value;
		}
		else {
			var sag = this.event.getEventSourceAgent();
			var sad = this.event.getEventSourceAdress();
			var key = sag.toLowerCase() + sad.toLowerCase();
			var regex = /[\W_]+/g;
			key = key.replace(regex, "")
			var hash = 0;
			if (key.length == 0) return hash;
			for (var i = 0; i < key.length; i++) {
				var char = key.charCodeAt(i);
				hash = ((hash << 5) - hash) + char;
				hash = hash & hash; // Convert to 32bit integer
			}
			if (hash < 0) {
				hash = hash * -1;
			}
			this.helper.save_cookie('_tthash', hash, validDays, false);
			return hash;
		}
	}

	/**
	* Get Click-ID if available
	* @return  {String} The Click-ID (ttclid)
	*/
	get_Ttc() {
		var validDays = 7;
		if (this.helper.get_Param("ttclid")) {
			var clid = this.helper.get_Param("ttclid");
			this.helper.save_cookie('_ttc', clid, validDays, false);
			return clid;
		}
		else if (this.helper.get_Cookie('_ttc', false)) {
			var value = this.helper.get_Cookie('_ttc', false);
			this.helper.save_cookie('_ttc', value, validDays, false);
			return value;
		}
		return ""
	}

	/**
	* Get Client user id if available
	* @return  {String} The Click-ID (ttp)
	*/
	get_Ttp() {
		var validDays = 90;
		if (this.helper.get_Cookie('_ttp', false)) {
			var value = this.helper.get_Cookie('_ttp', false);
			this.helper.save_cookie('_ttp', value, validDays, false);
			return value;
		}
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_tt_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_tt = window.wp_sdtrk_engine_class.get_catcher_tt();
		if (catcher_tt.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_tt.catchPageHit(0);
						break;
					case 'Time':
						catcher_tt.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_tt.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_tt.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_tt.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

/**
* Backload the Server
**/
function wp_sdtrk_backload_tt_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_tt = window.wp_sdtrk_engine_class.get_catcher_tt();
		if (catcher_tt.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_tt.catchPageHit(1);
						break;
					case 'Time':
						catcher_tt.catchTimeHit(data[1], 1);
						break;
					case 'Scroll':
						catcher_tt.catchScrollHit(data[1], 1);
						break;
					case 'Click':
						catcher_tt.catchClickHit(data[1], 1);
						break;
					case 'Visited':
						catcher_tt.catchVisibilityHit(data[1], 1);
						break;
				}
			}
		}
	}
}