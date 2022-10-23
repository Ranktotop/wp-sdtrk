class Wp_Sdtrk_Catcher_Ga {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_ga;
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
	* Validate if fb is enabled 0 = browser, 1 = server, 2 = both
	 */
	validate(target = 2) {
		if (this.localizedData.pid === "" || !this.event) {
			return;
		}
		//This has to be fired first, because GA uses the cookie for identification
		if (this.get_Cid()) {
			this.cid = this.get_Cid();
		}
		if ((target === 2 || target === 0) && this.helper.has_consent(this.localizedData.b_ci, this.localizedData.b_cs, this.event) !== false && this.localizedData.b_e !== "") {
			this.b_enabled = true;
			//load the base pixel
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
			//Pageview is fired on pixel-init automatically
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
	* Load the base pixel - This fires a pageview hit automatically
	 */
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			//Save campaign data
			this.set_storedCampaign();

			//Base Pixel
			(function(window, document, src) {
				var a = document.createElement('script'),
					m = document.getElementsByTagName('script')[0];
				a.async = 1;
				a.src = src;
				m.parentNode.insertBefore(a, m);
			})(window, document, '//www.googletagmanager.com/gtag/js?id=' + this.localizedData.pid);

			window.dataLayer = window.dataLayer || [];
			window.gtag = window.gtag || function gtag() {
				dataLayer.push(arguments);
			};

			//The config object for campaigns
			//var campaignData = this.get_storedCampaign();
			//if (campaignData) {
			//gtag('set', campaignData) Testing other way, see below
			//}

			//init
			gtag('js', new Date());
			gtag('config', this.localizedData.pid, this.get_config());
			this.helper.debugLog(this.localizedData.dbg, { event: 'page_view', data: this.get_config()}, 'Fired in Browser (ga-Page)');
			this.pixelLoaded = true;
		}
	}

	/**
	* Get custom dimensions for GA
	* @return  {Object} The dimensions-object
	*/
	get_dimensions() {
		var isEcom = false;
		// configure custom dimensions
		var cd = {
			'dimension1': 'event_hour',
			'dimension2': 'event_day',
			'dimension3': 'event_month'
		};
		// configure Dynamic Remarketing CDs
		if (isEcom) {
			cd.dimension4 = 'ecomm_prodid';
			cd.dimension5 = 'ecomm_pagetype';
			cd.dimension6 = 'ecomm_totalvalue';
		}
		else {
			cd.dimension4 = 'dynx_itemid';
			cd.dimension5 = 'dynx_pagetype';
			cd.dimension6 = 'dynx_totalvalue';
		}
		return cd;
	}

	/**
	* Get config for gtag
	* @return  {Object} The config-object
	*/
	get_config() {
		var config = {
			'link_attribution': false,
			'anonymize_ip': true,
			'custom_map': this.get_dimensions(),
			'debug_mode': this.localizedData.debug === "1"
		};
		var campaignData = this.get_storedCampaign(); // try to restore lost campaign-data		
		if (campaignData) {
			if (typeof campaignData.referrer !== 'undefined') {
				config['page_referrer'] = campaignData.referrer
			}
			if (typeof campaignData.location !== 'undefined') {
				config['page_location'] = campaignData.location
			}
			if (typeof campaignData.page !== 'undefined') {
				config['page_path'] = campaignData.page
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
			//Fire the desired event
			switch (handler) {
				case 'Event':
					gtag("event", this.event.grabEventName(), this.get_data_custom());
					this.helper.debugLog(this.localizedData.dbg, { event: this.event.grabEventName(),data: this.get_data_custom() }, 'Fired in Browser (ga-' + handler + ')');
					break;
				case 'Time':
					gtag("event", this.helper.get_EventName(handler,data.time), this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-t" + data.time }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.time),data: this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-t" + data.time }) }, 'Fired in Browser (ga-' + handler + ')');
					break;
				case 'Scroll':
					gtag("event", this.helper.get_EventName(handler,data.percent), this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-s" + data.percent }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.percent),data: this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-s" + data.percent }) }, 'Fired in Browser (ga-' + handler + ')');
					break;
				case 'Click':
					gtag("event", this.helper.get_EventName(handler,data.tag), this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-b" + data.tag, buttonTag: data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag),data: this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-b" + data.tag, buttonTag: data.tag }) }, 'Fired in Browser (ga-' + handler + ')');
					break;
				case 'Visibility':
					gtag("event", this.helper.get_EventName(handler,data.tag), this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-v" + data.tag, itemTag: data.tag }));
					this.helper.debugLog(this.localizedData.dbg, { event: this.helper.get_EventName(handler,data.tag),data: this.get_data_custom(['transaction_id'], { transaction_id: this.event.grabOrderId() + "-v" + data.tag, itemTag: data.tag })}, 'Fired in Browser (ga-' + handler + ')');
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
			//add client id and click id
			if (this.cid !== false) {
				data.cid = this.cid;
			}
			if (this.gclid !== false) {
				data.gclid = this.gclid;
			}
			this.helper.send_ajax({ event: this.event, type: 'ga', handler: handler, data: data },this.localizedData.dbg);
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
		if (this.event.grabValue() > 0 || this.event.grabEventName() === 'purchase') {
			//Transaction id was here and value/currency was ever sent before and ga4 worked. Testing this combination
			customData['value'] = this.event.grabValue();
			customData['currency'] = "EUR";
		}
		//Product
		if (this.event.grabProdId() !== "") {
			customData['items'] = [{
				'id': this.event.grabProdId(),
				'name': this.event.grabProdName(),
				//'category': "SomeCategory",			
				'quantity': 1,
				'price': this.event.grabValue(),
				'brand': this.event.getBrandName(),
			}]
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
		customData['send_to'] = this.localizedData.pid;

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
	* Save campaign-data if one of the required params are given
	 */
	set_storedCampaign() {
		var paramsToFind = ['gclid']; // 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid'
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
	* @return  {Array} The campaign-data
	 */
	get_storedCampaign() {
		var ref = this.helper.get_Cookie('_rd', true);
		var cd = this.helper.get_Cookie('_cd', true);
		if (ref || cd) {
			//The config object for campaigns
			var campaignData = {};
			if (ref) {
				campaignData['referrer'] = ref;
				//Delete Referrer
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
	* Get saved client-id or create a new one
	* @return  {String} The client-user-id
	 */
	get_Cid() {
		var validDays = 90;
		var value = this.helper.get_Cookie('_ga', false);
		if (value) {
			//return saved client id
			var value = this.helper.get_Cookie('_ga', false);
			const regex = /[0-9]+\.[0-9]+$/gm;
			var userid = value.match(regex);
			if (Array.isArray(userid)) {
				userid = userid[0];
			}
			this.helper.save_cookie('_ga', value, validDays, false);
			return userid;
		}
		else {
			//generate new client id
			var version = 'GA1';
			var subdomainIndex = '1';
			var creationTime = + new Date();
			var randomNo = parseInt(Math.random() * 10000000000);
			var clientId = creationTime + '.' + randomNo;
			//use fp if available			
			if(this.event.getUserFP()){
				clientId = this.event.getUserFP()+ '.' +this.event.getUserFP();
			}			
			var cValue = version + '.' + subdomainIndex + '.' + clientId;
			//var identifier = randomInteger(100000000, 999999999).toString() +'.'+ randomInteger(1000000000, 9999999999).toString()
			//var userid = 'GA1.1.' + identifier;
			this.helper.save_cookie('_ga', cValue, validDays, false);
			return clientId;
		}
	}

	/**
	* Get saved click-id if available
	* @return  {String} The click id (gclid)
	 */
	get_Gclid() {
		var validDays = 90;
		if (this.helper.get_Param("gclid")) {
			var clid = this.helper.get_Param("gclid");
			this.helper.save_cookie('_gc', clid, validDays, false);
			return clid;
		}
		else if (this.helper.get_Cookie('_gc', false)) {
			var value = this.helper.get_Cookie('_gc', false);
			this.helper.save_cookie('_gc', value, validDays, false);
			return value;
		}
		return ""
	}
}

/**
* Backload the Browser
**/
function wp_sdtrk_backload_ga_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_ga = window.wp_sdtrk_engine_class.get_catcher_ga();
		if (catcher_ga.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_ga.catchPageHit(0);
						break;
					case 'Time':
						catcher_ga.catchTimeHit(data[1], 0);
						break;
					case 'Scroll':
						catcher_ga.catchScrollHit(data[1], 0);
						break;
					case 'Click':
						catcher_ga.catchClickHit(data[1], 0);
						break;
					case 'Visited':
						catcher_ga.catchVisibilityHit(data[1], 0);
						break;
				}
			}
		}
	}
}

/**
* Backload the Server
**/
function wp_sdtrk_backload_ga_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_ga = window.wp_sdtrk_engine_class.get_catcher_ga();
		if (catcher_ga.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				data = h.split("_");
				switch (data[0]) {
					case 'Page':
						catcher_ga.catchPageHit(1);
						break;
					case 'Time':
						catcher_ga.catchTimeHit(data[1], 1);
						break;
					case 'Scroll':
						catcher_ga.catchScrollHit(data[1], 1);
						break;
					case 'Click':
						catcher_ga.catchClickHit(data[1], 1);
						break;
					case 'Visited':
						catcher_ga.catchVisibilityHit(data[1], 1);
						break;
				}
			}
		}
	}
}