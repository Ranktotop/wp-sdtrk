(function( $ ) {
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
	 
	function fireFBTracking(fbPixelId,eventid){
		!function(f,b,e,v,n,t,s)
		  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		  n.queue=[];t=b.createElement(e);t.async=!0;
		  t.src=v;s=b.getElementsByTagName(e)[0];
		  s.parentNode.insertBefore(t,s)}(window, document,'script',
		  'https://connect.facebook.net/en_US/fbevents.js');  
		  fbq('init', fbPixelId);
		  fbq('track', 'PageView', {eventID: eventid});
	}

	// Load Scripts
	$(document).ready(function() {
		if(wp_sdtrk_fb.wp_sdtrk_fb_data && wp_sdtrk_fb.wp_sdtrk_fb_data['baseData'] && wp_sdtrk_fb.wp_sdtrk_fb_data['customData']){
			var baseData = wp_sdtrk_fb.wp_sdtrk_fb_data['baseData'];
			var customData = wp_sdtrk_fb.wp_sdtrk_fb_data['customData'];
			var eventId = baseData['eventId'];
			var pixelId = baseData['pixelId'];
			if(eventId && pixelId){
				fireFBTracking(pixelId,eventId);
			}
		}
	});
})( jQuery );

