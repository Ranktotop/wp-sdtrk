class Wp_Sdtrk_Catcher_Local {

	/**
	* Constructor
	* @param {Wp_Sdtrk_Event} event The event
	* @param {Wp_Sdtrk_Helper} helper The helper
	*/
	constructor(event, helper) {
		this.localizedData = wp_sdtrk_local;
		this.event = event;
		this.helper = helper;
		this.enabled = (this.localizedData.enabled === "" || !this.event) ? false : true;
	}

	/**
	* Catch page hit
	 */
	catchPageHit() {
		this.sendData('Page', { state: true });
		this.catchEventHit();
	}

	/**
	* Catch page hit
	 */
	catchEventHit() {
		if (this.event.grabEventName()) {
			this.sendData('Event', { state: true });
		}
	}

	/**
	* Catch scroll hit
	* @param {String} percent The % of the hit
	 */
	catchScrollHit(percent) {
		this.sendData('Scroll', { percent: percent });
	}

	/**
	* Catch time hit
	* @param {String} time The time of the hit
	 */
	catchTimeHit(time) {
		this.sendData('Time', { time: time });
	}

	/**
	* Catch click hit
	* @param {String} tag The tag of the hit
	 */
	catchClickHit(tag) {
		this.sendData('Click', { tag: tag });
	}

	/**
	* Catch visibility hit
	* @param {String} tag The tag of the hit
	 */
	catchVisibilityHit(tag) {
		this.sendData('Visibility', { tag: tag });
	}

	/**
	* Send data to server
	* @param {String} handler The handler of event
	* @param {Object} data The data to send
	 */
	sendData(handler, data) {
		//Skip if admin
		if (this.helper.isAdmin()) {
			this.helper.debugLog(this.localizedData.dbg, {}, 'Skip because user is admin (local)');					
			return;
		}
		if (this.enabled) {
			this.helper.send_ajax({ event: this.event, type: 'local', handler: handler, data: data }, this.localizedData.dbg);
		}
	}
}