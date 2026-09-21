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
	* Send data to server
	* @param {String} handler The handler of event
	* @param {Object} data The data to send
	 */
	sendData(handler, data) {
		if (this.isEnabled('s')) {
			this.helper.send_ajax({ event: this.event, type: 'oai', handler: handler, data: data }, this.localizedData.dbg);
		}
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
