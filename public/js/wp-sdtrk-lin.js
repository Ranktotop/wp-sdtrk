class Wp_Sdtrk_Catcher_Lin {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_lin;
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
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (lin)');					
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
			//Base Pixel
			window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
			window._linkedin_data_partner_ids.push(this.localizedData.pid);
			(function(l) {
				if (!l) {
					window.lintrk = function(a, b) { window.lintrk.q.push([a, b]) };
					window.lintrk.q = []
				}
				var s = document.getElementsByTagName("script")[0];
				var b = document.createElement("script");
				b.type = "text/javascript";
				b.async = true;
				b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
				s.parentNode.insertBefore(b, s);
			})(window.lintrk);
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
					var conversions = this.get_triggeredConversions('page_view');
					for (let i = 0; i < conversions.length; i++) {
						window.lintrk('track', { conversion_id: conversions[i] });
						this.helper.debugLog(this.localizedData.dbg, { event: conversions[i] }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
				case 'Event':
					var conversions = this.get_triggeredConversions(this.event.grabEventName());
					for (let i = 0; i < conversions.length; i++) {
						window.lintrk('track', { conversion_id: conversions[i] });
						this.helper.debugLog(this.localizedData.dbg, { event: conversions[i] }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
				case 'Time':
					var conversions = this.get_triggeredConversions('timetracker-' + data.time);
					for (let i = 0; i < conversions.length; i++) {
						window.lintrk('track', { conversion_id: conversions[i] });
						this.helper.debugLog(this.localizedData.dbg, { event: conversions[i] }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
				case 'Scroll':
					var conversions = this.get_triggeredConversions('scrolltracker-' + data.percent);
					for (let i = 0; i < conversions.length; i++) {
						window.lintrk('track', { conversion_id: conversions[i] });
						this.helper.debugLog(this.localizedData.dbg, { event: conversions[i] }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
				case 'Click':
					var convId = this.is_mappedBtn(data.tag);
					if (convId !== false) {
						window.lintrk('track', { conversion_id: convId })
						this.helper.debugLog(this.localizedData.dbg, { event: convId }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
				case 'Visibility':
					var convId = this.is_mappedIV(data.tag);
					if (convId !== false) {
						window.lintrk('track', { conversion_id: convId })
						this.helper.debugLog(this.localizedData.dbg, { event: convId }, 'Fired in Browser (lin-' + handler + ')')
					}
					break;
			}
		}
	}

	/**
	* Checks if the rules for given event are true
	* @param {String} currentEventName The name of event to check
	* @return  {Array} The conversion ids of the mapped events whose conditions are met.
	*/
	get_triggeredConversions(currentEventName) {
		var triggeredConversionIds = [];
		//iterate all given events
		for (var i = 0; i < this.localizedData.map_ev.length; i++) {
			var eventName = this.localizedData.map_ev[i].eventName;
			var eventConvId = this.localizedData.map_ev[i].convId;
			var eventRules = this.localizedData.map_ev[i].rules;
			//if the element is the current one
			var state = (currentEventName === eventName);
			if (state) {
				//iterate rules for this event
				for (const key in eventRules) {
					if (eventRules.hasOwnProperty(key)) {
						switch (key) {
							case 'prodname':
								if (this.event.grabProdName() !== eventRules[key]) {
									state = false;
								}
								break;
							case 'prodid':
								if (this.event.grabProdId() !== eventRules[key]) {
									state = false;
								}
								break;
							default:
								if (this.helper.get_Param(key) !== eventRules[key]) {
									state = false;
								}
						}
					}
				}
			}
			//If the event-rules matched and mapped conversion-id is set
			if (state && eventConvId.length !== 0) {
				triggeredConversionIds.push(eventConvId);
			}
		};
		return triggeredConversionIds;
	}

	/**
	* Checks if the clicked button is mapped
	* @param {String} buttontag The tag of the button to check
	* @return  {String|Boolean} The Conversion-ID if mapped, else false
	*/
	is_mappedBtn(buttontag) {
		for (var i = 0; i < this.localizedData.map_btn.length; i++) {
			var btnTag = this.localizedData.map_btn[i].btnTag;
			var eventConvId = this.localizedData.map_btn[i].convId;
			var state = (buttontag === btnTag);
			if (state && eventConvId.length !== 0) {
				return eventConvId
			}
		};
		return false;
	}

	/**
	* Checks if the visited item is mapped
	* @param {String} itemtag The tag of the item to check
	* @return  {String|Boolean} The Conversion-ID if mapped, else false
	*/
	is_mappedIV(itemtag) {
		for (var i = 0; i < this.localizedData.map_iv.length; i++) {
			var ivTag = this.localizedData.map_iv[i].ivTag;
			var eventConvId = this.localizedData.map_iv[i].convId;
			var state = (itemtag === ivTag);
			if (state && eventConvId.length !== 0) {
				return eventConvId
			}
		};
		return false;
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_lin_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_lin = window.wp_sdtrk_engine_class.get_catcher_lin();
		if (catcher_lin.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_lin.catchPageHit(0);
						break;
					case 'Time':
						catcher_lin.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_lin.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_lin.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_lin.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}