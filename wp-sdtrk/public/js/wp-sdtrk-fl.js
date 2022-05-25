var flEventData = false;
var flEventData_finishedLoading = false;
var flScrollTracked_b = false;
var flClickedButtons_b = [];
var flLoaded = false;
var flInitialized = false;
wp_sdtrk_collectFLData();

// Load Listener
jQuery(document).ready(function() {
	wp_sdtrk_track_fl();
	flEventData_finishedLoading = true;
});

/**
* Collects all available data for GA
 */
function wp_sdtrk_collectFLData() {
	if (wp_sdtrk_fl.fl_id === "" || !wp_sdtrk_event) {
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
	eventData['page_title'] = wp_sdtrk_event.getPageName();
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

	//Save to global
	flEventData = {};
	flEventData.eventData = eventData;
	flEventData.eventName = eventName;
	flEventData.timeTrigger = wp_sdtrk_event.getTimeTrigger();
	flEventData.scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	flEventData.clickTrigger = wp_sdtrk_event.getClickTrigger();
}

//Inits the tracker
function wp_sdtrk_track_fl() {
	if (flEventData === false) {
		return;
	}
	flEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_fl.c_fl_b_i, wp_sdtrk_fl.c_fl_b_s);
	//console.log(gaEventData);

	//Browser: If consent is given
	if (flEventData.bc !== false && wp_sdtrk_fl.fl_b_e !== "") {
		wp_sdtrk_track_fl_b();
	}
}

//Initialize Base-Tag
function wp_sdtrk_initialize_fl() {
	if (!flInitialized) {
		if (!flLoaded) {
			var deferredEvents = [];
			window.funnelytics = {
				events: {
					trigger: function(name, attributes, callback, opts) {
						deferredEvents.push({
							name: name,
							attributes: attributes,
							callback: callback, opts: opts
						});
					}
				}
			};
			var insert = document.getElementsByTagName('script')[0], script = document.createElement('script');
			script.addEventListener('load', function() {
				window.funnelytics.init(wp_sdtrk_fl.fl_id, false, deferredEvents);
			});
			script.src = 'https://cdn.funnelytics.io/track.js'; script.type = 'text/javascript';
			script.async = true;
			insert.parentNode.insertBefore(script, insert);
			flLoaded = true;
		}
		flInitialized = true;
	}
}

//Fire Analytics in Browser
function wp_sdtrk_track_fl_b() {
	//Load Funnelytics, if its not already loaded
	wp_sdtrk_initialize_fl();
	var name = flEventData.eventName;
	
	//Track each event
	if (name && name !== "") {
		window.funnelytics.events.trigger(name, flEventData.eventData);
	}

	//Time Trigger
	if (flEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_fl_b_timeTracker();
	}

	//Scroll-Trigger
	if (flEventData.scrollTrigger !== false) {
		wp_sdtrk_track_fl_b_scrollTracker();
	}

	//Click-Trigger
	if (flEventData.clickTrigger !== false) {
		wp_sdtrk_track_fl_b_clickTracker();
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_fl_b_timeTracker() {
	if (!flInitialized || flEventData.timeTrigger.length < 1) {
		return;
	}
	flEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
					window.funnelytics.events.trigger(timeEventName, flEventData.eventData);
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_fl_b_scrollTracker() {
	if (flEventData.scrollTrigger === false || !flInitialized || flScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (flScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = flEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			flScrollTracked_b = true;
			var scrollEventName = 'Scrolldepth-' + flEventData.scrollTrigger + '-Percent';
			window.funnelytics.events.trigger(scrollEventName, flEventData.eventData);
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_fl_b_clickTracker() {
	if (flEventData.clickTrigger === false || !flInitialized || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!flClickedButtons_b.includes(el[1])) {
				flClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(flEventData.eventData);
				var clickEventName = 'ButtonClick';
				btnCustomData.buttonTag = el[1];
				window.funnelytics.events.trigger(clickEventName, btnCustomData);
			}
		});

	});
}

//Backload Analytics in Browser
function wp_sdtrk_backload_fl_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (flEventData === false || flEventData.bc !== false || wp_sdtrk_fl.fl_b_e === "" || !flEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	flEventData.bc = true
	wp_sdtrk_track_fl_b();
}