var gaEventData = false;
var gaEventData_finishedLoading = false;
var gaScrollTracked_b = false;
var gaClickedButtons_b = [];
var gaLoaded = false;
var gaInitialized = false;

function wp_sdtrk_runGA() {
	jQuery(document).ready(function() {
		wp_sdtrk_collectGAData();
		wp_sdtrk_track_ga();
		gaEventData_finishedLoading = true;
	});
}

/**
* Collects all available data for GA
 */
function wp_sdtrk_collectGAData() {
	if (wp_sdtrk_ga.ga_id === "" || !wp_sdtrk_event) {
		return;
	}
	//Initialize	
	var prodId = wp_sdtrk_event.grabProdId();
	var eventName = wp_sdtrk_event.grabEventName();
	var value = wp_sdtrk_event.grabValue();
	var eventData = {};
	//var post_type = "page"; //product was sent only if value or purchase was sent, else page was sent. Testing this combination

	//Value
	if (value > 0 || eventName == 'purchase') {		
		//Transaction id was here and value/currency was ever sent before and ga4 worked. Testing this combination
		eventData['value'] = value;
		eventData['currency'] = "EUR";
	}

	//Items
	if (prodId !== "") {
		eventData['items'] = [{
			'id': prodId,
			'name': wp_sdtrk_event.grabProdName(),
			//'category': "SomeCategory",			
			'quantity': 1,
			'price': value,
			'brand': wp_sdtrk_event.getBrandName(),
		}]
	}
	//Meta-Data
	eventData['transaction_id'] = wp_sdtrk_event.grabOrderId();	
	eventData['non_interaction'] = true;
	eventData['page_title'] = wp_sdtrk_event.getPageName();
	eventData['post_type'] = "product";
	eventData['post_id'] = wp_sdtrk_event.getPageId();
	eventData['plugin'] = "Wp-Sdtrk";
	eventData['event_url'] = wp_sdtrk_event.getEventSource();
	eventData['user_role'] = "guest";

	//UTM
	for (var k in wp_sdtrk_event.getUtm()) {
		if (wp_sdtrk_event.getUtm()[k] !== "") {
			eventData[k] = wp_sdtrk_event.getUtm()[k];
		}
	}
	eventData['event_time'] = wp_sdtrk_getDateTime()[0];
	eventData['event_day'] = wp_sdtrk_getDateTime()[1];
	eventData['event_month'] = wp_sdtrk_getDateTime()[2];
	eventData['landing_page'] = wp_sdtrk_event.getLandingPage();

	eventData['send_to'] = wp_sdtrk_ga.ga_id;

	//Save to global
	gaEventData = {};
	gaEventData.eventData = eventData;
	gaEventData.eventName = eventName;
	gaEventData.timeTrigger = wp_sdtrk_event.getTimeTrigger();
	gaEventData.scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	gaEventData.clickTrigger = wp_sdtrk_event.getClickTrigger();
}

//Inits the tracker
function wp_sdtrk_track_ga() {
	if (gaEventData === false) {
		return;
	}
	gaEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_ga.c_ga_b_i, wp_sdtrk_ga.c_ga_b_s);
	//console.log(gaEventData);

	//Browser: If consent is given
	if (gaEventData.bc !== false && wp_sdtrk_ga.ga_b_e !== "") {		
		wp_sdtrk_track_ga_b();
	}
}

//Initialize Base-Tag
function wp_sdtrk_initialize_ga() {
	if (!gaInitialized) {
		if (!gaLoaded) {
			(function(window, document, src) {
				var a = document.createElement('script'),
					m = document.getElementsByTagName('script')[0];
				a.async = 1;
				a.src = src;
				m.parentNode.insertBefore(a, m);
			})(window, document, '//www.googletagmanager.com/gtag/js?id=' + wp_sdtrk_ga.ga_id);

			window.dataLayer = window.dataLayer || [];
			window.gtag = window.gtag || function gtag() {
				dataLayer.push(arguments);
			};

			//The config object for campaigns
			var campaignData = wp_sdtrk_getStoredCampaignData();
			if (campaignData) {
				//gtag('set', campaignData) Testing other way, see below
			}

			//init
			gtag('js', new Date());
			gaLoaded = true;
		}

		// configure custom dimensions
		var cd = {
			'dimension1': 'event_hour',
			'dimension2': 'event_day',
			'dimension3': 'event_month'
		};

		var isEcom = false;
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

		var config = {
			'link_attribution': false,
			'anonymize_ip': true,
			'custom_map': cd,
			'debug_mode': wp_sdtrk_ga.ga_debug === "1"			
		};
		var campaignData = wp_sdtrk_getStoredCampaignData(); // try to restore lost campaign-data		
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

		gtag('config', wp_sdtrk_ga.ga_id, config);

		gaInitialized = true;
	}

}

//Fire Analytics in Browser
function wp_sdtrk_track_ga_b() {
	//Load Google Analytics, if its not already loaded
	wp_sdtrk_save_ga_CampaignData();
	wp_sdtrk_initialize_ga();

	var name = gaEventData.eventName;
	if (name && name !== "" && name !== 'page_view') {
		gtag("event", name, gaEventData.eventData);
	}

	//Time Trigger
	if (gaEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_ga_b_timeTracker();
	}

	//Scroll-Trigger
	if (gaEventData.scrollTrigger !== false) {
		wp_sdtrk_track_ga_b_scrollTracker();
	}

	//Click-Trigger
	if (gaEventData.clickTrigger !== false) {
		wp_sdtrk_track_ga_b_clickTracker();
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_ga_b_timeTracker() {
	if (!gaInitialized || gaEventData.timeTrigger.length < 1) {
		return;
	}
	gaEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
					gtag("event", timeEventName, gaEventData.eventData);
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_ga_b_scrollTracker() {
	if (gaEventData.scrollTrigger === false || !gaInitialized || gaScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (gaScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = gaEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			gaScrollTracked_b = true;
			var scrollEventName = 'Scrolldepth-' + gaEventData.scrollTrigger + '-Percent';
			gtag("event", scrollEventName, gaEventData.eventData);
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_ga_b_clickTracker() {
	if (gaEventData.clickTrigger === false || !gaInitialized || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!gaClickedButtons_b.includes(el[1])) {
				gaClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(gaEventData.eventData);
				var clickEventName = 'ButtonClick';
				btnCustomData.buttonTag = el[1];
				gtag("event", clickEventName, btnCustomData);
			}
		});

	});
}

//Backload Analytics in Browser
function wp_sdtrk_backload_ga_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (gaEventData === false || gaEventData.bc !== false || wp_sdtrk_ga.ga_b_e === "" || !gaEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	gaEventData.bc = true
	wp_sdtrk_track_ga_b();
}

//For testing only
function randomInteger(min, max) {
	return Math.floor(Math.random() * (max - min + 1)) + min;
}

//Get gclid from param or cookies
function wp_sdtrk_getGclid() {
	var validDays = 90;
	if (wp_sdtrk_getParam("gclid")) {
		var clid = wp_sdtrk_getParam("gclid");
		wp_sdtrk_setCookie('_gc', clid, validDays, false);
		return clid;
	}
	else if (wp_sdtrk_getCookie('_gc', false)) {
		var value = wp_sdtrk_getCookie('_gc', false);
		wp_sdtrk_setCookie('_gc', value, validDays, false);
		return value;
	}
	return ""
}

//Check for stored campaign-data and load them
function wp_sdtrk_getStoredCampaignData() {
	var ref = wp_sdtrk_getCookie('_rd', true);
	var cd = wp_sdtrk_getCookie('_cd', true);
	if (ref || cd) {
		//The config object for campaigns
		var campaignData = {};
		if (ref) {
			campaignData['referrer'] = ref;
			//Delete Referrer
			wp_sdtrk_setCookie('_rd', '', 0, true);
		}
		if (cd) {
			campaignData['location'] = cd;
		}
		campaignData['page'] = document.location.pathname + document.location.search;
		return campaignData;
	}
	return false;
}

//Save campaign-data if one of the required params are given
function wp_sdtrk_save_ga_CampaignData() {
	var paramsToFind = ['gclid']; // 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid'
	for (var param of paramsToFind) {
		var value = wp_sdtrk_getParam(param);
		if (value) {
			wp_sdtrk_setCookie('_cd', window.location.href, 14, true);
		}
		break;
	}
	// Save referrer if it does not contain current hostname
	var referrerHost = wp_sdtrk.referer;
	if (referrerHost.indexOf(window.location.host) === -1 && referrerHost !== '') {
		wp_sdtrk_setCookie('_rd', wp_sdtrk.referer, 14, true);
	}
}