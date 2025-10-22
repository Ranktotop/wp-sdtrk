class Wp_Sdtrk_Catcher_Mtm {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_mtm; // { pid: 'https://matomo.example/', sid: '123', ... }
		this.event = event;
		this.helper = helper;
		this.s_enabled = false;  // server
		this.b_enabled = false;  // browser
		this.pixelLoaded = false;

		this.validate();
	}

	/**
	* Validate if Matomo is enabled 0 = browser, 1 = server, 2 = both
	*/
	validate(target = 2) {
		if (!this.localizedData || this.localizedData.pid === "" || this.localizedData.sid === "" || !this.event) {
			return;
		}
		// Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.debug, {}, 'Skip because user is admin (matomo)');
			return;
		}

		if ((target === 2 || target === 0) &&
			this.helper.has_consent(this.localizedData.b_ci, this.localizedData.b_cs, this.event) !== false &&
			this.localizedData.b_e !== "") {
			this.b_enabled = true;
			this.loadPixel();
		}
		if ((target === 2 || target === 1) &&
			this.helper.has_consent(this.localizedData.s_ci, this.localizedData.s_cs, this.event) !== false &&
			this.localizedData.s_e !== "") {
			this.s_enabled = true;
		}
	}

	/**
	* This method checks if the backload shall be done for given type
	*/
	isOngoingBackload(type) {
		let oldState = true;
		let newState = false;

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
	*/
	isEnabled(type) {
		switch (type) {
			case 'b': return this.b_enabled;
			case 's': return this.s_enabled;
		}
		return false;
	}

	/**
	* Catch page hit
	*/
	catchPageHit(target = 2) {
		// Browser: Pageview wird beim Pixel-Init gesendet
		if (target === 1 || target === 2) {
			this.sendData('Page', { state: true });
		}
		this.catchEventHit(target);
	}

	/**
	* Catch event hit
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
	* Load the base pixel - queues a pageview
	*/
	loadPixel() {
		if (this.isEnabled('b') && !this.pixelLoaded) {
			// Landing/Referrer-Restore für den ersten Pageview
			const campaignData = this.get_storedCampaign();

			let u = this.localizedData.pid;
			if (u.slice(-1) !== '/') { u += '/'; }

			window._paq = window._paq || [];

			_paq.push(['setDocumentTitle', document.domain + "/" + (this.event.getPageName ? this.event.getPageName() : document.title)]);

			if (campaignData && campaignData.location) {
				_paq.push(['setCustomUrl', campaignData.location]);
			}
			if (campaignData && campaignData.referrer) {
				_paq.push(['setReferrerUrl', campaignData.referrer]);
			}

			_paq.push(['enableLinkTracking']);
			_paq.push(['setTrackerUrl', u + 'matomo.php']);
			_paq.push(['setSiteId', String(this.localizedData.sid)]);
			_paq.push(['trackPageView']);

			this.helper.debugLog(this.localizedData.debug, { event: 'page_view' }, 'Fired in Browser (mtm-Page)');
			this.pixelLoaded = true;
		}
	}

	/**
	* Fire data in browser via Matomo — direkter Push & Log pro Case
	*/
	fireData(handler, data) {
		if (this.isEnabled('b') && this.pixelLoaded) {
			switch (handler) {

				case 'Event': {
					// action: immer Event-Name
					const action = this.event.grabEventName ? this.event.grabEventName() : 'event';

					// name: Produktname > Produkt-ID > Seitentitel
					const prodName = this.event.grabProdName ? this.event.grabProdName() : '';
					const prodId = this.event.grabProdId ? this.event.grabProdId() : '';
					const pageName = this.event.getPageName ? this.event.getPageName() : document.title;
					const name = prodName || prodId || pageName;

					// value: wenn Produktwert vorhanden, sonst weglassen
					let rawVal = (this.event.grabValue ? this.event.grabValue() : undefined);
					let value = (rawVal === undefined || rawVal === null || rawVal === '') ? NaN : parseFloat(rawVal);

					if (!isNaN(value)) {
						_paq.push(['trackEvent', 'Wp-Sdtrk', action, name, value]);
					} else {
						_paq.push(['trackEvent', 'Wp-Sdtrk', action, name]);
					}

					this.helper.debugLog(
						this.localizedData.debug,
						{ event: action, data: { name, value: !isNaN(value) ? value : undefined } },
						'Fired in Browser (mtm-' + handler + ')'
					);
					break;
				}

				case 'Time':
					_paq.push(['trackEvent', 'Wp-Sdtrk', this.helper.get_EventName(handler, data.time), this.event.getPageName(), parseInt(data.time, 10)]);
					this.helper.debugLog(this.localizedData.debug, { event: this.helper.get_EventName(handler, data.time), data: { time: data.time } }, 'Fired in Browser (mtm-' + handler + ')');
					break;

				case 'Scroll':
					_paq.push(['trackEvent', 'Wp-Sdtrk', this.helper.get_EventName(handler, data.percent), this.event.getPageName(), parseInt(data.percent, 10)]);
					this.helper.debugLog(this.localizedData.debug, { event: this.helper.get_EventName(handler, data.percent), data: { percent: data.percent } }, 'Fired in Browser (mtm-' + handler + ')');
					break;

				case 'Click':
					_paq.push(['trackEvent', 'Wp-Sdtrk', this.helper.get_EventName(handler, data.tag), data.tag]);
					this.helper.debugLog(this.localizedData.debug, { event: this.helper.get_EventName(handler, data.tag), data: { tag: data.tag } }, 'Fired in Browser (mtm-' + handler + ')');
					break;

				case 'Visibility':
					_paq.push(['trackEvent', 'Wp-Sdtrk', this.helper.get_EventName(handler, data.tag), data.tag]);
					this.helper.debugLog(this.localizedData.debug, { event: this.helper.get_EventName(handler, data.tag), data: { tag: data.tag } }, 'Fired in Browser (mtm-' + handler + ')');
					break;
			}
		}
	}



	/**
	* Send data to server (kein cid/gclid anhängen)
	*/
	sendData(handler, data) {
		if (this.isEnabled('s')) {
			this.helper.send_ajax({ event: this.event, type: 'matomo', handler: handler, data: data }, this.localizedData.debug);
		}
	}

	/**
	* Kampagnen-/Referrer speichern (optional, analog GA-Variante)
	*/
	set_storedCampaign() {
		const paramsToFind = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];
		for (const param of paramsToFind) {
			const value = this.helper.get_Param(param);
			if (value) {
				this.helper.save_cookie('_cd', this.event.getEventUrl(), 14, true);
				break;
			}
		}
		const referrerHost = this.event.getEventSourceReferer();
		if (referrerHost.indexOf(this.event.getEventDomain()) === -1 && referrerHost !== '') {
			this.helper.save_cookie('_rd', referrerHost, 14, true);
		}
	}

	/**
	* Kampagnen-/Referrer abrufen
	*/
	get_storedCampaign() {
		const ref = this.helper.get_Cookie('_rd', true);
		const cd = this.helper.get_Cookie('_cd', true);
		if (ref || cd) {
			const campaignData = {};
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
}

/**
* Backload the Browser (Matomo)
**/
function wp_sdtrk_backload_mtm_b() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_mtm = window.wp_sdtrk_engine_class.get_catcher_mtm();
		if (catcher_mtm.isOngoingBackload('b')) {
			for (const h of window.wp_sdtrk_history) {
				const data = h.split("_");
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
* Backload the Server (Matomo)
**/
function wp_sdtrk_backload_mtm_s() {
	if (typeof window.wp_sdtrk_engine_class !== 'undefined') {
		var catcher_mtm = window.wp_sdtrk_engine_class.get_catcher_mtm();
		if (catcher_mtm.isOngoingBackload('s')) {
			for (const h of window.wp_sdtrk_history) {
				const data = h.split("_");
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
