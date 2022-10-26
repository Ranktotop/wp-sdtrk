class Wp_Sdtrk_Catcher_Fl {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_fl;
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
		//Skip if invalid data
		if (this.localizedData.pid === "" || !this.event) {
			return;
		}
		//Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (fl)');					
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
			(function(funnel) {
				var deferredEvents = [];
				window.funnelytics = {
					events: {
						trigger: function(name, attributes, callback, opts) {
							deferredEvents.push({
								name: name,
								attributes: attributes,
								callback: callback,
								opts: opts
							});
						}
					}
				};
				var insert = document.getElementsByTagName('script')[0], script = document.createElement('script');
				script.addEventListener('load', function() {
					window.funnelytics.init(funnel, false, deferredEvents);
				});
				script.src = 'https://cdn.funnelytics.io/track-v3.js';
				script.type = 'text/javascript';
				script.async = true;
				insert.parentNode.insertBefore(script, insert);
			})(this.localizedData.pid);
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
					window.funnelytics.events.trigger('page_view', this.get_data_custom([], {}));
					this.helper.debugLog(this.localizedData.dbg, { event: 'page_view', data: this.get_data_custom([], {}) }, 'Fired in Browser (fl-' + handler + ')');
					break;
				case 'Event':
					window.funnelytics.events.trigger(this.convert_eventname(this.event.grabEventName()), this.get_data_custom());
					this.helper.debugLog(this.localizedData.dbg, { event: this.convert_eventname(this.event.grabEventName()), data: this.get_data_custom() }, 'Fired in Browser (fl-' + handler + ')');
					break;
				case 'Time':
					window.funnelytics.events.trigger(this.helper.get_EventName(handler,data.time), this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], {}));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.time), data: this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], {}) }, 'Fired in Browser (fl-' + handler + ')');
					break;
				case 'Scroll':
					window.funnelytics.events.trigger(this.helper.get_EventName(handler,data.percent), this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], {}));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.percent), data: this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], {}) }, 'Fired in Browser (fl-' + handler + ')');
					break;
				case 'Click':
					window.funnelytics.events.trigger(this.helper.get_EventName(handler,data.tag), this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], { buttonTag: data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag), data: this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], { buttonTag: data.tag }) }, 'Fired in Browser (fl-' + handler + ')');
					break;
				case 'Visibility':
					window.funnelytics.events.trigger(this.helper.get_EventName(handler,data.tag), this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], { itemTag: data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag), data: this.get_data_custom(['__currency__', '__total_in_cents__','__order__'], { itemTag: data.tag }) }, 'Fired in Browser (fl-' + handler + ')');
					break;
			}
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
		if (this.event.grabValue() > 0 || this.convert_eventname(this.event.grabEventName()) === '__commerce_action__') {
			customData.__currency__ = "EUR";
			customData.__total_in_cents__ = this.event.grabValue() * 100; //has to be in cents
			customData.__order__= this.event.grabOrderId();
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData.__sku__ = this.event.grabProdId();
			customData.__label__ = this.event.grabProdName();
		}
		//Needed for preventing pixel-helper errors with empty contents
		else {
			customData.__sku__ = this.event.getPageId();
			customData.__label__ = this.event.getPageName();
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
	* Converts an EventName to FL-EventName (only purchase is expected as specific key '__commerce_action__'. All others can be named arbitrarily)
	* @param {String} name The given event-name
	 */
	convert_eventname(name) {
		switch (name) {
			case 'purchase':
				return '__commerce_action__';
			default:
				return name;
		}
	}
}


/**
* Backload the Browser
**/
function wp_sdtrk_backload_fl_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_fl = window.wp_sdtrk_engine_class.get_catcher_fl();
		if (catcher_fl.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_fl.catchPageHit(0);
						break;
					case 'Time':
						catcher_fl.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_fl.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_fl.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_fl.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}