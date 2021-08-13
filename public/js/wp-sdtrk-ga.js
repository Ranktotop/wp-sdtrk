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

	function fireGATracking(gaid, initData) {
		var s = document.createElement('script');
		s.type = 'text/javascript';
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + gaid;
		var x = document.getElementsByTagName('script')[0];
		x.parentNode.insertBefore(s, x);
		window.dataLayer = window.dataLayer || [];
		function gtag() { dataLayer.push(arguments); }
		gtag('js', new Date());
		gtag('config', gaid,initData);
		return gtag;
	}

	function fireGAEvent(gtag, eventName, eventData) {
		gtag("event", eventName, eventData);
	}


	// Load Scripts
	$(document).ready(function() {
		if (wp_sdtrk_ga.wp_sdtrk_ga_id) {
			var gtag = fireGATracking(wp_sdtrk_ga.wp_sdtrk_ga_id, wp_sdtrk_ga.wp_sdtrk_ga_initData);
			if (wp_sdtrk_ga.wp_sdtrk_ga_eventName && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== false && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== 'page_view' && wp_sdtrk_ga.wp_sdtrk_ga_eventData) {
				fireGAEvent(gtag, wp_sdtrk_ga.wp_sdtrk_ga_eventName,wp_sdtrk_ga.wp_sdtrk_ga_eventData);
			}
		}
	});
})(jQuery);

