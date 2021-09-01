var gaEventData = false;
var gaEventData_finishedLoading = false;
var gaScrollTracked_b = false;
var gaClickedButtons_b = [];
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
	var timeTrigger = wp_sdtrk_event.getTimeTrigger();
	var scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	var clickTrigger = wp_sdtrk_event.getClickTrigger();
	var isGa4 = true;

	var initData = {};
	var eventData = {};
	var campaignData = {};

	//Debug Mode	
	if (wp_sdtrk_ga.ga_debug === "1") {
		initData.debug_mode = true;
	}

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
			// Universal Analytics way (Not needed for GA4)
			if (isGa4 === false) {
				campaignData[k.replace("utm_", "")] = wp_sdtrk_event.getUtm()[k];
				campaignSet = true;
			}
		}
	}
	//Replace Campaign to support GA assignment
	if (campaignSet) {
		// Universal Analytics way (Not needed for GA4)
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
	gaEventData.timeTrigger = timeTrigger;
	gaEventData.scrollTrigger = scrollTrigger;
	gaEventData.clickTrigger = clickTrigger;
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

//Fire Analytics in Browser
function wp_sdtrk_track_ga_b() {
	var s = document.createElement('script');
	s.type = 'text/javascript';
	s.async = true;
	s.src = 'https://www.googletagmanager.com/gtag/js?id=' + wp_sdtrk_ga.ga_id;
	var x = document.getElementsByTagName('script')[0];
	x.parentNode.insertBefore(s, x);
	window.dataLayer = window.dataLayer || [];


	//V1 -working for ga4
	function gtag() { dataLayer.push(arguments); }
	gtag('js', new Date());
	gtag('config', wp_sdtrk_ga.ga_id, gaEventData.initData);

	// -> working for GA4
	//gtag('config', wp_sdtrk_ga.ga_id, {
	//	'debug_mode': true,
	//	'campaign': 'transferv20c2',
	//	'source': 'transferv20s2',
	//	'medium': 'transferv20m2',
	//	'name': 'transferv20n2',
	//	'content': 'transferv20ct2',
	//	'term': 'transferv20t2'
	//});

	// -> working for Universal Analytics
	//gtag('config', wp_sdtrk_ga.ga_id, gaEventData.initData);
	/**
	output:
	campaign: {source: "1154", medium: "def", content: "abc"}
	content: "abc"
	debug_mode: true
	medium: "def"
	 */

	var name = gaEventData.eventName;
	if (name && name !== "" && name !== 'page_view') {
		gtag("event", name, gaEventData.eventData);
	}

	//Time Trigger
	if (gaEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_ga_b_timeTracker(gtag);
	}

	//Scroll-Trigger
	if (gaEventData.scrollTrigger !== false) {
		wp_sdtrk_track_ga_b_scrollTracker(gtag);
	}

	//Click-Trigger
	if (gaEventData.clickTrigger !== false) {
		wp_sdtrk_track_ga_b_clickTracker(gtag);
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_ga_b_timeTracker(gtag) {
	if (!gtag || gaEventData.timeTrigger.length < 1) {
		return;
	}
	gaEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
					gtag("event", timeEventName, gaEventData.initData);
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_ga_b_scrollTracker(gtag) {
	if (gaEventData.scrollTrigger === false || !gtag || gaScrollTracked_b === true) {
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
			gtag("event", scrollEventName, gaEventData.initData);
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_ga_b_clickTracker(gtag) {
	if (gaEventData.clickTrigger === false || !gtag || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!gaClickedButtons_b.includes(el[1])) {
				gaClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(gaEventData.initData);
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