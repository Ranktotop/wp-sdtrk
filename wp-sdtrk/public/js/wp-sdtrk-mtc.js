var mtcEventData = false;
var mtcEventData_finishedLoading = false;
var mtcScrollTracked_b = false;
var mtcClickedButtons_b = [];
var mtcLoaded = false;
var mtcInitialized = false;
wp_sdtrk_collectMTCData();

// Load Listener
jQuery(document).ready(function() {
	wp_sdtrk_track_mtc();
	mtcEventData_finishedLoading = true;
});

/**
* Collects all available data for GA
 */
function wp_sdtrk_collectMTCData() {
	if (wp_sdtrk_mtc.mtc_id === "" || !wp_sdtrk_event) {
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
		eventData['item_id'] = prodId;
		eventData['item_name'] = wp_sdtrk_event.grabProdName();
		eventData['item_quantity'] = 1;
		eventData['item_price'] = value;
		eventData['item_brand'] = wp_sdtrk_event.getBrandName();
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
	mtcEventData = {};
	mtcEventData.eventData = eventData;
	mtcEventData.eventName = eventName;
	mtcEventData.timeTrigger = wp_sdtrk_event.getTimeTrigger();
	mtcEventData.scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	mtcEventData.clickTrigger = wp_sdtrk_event.getClickTrigger();
}

//Inits the tracker
function wp_sdtrk_track_mtc() {
	if (mtcEventData === false) {
		return;
	}
	mtcEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_mtc.c_mtc_b_i, wp_sdtrk_mtc.c_mtc_b_s);
	//console.log(gaEventData);

	//Browser: If consent is given
	if (mtcEventData.bc !== false && wp_sdtrk_mtc.mtc_b_e !== "") {
		wp_sdtrk_track_mtc_b();
	}
}

//Initialize Base-Tag
function wp_sdtrk_initialize_mtc() {
	if (!mtcInitialized) {
		if (!mtcLoaded) {
			var mauticUrl = wp_sdtrk_mtc.mtc_id;
			
			//Check if string ends with slash
			if(mauticUrl.substr(-1) !== '/'){
				mauticUrl = mauticUrl+"/mtc.js";
			}
			else{
				mauticUrl = mauticUrl+"mtc.js";
			}

			(function(w, d, t, u, n, a, m) {
				w['MauticTrackingObject'] = n;
				w[n] = w[n] || function() { (w[n].q = w[n].q || []).push(arguments) }, a = d.createElement(t),
					m = d.getElementsByTagName(t)[0]; a.async = 1; a.src = u; m.parentNode.insertBefore(a, m)
			})(window, document, 'script', mauticUrl, 'mt');
			mtcLoaded = true;
		}
		mtcInitialized = true;
	}
}

//Fire Analytics in Browser
function wp_sdtrk_track_mtc_b() {
	//Load Mautic, if its not already loaded
	wp_sdtrk_initialize_mtc();

	var name = mtcEventData.eventName;
	if (!name || name === "" || name === "page_view") {
		name = 'pageview';
	}
	
	//Fire all events with data
	//if there is a prod-id add tags like lead_12345
	if(mtcEventData.eventData['item_id'] && mtcEventData.eventData['item_id'] !== ""){
		var mtcCustomData = wp_sdtrk_clone(mtcEventData.eventData);
		mtcCustomData.tags = name+"_"+mtcEventData.eventData['item_id'];
		mt('send', name, mtcCustomData);
	}
	else{
		mt('send', name, mtcEventData.eventData);
	}

	//Time Trigger
	if (mtcEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_mtc_b_timeTracker();
	}

	//Scroll-Trigger
	if (mtcEventData.scrollTrigger !== false) {
		wp_sdtrk_track_mtc_b_scrollTracker();
	}

	//Click-Trigger
	if (mtcEventData.clickTrigger !== false) {
		wp_sdtrk_track_mtc_b_clickTracker();
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_mtc_b_timeTracker() {
	if (!mtcInitialized || mtcEventData.timeTrigger.length < 1) {
		return;
	}
	mtcEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';
					mt('send', timeEventName, mtcEventData.eventData);
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_mtc_b_scrollTracker() {
	if (mtcEventData.scrollTrigger === false || !mtcInitialized || mtcScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (mtcScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = mtcEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			mtcScrollTracked_b = true;
			var scrollEventName = 'Scrolldepth-' + mtcEventData.scrollTrigger + '-Percent';
			mt('send', scrollEventName, mtcEventData.eventData);
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_mtc_b_clickTracker() {
	if (mtcEventData.clickTrigger === false || !mtcInitialized || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!mtcClickedButtons_b.includes(el[1])) {
				mtcClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(mtcEventData.eventData);
				var clickEventName = 'ButtonClick';
				btnCustomData.buttonTag = el[1];
				mt('send', clickEventName, btnCustomData);
			}
		});

	});
}

//Backload Analytics in Browser
function wp_sdtrk_backload_mtc_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (mtcEventData === false || mtcEventData.bc !== false || wp_sdtrk_mtc.mtc_b_e === "" || !mtcEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	mtcEventData.bc = true
	wp_sdtrk_track_mtc_b();
}