(function($) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	function clone(obj) {
        if (null == obj || "object" != typeof obj) return obj;
        var copy = obj.constructor();
        for (var attr in obj) {
            if (obj.hasOwnProperty(attr)) copy[attr] = clone(obj[attr]);
        }
        return copy;
    }

	function fireFBTracking(baseData, customData) {
		var baseD = clone(baseData);
		var cusD = clone(customData);
		var pixelId = (baseD['pixelId']) ? baseD['pixelId'] : false;
		var eventId = (baseD['eventId']) ? baseD['eventId'] : false;
		
		if(cusD.hasOwnProperty('value')){
			delete cusD['value'];
			delete cusD['currency'];
		}

		if (pixelId !== false && eventId !== false) {
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
			fbq('init', pixelId);
			fbq('track', 'PageView', cusD, {eventID: eventId });
		}
	}
	
	function fireFBEvent(baseData, customData, eventName){
		var pixelId = (baseData['pixelId']) ? baseData['pixelId'] : false;
		var eventId = (baseData['eventId']) ? baseData['eventId'] : false;
		var eventName = (baseData['eventName']) ? baseData['eventName'] : false;

		if (pixelId !== false && eventId !== false && eventName !== false && eventName !=='PageView') {
			fbq('trackSingle', pixelId, eventName, customData, {eventID: eventId});
		}
	}

	// Load Scripts
	$(document).ready(function() {
		if (wp_sdtrk_fb.wp_sdtrk_fb_basedata && wp_sdtrk_fb.wp_sdtrk_fb_customdata) {
			fireFBTracking(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);			
			fireFBEvent(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);
		}
	});
})(jQuery);

