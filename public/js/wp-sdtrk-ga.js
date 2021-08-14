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
	gtag('config', gaid, initData);
	return gtag;
}

function fireGAEvent(gtag, eventName, eventData) {
	gtag("event", eventName, eventData);
}


// Load Scripts
jQuery(document).ready(function() {
	if (wp_sdtrk_ga.wp_sdtrk_ga_id && wp_sdtrk_ga.wp_sdtrk_ga_b_consent) {
		var gtag = fireGATracking(wp_sdtrk_ga.wp_sdtrk_ga_id, wp_sdtrk_ga.wp_sdtrk_ga_initData);
		if (wp_sdtrk_ga.wp_sdtrk_ga_eventName && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== false && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== 'page_view' && wp_sdtrk_ga.wp_sdtrk_ga_eventData) {
			fireGAEvent(gtag, wp_sdtrk_ga.wp_sdtrk_ga_eventName, wp_sdtrk_ga.wp_sdtrk_ga_eventData);
		}
	}
})

//Backload B Data
function backloadGA_b() {
	if (wp_sdtrk_ga.wp_sdtrk_ga_id && !wp_sdtrk_ga.wp_sdtrk_ga_b_consent) {
		var gtag = fireGATracking(wp_sdtrk_ga.wp_sdtrk_ga_id, wp_sdtrk_ga.wp_sdtrk_ga_initData);
		if (wp_sdtrk_ga.wp_sdtrk_ga_eventName && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== false && wp_sdtrk_ga.wp_sdtrk_ga_eventName !== 'page_view' && wp_sdtrk_ga.wp_sdtrk_ga_eventData) {
			fireGAEvent(gtag, wp_sdtrk_ga.wp_sdtrk_ga_eventName, wp_sdtrk_ga.wp_sdtrk_ga_eventData);
		}
	}
}