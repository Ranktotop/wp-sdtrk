class Wp_Sdtrk_Catcher_Fb {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_fb;
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;
		this.b_enabled = false;
		this.pixelLoaded = false;
		this.fbc = false;
		this.fbp = false;
		this.validate();
	}

	/**
	* Validate if fb is enabled 0 = browser, 1 = server, 2 = both
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
		if (this.get_fbc()) {
			this.fbc = this.get_fbc();
		}
		if (this.get_fbp()) {
			this.fbp = this.get_fbp();
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
			!function(f, b, e, v, n, t, s) {
				if (f.fbq) return; n = f.fbq = function() {
					n.callMethod ?
						n.callMethod.apply(n, arguments) : n.queue.push(arguments)
				};
				if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
				n.queue = []; t = b.createElement(e); t.async = !0;
				t.src = v; s = b.getElementsByTagName(e)[0];
				s.parentNode.insertBefore(t, s)
			}(window, document, 'script',
				'https://connect.facebook.net/en_US/fbevents.js');
			fbq('init', this.localizedData.pid, this.get_data_user());
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
					fbq('track', 'PageView', this.get_data_custom(['value', 'currency']), { eventID: this.event.grabOrderId() });
					this.helper.debugLog(this.localizedData.dbg, { event: 'PageView', data: this.get_data_custom(['value', 'currency']), meta: { eventID: this.event.grabOrderId() }}, 'Fired in Browser (fb-' + handler + ')');
					break;
				case 'Event':
					fbq('trackSingle', this.localizedData.pid, this.convert_eventname(this.event.grabEventName()), this.get_data_custom(), { eventID: this.event.grabOrderId() });
					this.helper.debugLog(this.localizedData.dbg, { event: this.convert_eventname(this.event.grabEventName()), data: this.get_data_custom(), meta: { eventID: this.event.grabOrderId() }}, 'Fired in Browser (fb-' + handler + ')');
					break;
				case 'Time':
					fbq('trackCustom', this.helper.get_EventName(handler,data.time), this.get_data_custom(), { eventID: this.event.grabOrderId() + "-t" + data.time });
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.time), data: this.get_data_custom(), meta: { eventID: this.event.grabOrderId() + "-t" + data.time }}, 'Fired in Browser (fb-' + handler + ')');
					break;
				case 'Scroll':
					fbq('trackCustom', this.helper.get_EventName(handler,data.percent), this.get_data_custom(), { eventID: this.event.grabOrderId() + "-s" + data.percent });
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.percent), data: this.get_data_custom(), meta: { eventID: this.event.grabOrderId() + "-s" + data.percent }}, 'Fired in Browser (fb-' + handler + ')');
					break;
				case 'Click':
					fbq('trackCustom', this.helper.get_EventName(handler,data.tag), this.get_data_custom([], { buttonTag: data.tag }), { eventID: this.event.grabOrderId() + "-b" + data.tag });
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag), data: this.get_data_custom([], { buttonTag: data.tag }), meta: { eventID: this.event.grabOrderId() + "-b" + data.tag }}, 'Fired in Browser (fb-' + handler + ')');
					break;
				case 'Visibility':
					fbq('trackCustom', this.helper.get_EventName(handler,data.tag), this.get_data_custom([], { itemTag: data.tag }), { eventID: this.event.grabOrderId() + "-v" + data.tag });
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag), data: this.get_data_custom([], { itemTag: data.tag }), meta: { eventID: this.event.grabOrderId() + "-v" + data.tag }}, 'Fired in Browser (fb-' + handler + ')');
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
			//add fbc and fbp
			if (this.fbc !== false) {
				data.fbc = this.fbc;
			}
			if (this.fbp !== false) {
				data.fbp = this.fbp;
			}
			this.helper.send_ajax({ event: this.event, type: 'fb', handler: handler, data: data },this.localizedData.dbg);
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
		if (this.event.grabValue() > 0 || this.convert_eventname(this.event.grabEventName()) === 'Purchase') {
			customData.currency = "EUR";
			customData.value = this.event.grabValue();
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData.content_ids = '["' + this.event.grabProdId() + '"]';
			customData.content_type = "product";
			customData.content_name = this.event.grabProdName();
			customData.contents = '[{"id":"' + this.event.grabProdId() + '","quantity":' + 1 + '}]';
		}
		//UTM
		for (var k in this.event.getUtm()) {
			var value = this.event.getUtm()[k];
			if (value !== "") {
				customData[k] = value;
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

	/**
	* Get user data
	* @return  {Array} The user object
	 */
	get_data_user() {
		var userData = {};
		if (this.event.getUserFirstName() !== "") {
			userData.fn = this.event.getUserFirstName();
		}
		if (this.event.getUserLastName() !== "") {
			userData.ln = this.event.getUserLastName();
		}
		if (this.event.getUserEmail() !== "") {
			userData.em = this.event.getUserEmail();
		}
		return userData;
	}

	/**
	* Converts an EventName to FB-EventName
	* @param {String} name The given event-name
	 */
	convert_eventname(name) {
		switch (name) {
			case 'page_view':
				return 'PageView';
			case 'view_item':
				return 'ViewContent';
			case 'generate_lead':
				return 'Lead';
			case 'sign_up':
				return 'CompleteRegistration';
			case 'add_to_cart':
				return 'AddToCart';
			case 'begin_checkout':
				return 'InitiateCheckout';
			case 'purchase':
				return 'Purchase';
			default:
				return false;
		}
	}

	/**
	* Get fbp or calculate new fbp
	* @return  {String} The fbp value
	 */
	get_fbp() {
		var validDays = 90;
		if (this.helper.get_Cookie('_fbp', false)) {
			var value = this.helper.get_Cookie('_fbp', false);
			this.helper.save_cookie('_fbp', value, validDays, false);
			return value;
		}
		var version = 'fb';
		var subdomainIndex = '1';
		var creationTime = + new Date();
		var randomNo = parseInt(Math.random() * 10000000000);
		var cValue = version + '.' + subdomainIndex + '.' + creationTime + '.' + randomNo;
		this.helper.save_cookie('_fbp', cValue, validDays, false);
		return cValue;
	}

	/**
	* Get fbc or calculate new fbc
	* @return  {String} The fbc value
	 */
	get_fbc() {
		var validDays = 90;
		if (this.helper.get_Param("fbclid")) {
			var version = 'fb';
			var subdomainIndex = '1';
			var creationTime = + new Date();
			var clid = this.helper.get_Param("fbclid");
			var cValue = version + '.' + subdomainIndex + '.' + creationTime + '.' + clid;
			this.helper.save_cookie('_fbc', cValue, validDays, false);
			return cValue;
		}
		else if (this.helper.get_Cookie('_fbc', false)) {
			var value = this.helper.get_Cookie('_fbc', false);
			this.helper.save_cookie('_fbc', value, validDays, false);
			return value;
		}
		return ""
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_fb_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_fb = window.wp_sdtrk_engine_class.get_catcher_fb();
		if (catcher_fb.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_fb.catchPageHit(0);
						break;
					case 'Time':
						catcher_fb.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_fb.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_fb.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_fb.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

/**
* Backload the Server
**/
function wp_sdtrk_backload_fb_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_fb = window.wp_sdtrk_engine_class.get_catcher_fb();
		if (catcher_fb.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_fb.catchPageHit(1);
						break;
					case 'Time':
						catcher_fb.catchTimeHit(data[1], 1);
						break;
					case 'Scroll':
						catcher_fb.catchScrollHit(data[1], 1);
						break;
					case 'Click':
						catcher_fb.catchClickHit(data[1], 1);
						break;
					case 'Visited':
						catcher_fb.catchVisibilityHit(data[1], 1);
						break;
				}
			}
		}
	}
}
