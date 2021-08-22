var fbEventData = false;
var fbEventData_finishedLoading = false;
var fbCustomEvent = false;
var fbScrollTracked_b = false;
var fbScrollTracked_s = false;
var fbClickedButtons_b = [];
var fbClickedButtons_s = [];
wp_sdtrk_collectFBData();

// Load Listener
jQuery(document).ready(function() {
	wp_sdtrk_track_fb();
	fbEventData_finishedLoading = true;
});

/**
* Collects all available data for FB
 */
function wp_sdtrk_collectFBData() {
	if (wp_sdtrk_fb.fb_id === "" || !wp_sdtrk_event) {
		return;
	}
	//Initialize	
	var prodName = wp_sdtrk_event.grabProdName();
	var prodId = wp_sdtrk_event.grabProdId();
	var eventId = wp_sdtrk_event.grabOrderId();
	var eventName = wp_sdtrk_convertEventNameToFb(wp_sdtrk_event.grabEventName());
	var value = wp_sdtrk_event.grabValue();
	var timeTrigger = wp_sdtrk_event.getTimeTrigger();
	var scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	var clickTrigger = wp_sdtrk_event.getClickTrigger();

	// Collect the Base-Data
	var baseData = {
		'pixelId': wp_sdtrk_fb.fb_id,
		'eventId': eventId,
		'eventName': eventName
	};

	//Collect the Custom-Data
	var customData = {};

	//Value
	if (value > 0 || eventName === 'Purchase') {
		customData.currency = "EUR";
		customData.value = value;
	}

	//Product
	if (prodId !== "") {
		customData.content_ids = '["' + prodId + '"]';
		customData.content_type = "product";
		customData.content_name = prodName;
		customData.contents = '[{"id":"' + prodId + '","quantity":' + 1 + '}]';
	}

	//UTM
	for (var k in wp_sdtrk_event.getUtm()) {
		if (wp_sdtrk_event.getUtm()[k] !== "") {
			customData[k] = wp_sdtrk_event.getUtm()[k];
		}
	}

	//Save to global
	fbEventData = {};
	fbEventData.baseData = baseData;
	fbEventData.customData = customData;
	fbEventData.fbc = wp_sdtrk_getFbc();
	fbEventData.fbp = wp_sdtrk_getFbp();
	fbEventData.timeTrigger = timeTrigger;
	fbEventData.scrollTrigger = scrollTrigger;
	fbEventData.clickTrigger = clickTrigger;
}

//Inits the tracker
function wp_sdtrk_track_fb() {
	if (fbEventData === false) {
		return;
	}
	fbEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_fb.c_fb_b_i, wp_sdtrk_fb.c_fb_b_s);
	fbEventData.sc = wp_sdtrk_checkServiceConsent(wp_sdtrk_fb.c_fb_s_i, wp_sdtrk_fb.c_fb_s_s);
	//console.log(fbEventData);

	//Browser: If consent is given
	if (fbEventData.bc !== false && wp_sdtrk_fb.fb_b_e !== "") {
		wp_sdtrk_track_fb_b();
	}
	//Server: If consent is given
	if (fbEventData.sc !== false && wp_sdtrk_fb.fb_s_e !== "") {
		wp_sdtrk_track_fb_s();
	}
}

//Fire FB Pixel on Server
function wp_sdtrk_track_fb_s() {
	var metaData = { fbp: fbEventData.fbp, fbc: fbEventData.fbc, event: wp_sdtrk_event, type: 'fb' };
	wp_sdtrk_sendAjax(metaData);

	//Time Trigger
	if (fbEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_fb_s_timeTracker();
	}

	//Scroll-Trigger
	if (fbEventData.scrollTrigger !== false) {
		wp_sdtrk_track_fb_s_scrollTracker();
	}
	
	//Click-Trigger
	if (fbEventData.clickTrigger !== false) {
		wp_sdtrk_track_fb_s_clickTracker();
	}
}

//Activate time-tracker for Server
function wp_sdtrk_track_fb_s_timeTracker() {
	if (fbEventData.timeTrigger.length < 1) {
		return;
	}
	var metaData = { fbp: fbEventData.fbp, fbc: fbEventData.fbc, event: wp_sdtrk_event, type: 'fb-tt' };
	var eventId = (fbEventData.baseData['eventId']) ? fbEventData.baseData['eventId'] : false;
	if (eventId !== false) {
		fbEventData.timeTrigger.forEach((triggerTime) => {
			var time = parseInt(triggerTime);
			if (!isNaN(time)) {
				time = time * 1000;
				jQuery(document).ready(function() {
					setTimeout(function() {
						var timeEventId = eventId + "-t" + triggerTime;
						var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
						metaData.timeEventId = timeEventId;
						metaData.timeEventName = timeEventName;
						wp_sdtrk_sendAjax(metaData);
					}, time);
				});
			}

		});
	}
}

//Activate scroll-tracker for Server
function wp_sdtrk_track_fb_s_scrollTracker() {
	if (fbEventData.scrollTrigger === false || fbScrollTracked_s === true) {
		return;
	}
	var metaData = { fbp: fbEventData.fbp, fbc: fbEventData.fbc, event: wp_sdtrk_event, type: 'fb-sd' };
	var eventId = (fbEventData.baseData['eventId']) ? fbEventData.baseData['eventId'] : false;
	if (eventId !== false) {
		window.addEventListener('scroll', function() {
			if (fbScrollTracked_s === true) {
				return;
			}
			var st = jQuery(this).scrollTop();
			var wh = jQuery(document).height() - jQuery(window).height();
			var target = fbEventData.scrollTrigger;
			var perc = Math.ceil((st * 100) / wh)

			if (perc >= target) {
				fbScrollTracked_s = true;
				var scrollEventId = eventId + "-s" + fbEventData.scrollTrigger;
				var scrollEventName = 'Scrolldepth-' + fbEventData.scrollTrigger + '-Percent';
				metaData.scrollEventId = scrollEventId;
				metaData.scrollEventName = scrollEventName;
				wp_sdtrk_sendAjax(metaData);
			}
		});
	}
}

//Activate click-tracker for Server
function wp_sdtrk_track_fb_s_clickTracker() {
	if (fbEventData.clickTrigger === false || wp_sdtrk_buttons.length < 1) {
		return;
	}
	var metaData = { fbp: fbEventData.fbp, fbc: fbEventData.fbc, event: wp_sdtrk_event, type: 'fb-bc' };
	var eventId = (fbEventData.baseData['eventId']) ? fbEventData.baseData['eventId'] : false;
	if (eventId !== false) {
		wp_sdtrk_buttons.forEach((el) => {
			jQuery(el[0]).on('click', function() {
				if (!fbClickedButtons_s.includes(el[1])) {
					fbClickedButtons_s.push(el[1]);
					var clickEventId = eventId + "-b" + el[1];
					var clickEventName = 'ButtonClick';
					metaData.clickEventId = clickEventId;
					metaData.clickEventName = clickEventName;
					metaData.clickEventTag = el[1];
					wp_sdtrk_sendAjax(metaData);
				}
			});

		});
	}
}

//Fire FB Pixel in Browser
function wp_sdtrk_track_fb_b() {
	var baseData = fbEventData.baseData;
	var customData = fbEventData.customData;
	var cusD = wp_sdtrk_clone(customData);
	var pixelId = (baseData['pixelId']) ? baseData['pixelId'] : false;
	var eventId = (baseData['eventId']) ? baseData['eventId'] : false;
	var eventName = (baseData['eventName']) ? baseData['eventName'] : false;

	if (cusD.hasOwnProperty('value')) {
		delete cusD['value'];
		delete cusD['currency'];
	}

	if (pixelId !== false && eventId !== false) {
		//Base Pixel
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
		fbq('track', 'PageView', cusD, { eventID: eventId });

		//Event Pixel
		if (eventName !== false && eventName !== 'PageView') {
			if (fbCustomEvent) {
				fbq('trackCustom', eventName, customData, { eventID: eventId });
			}
			else {
				fbq('trackSingle', pixelId, eventName, customData, { eventID: eventId });
			}
		}

		//Time Trigger
		if (fbEventData.timeTrigger.length > 0) {
			wp_sdtrk_track_fb_b_timeTracker(pixelId, eventId, cusD);
		}

		//Scroll-Trigger
		if (fbEventData.scrollTrigger !== false) {
			wp_sdtrk_track_fb_b_scrollTracker(pixelId, eventId, cusD);
		}

		//Click-Trigger
		if (fbEventData.clickTrigger !== false) {
			wp_sdtrk_track_fb_b_clickTracker(pixelId, eventId, cusD);
		}
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_fb_b_timeTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || fbEventData.timeTrigger.length < 1 || !fbq) {
		return;
	}
	fbEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var timeEventId = eventId + "-t" + triggerTime;
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
					fbq('trackCustom', timeEventName, customData, { eventID: timeEventId });
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_fb_b_scrollTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || fbEventData.scrollTrigger === false || !fbq || fbScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (fbScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = fbEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			fbScrollTracked_b = true;
			var scrollEventId = eventId + "-s" + fbEventData.scrollTrigger;
			var scrollEventName = 'Scrolldepth-' + fbEventData.scrollTrigger + '-Percent';
			fbq('trackCustom', scrollEventName, customData, { eventID: scrollEventId });
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_fb_b_clickTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || fbEventData.clickTrigger === false || !fbq || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!fbClickedButtons_b.includes(el[1])) {
				fbClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(customData);
				var clickEventId = eventId + "-b" + el[1];
				var clickEventName = 'ButtonClick';
				btnCustomData.buttonTag = el[1];
				fbq('trackCustom', clickEventName, btnCustomData, { eventID: clickEventId });
			}
		});

	});
}

//Backload FB Pixel on Server
function wp_sdtrk_backload_fb_s() {
	//Dont fire if the consent was already given or the backload is called to 
	if (fbEventData === false || fbEventData.sc !== false || wp_sdtrk_fb.fb_s_e === "" || !fbEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	fbEventData.sc = true
	wp_sdtrk_track_fb_s();
}

//Backload FB Pixel in Browser
function wp_sdtrk_backload_fb_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (fbEventData === false || fbEventData.bc !== false || wp_sdtrk_fb.fb_b_e === "" || !fbEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	fbEventData.bc = true
	wp_sdtrk_track_fb_b();
}

//Converts an EventName to FB-EventName
function wp_sdtrk_convertEventNameToFb(name) {
	name = (!name || name === "") ? name : name.toLowerCase();
	switch (name) {
		case 'page_view':
			return 'PageView';
		case 'add_to_cart':
			return 'AddToCart';
		case 'purchase':
			return 'Purchase';
		case 'sign_up':
			return 'CompleteRegistration';
		case 'generate_lead':
			return 'Lead';
		case 'begin_checkout':
			return 'InitiateCheckout';
		case 'view_item':
			return 'ViewContent';
		default:
			fbCustomEvent = true;
			return name;
	}
}

//Get fbp or calculate new fbp
function wp_sdtrk_getFbp() {
	var validDays = 90;
	if (wp_sdtrk_getCookie('_fbp', false)) {
		var value = wp_sdtrk_getCookie('_fbp', false);
		wp_sdtrk_setCookie('_fbp', value, validDays, false);		
		return value;
	}	
	var version = 'fb';
	var subdomainIndex = '1';
	var creationTime = + new Date();
	var randomNo = parseInt(Math.random() * 10000000000);
	var cValue = version + '.' + subdomainIndex + '.' + creationTime + '.' + randomNo;
	wp_sdtrk_setCookie('_fbp', cValue, validDays, false);
	return cValue;
}

//Get fbc or calculate new fbc
function wp_sdtrk_getFbc() {
	var validDays = 90;
	if (wp_sdtrk_getCookie('_fbc', false)) {
		var value = wp_sdtrk_getCookie('_fbc', false);
		wp_sdtrk_setCookie('_fbc', value, validDays, false);		
		return value;
	}
	else if (wp_sdtrk_getParam("fbclid")) {		
		var version = 'fb';
		var subdomainIndex = '1';
		var creationTime = + new Date();
		var clid = wp_sdtrk_getParam("fbclid");
		var cValue = version + '.' + subdomainIndex + '.' + creationTime + '.' + clid;
		wp_sdtrk_setCookie('_fbc', cValue, validDays, false);
		return cValue;
	}
	return ""
}