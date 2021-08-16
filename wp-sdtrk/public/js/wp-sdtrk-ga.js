var gaEventData = false;
var gaEventData_finishedLoading = false;
wp_sdtrk_collectGAData();

// Load Listener
jQuery(document).ready(function() {
	wp_sdtrk_track_ga();
	gaEventData_finishedLoading = true;
});

/**
* Collects all available data for GA
 */
function wp_sdtrk_collectGAData() {
	if (wp_sdtrk_ga.ga_id === "" || !wp_sdtrk_event) {
		return;
	}
	//Initialize	
	var prodName = wp_sdtrk_event.grabProdName();
	var prodId = wp_sdtrk_event.grabProdId();
	var eventId = wp_sdtrk_event.grabOrderId();
	var eventName = wp_sdtrk_event.grabEventName();
	var value = wp_sdtrk_event.grabValue();
	var brandName = wp_sdtrk_event.getBrandName();

	var initData = {};
	var eventData = {};
	var campaignData = {};
	
	//Debug Mode
	initData.debug_mode = wp_sdtrk_ga.ga_debug;

	//Transaction Data
	eventData.transaction_id = eventId;

	//Value
	if (value > 0 || eventName == 'purchase') {
		eventData.value = value;
		eventData.currency = "EUR";
	}
	
	//UTM
	var campaignSet = false;
	for (var k in wp_sdtrk_event.getUtm()) {
		if (wp_sdtrk_event.getUtm()[k] !== "") {
			initData[k.replace("utm_", "")] = wp_sdtrk_event.getUtm()[k];
			eventData[k.replace("utm_", "")] = wp_sdtrk_event.getUtm()[k];
			//Renaming utm_campaign to utm_name could be needed for GA4
			campaignData[k.replace("utm_", "")] = wp_sdtrk_event.getUtm()[k];
			campaignSet = true;
		}
	}
	//Replace Campaign to support GA assignment
	if (campaignSet) {
		initData.campaign = campaignData;
		eventData.campaign = campaignData;
	}

	//The Event Data
	if (prodId !== "") {
		eventData['items'] = [{
			'item_name': prodName,
			'item_id': prodId,
			'price': value,
			'item_brand': brandName,
			'quantity': 1,
		}]
	}

	//Save to global
	gaEventData = {};
	gaEventData.initData = initData;
	gaEventData.eventData = eventData;
	gaEventData.eventName = eventName;
}

//Inits the tracker
function wp_sdtrk_track_ga() {
	if (gaEventData === false) {
		return;
	}
	gaEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_ga.c_ga_b_i, wp_sdtrk_ga.c_ga_b_s);
	console.log(gaEventData);

	//Browser: If consent is given
	if (gaEventData.bc !== false && wp_sdtrk_ga.ga_b_e !== "") {
		wp_sdtrk_track_ga_b();
	}
}

//Fire Analytics in Browser
function wp_sdtrk_track_ga_b() {
	var s = document.createElement('script');
	s.type = 'text/javascript';
	s.async = true;
	s.src = 'https://www.googletagmanager.com/gtag/js?id=' + wp_sdtrk_ga.ga_id;
	var x = document.getElementsByTagName('script')[0];
	x.parentNode.insertBefore(s, x);
	window.dataLayer = window.dataLayer || [];
	function gtag() { dataLayer.push(arguments); }
	gtag('js', new Date());
	gtag('config', wp_sdtrk_ga.ga_id, gaEventData.initData);
	
	var name = gaEventData.eventName;
	if (name && name !== "" && name !== 'page_view') {
		gtag("event", name, gaEventData.eventData);
	}
}

//Backload Analytics in Browser
function wp_sdtrk_backload_ga_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (gaEventData === false || gaEventData.bc !== false || wp_sdtrk_ga.ga_b_e !== "" || !gaEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	gaEventData.bc = true
	wp_sdtrk_track_ga_b();
}