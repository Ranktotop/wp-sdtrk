var ttEventData = false;
var ttEventData_finishedLoading = false;
var ttCustomEvent = false;
var ttScrollTracked_b = false;
var ttScrollTracked_s = false;
var ttClickedButtons_b = [];
var ttClickedButtons_s = [];
wp_sdtrk_collectTTData();

// Load Listener
jQuery(document).ready(function() {
	wp_sdtrk_track_tt();
	ttEventData_finishedLoading = true;
});

/**
* Collects all available data for FB
 */
function wp_sdtrk_collectTTData() {
	if (wp_sdtrk_tt.tt_id === "" || !wp_sdtrk_event) {
		return;
	}
	//Initialize	
	var prodName = wp_sdtrk_event.grabProdName();
	var prodId = wp_sdtrk_event.grabProdId();
	var eventId = wp_sdtrk_event.grabOrderId();
	var eventName = wp_sdtrk_convertEventNameToTt(wp_sdtrk_event.grabEventName());
	var value = wp_sdtrk_event.grabValue();
	var timeTrigger = wp_sdtrk_event.getTimeTrigger();
	var scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	var clickTrigger = wp_sdtrk_event.getClickTrigger();

	// Collect the Base-Data
	var baseData = {
		'pixelId': wp_sdtrk_tt.tt_id,
		'event': eventName,
		'event_id': eventId+"_"+wp_sdtrk_hashId()
	};
	
	//Collect the Custom-Data
	var customData = {
	};

	//Value
	if (value > 0 || eventName === 'PlaceAnOrder') {
		customData.currency = "EUR";
		customData.value = value;
		//customData.price = value;
	}

	//Product
	if (prodId !== "") {
		customData.content_id = prodId;
		customData.content_name = prodName;
		customData.content_type = "product";
		customData.quantity = 1;
	}
	else{
		if(wp_sdtrk_tt.tt_content){
			customData.content_id = wp_sdtrk_tt.tt_content;
			customData.content_name = wp_sdtrk_tt.tt_title;
			customData.content_type = "product";
			customData.quantity = 1;
		}		
	}

	//Save to global
	ttEventData = {};
	ttEventData.baseData = baseData;
	ttEventData.customData = customData;
	ttEventData.ttc = wp_sdtrk_getTtc();
	ttEventData.hashId = wp_sdtrk_hashId();
	ttEventData.timeTrigger = timeTrigger;
	ttEventData.scrollTrigger = scrollTrigger;
	ttEventData.clickTrigger = clickTrigger;
}

//Inits the tracker
function wp_sdtrk_track_tt() {
	if (ttEventData === false) {
		return;
	}
	ttEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_tt.c_tt_b_i, wp_sdtrk_tt.c_tt_b_s);
	ttEventData.sc = wp_sdtrk_checkServiceConsent(wp_sdtrk_tt.c_tt_s_i, wp_sdtrk_tt.c_tt_s_s);
	//console.log(ttEventData);

	//Browser: If consent is given
	if (ttEventData.bc !== false && wp_sdtrk_tt.tt_b_e !== "") {
		wp_sdtrk_track_tt_b();
	}
	//Server: If consent is given
	if (ttEventData.sc !== false && wp_sdtrk_tt.tt_s_e !== "") {
		wp_sdtrk_track_tt_s();
	}
}

//Fire FB Pixel on Server
function wp_sdtrk_track_tt_s() {
	var metaData = { hashId: ttEventData.hashId, ttc: ttEventData.ttc, event: wp_sdtrk_event, type: 'tt'};
	wp_sdtrk_sendAjax(metaData);

	//Time Trigger
	if (ttEventData.timeTrigger.length > 0) {
		wp_sdtrk_track_tt_s_timeTracker();
	}

	//Scroll-Trigger
	if (ttEventData.scrollTrigger !== false) {
		wp_sdtrk_track_tt_s_scrollTracker();
	}
	
	//Click-Trigger
	if (ttEventData.clickTrigger !== false) {
		wp_sdtrk_track_tt_s_clickTracker();
	}
}

//Activate time-tracker for Server
function wp_sdtrk_track_tt_s_timeTracker() {
	if (ttEventData.timeTrigger.length < 1) {
		return;
	}
	var metaData = { hashId: ttEventData.hashId, ttc: ttEventData.ttc, event: wp_sdtrk_event, type: 'tt-tt'};
	var eventId = (ttEventData.baseData['event_id']) ? ttEventData.baseData['event_id'] : false;
	if (eventId !== false) {
		ttEventData.timeTrigger.forEach((triggerTime) => {
			var time = parseInt(triggerTime);
			if (!isNaN(time)) {
				time = time * 1000;
				jQuery(document).ready(function() {
					setTimeout(function() {
						cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");
						var timeEventId = cleanEventId + "-t" + triggerTime+"_"+wp_sdtrk_hashId();
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
function wp_sdtrk_track_tt_s_scrollTracker() {
	if (ttEventData.scrollTrigger === false || ttScrollTracked_s === true) {
		return;
	}
	var metaData = { hashId: ttEventData.hashId, ttc: ttEventData.ttc, event: wp_sdtrk_event, type: 'tt-sd'};
	var eventId = (ttEventData.baseData['event_id']) ? ttEventData.baseData['event_id'] : false;
	if (eventId !== false) {
		window.addEventListener('scroll', function() {
			if (ttScrollTracked_s === true) {
				return;
			}
			var st = jQuery(this).scrollTop();
			var wh = jQuery(document).height() - jQuery(window).height();
			var target = ttEventData.scrollTrigger;
			var perc = Math.ceil((st * 100) / wh)

			if (perc >= target) {
				ttScrollTracked_s = true;
				cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");	
				var scrollEventId = cleanEventId + "-s" + ttEventData.scrollTrigger+"_"+wp_sdtrk_hashId();
				var scrollEventName = 'Scrolldepth-' + ttEventData.scrollTrigger + '-Percent';
				metaData.scrollEventId = scrollEventId;
				metaData.scrollEventName = scrollEventName;
				wp_sdtrk_sendAjax(metaData);
			}
		});
	}
}

//Activate click-tracker for Server
function wp_sdtrk_track_tt_s_clickTracker() {
	if (ttEventData.clickTrigger === false || wp_sdtrk_buttons.length < 1) {
		return;
	}
	var metaData = { hashId: ttEventData.hashId, ttc: ttEventData.ttc, event: wp_sdtrk_event, type: 'tt-bc'};
	var eventId = (ttEventData.baseData['event_id']) ? ttEventData.baseData['event_id'] : false;
	if (eventId !== false) {
		wp_sdtrk_buttons.forEach((el) => {
			jQuery(el[0]).on('click', function() {
				if (!ttClickedButtons_s.includes(el[1])) {
					ttClickedButtons_s.push(el[1]);
					cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");	
					var clickEventId = cleanEventId + "-b" + el[1]+"_"+wp_sdtrk_hashId();
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

//Fire TT Pixel in Browser
function wp_sdtrk_track_tt_b() {
	var baseData = ttEventData.baseData;
	var customData = ttEventData.customData;
	var cusD = wp_sdtrk_clone(customData);
	var pixelId = (baseData['pixelId']) ? baseData['pixelId'] : false;
	var eventId = (baseData['event_id']) ? baseData['event_id'] : false;
	var eventName = (baseData['event']) ? baseData['event'] : false;
	if (cusD.hasOwnProperty('value')) {
		delete cusD['value'];
		delete cusD['currency'];
	}
	if (pixelId !== false && eventId !== false) {
		
		//Base Pixel
		! function(w, d, t) {
    		w.TiktokAnalyticsObject = t;
		    var ttq = w[t] = w[t] || [];
		    ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"], ttq.setAndDefer = function(t, e) {
		        t[e] = function() {
		            t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
		        }
		    };
		    for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
		    ttq.instance = function(t) {
		        for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
		        return e
		    }, ttq.load = function(e, n) {
		        var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
		        ttq._i = ttq._i || {}, ttq._i[e] = [], ttq._i[e]._u = i, ttq._t = ttq._t || {}, ttq._t[e] = +new Date, ttq._o = ttq._o || {}, ttq._o[e] = n || {};
		        var o = document.createElement("script");
		        o.type = "text/javascript", o.async = !0, o.src = i + "?sdkid=" + e + "&lib=" + t;
		        var a = document.getElementsByTagName("script")[0];
		        a.parentNode.insertBefore(o, a)
		    };
		
		    ttq.load(pixelId);
		    ttq.page();
		}(window, document, 'ttq');
		
		//Identify
		if (ttEventData.hashId){
			ttq.identify({external_id: ttEventData.hashId});
		}
			

		//Event Pixel
		if (eventName !== false && eventName !== 'ViewContent') {
			ttq.track(eventName, customData,{"event_id":eventId});
		}

		//Time Trigger
		if (ttEventData.timeTrigger.length > 0) {
			wp_sdtrk_track_tt_b_timeTracker(pixelId, eventId, cusD);
		}

		//Scroll-Trigger
		if (ttEventData.scrollTrigger !== false) {
			wp_sdtrk_track_tt_b_scrollTracker(pixelId, eventId, cusD);
		}

		//Click-Trigger
		if (ttEventData.clickTrigger !== false) {
			wp_sdtrk_track_tt_b_clickTracker(pixelId, eventId, cusD);
		}
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_tt_b_timeTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || ttEventData.timeTrigger.length < 1 || !ttq) {
		return;
	}
	ttEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");					
					var timeEventId = cleanEventId + "-t" + triggerTime+"_"+wp_sdtrk_hashId();
					var timeEventName = 'Watchtime-' + triggerTime.toString() + '-Seconds';		
					ttq.track(timeEventName, customData,{"event_id":timeEventId})
				}, time);
			});
		}

	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_tt_b_scrollTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || ttEventData.scrollTrigger === false || !ttq || ttScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (ttScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = ttEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			ttScrollTracked_b = true;
			cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");					
			var scrollEventId = cleanEventId + "-s" + ttEventData.scrollTrigger+"_"+wp_sdtrk_hashId();;
			var scrollEventName = 'Scrolldepth-' + ttEventData.scrollTrigger + '-Percent';
			ttq.track(scrollEventName, customData,{"event_id":scrollEventId})
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_tt_b_clickTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || ttEventData.clickTrigger === false || !ttq || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!ttClickedButtons_b.includes(el[1])) {
				ttClickedButtons_b.push(el[1]);
				var btnCustomData = wp_sdtrk_clone(customData);
				cleanEventId = eventId.replace("_"+wp_sdtrk_hashId(), "");				
				var clickEventId = cleanEventId + "-b" + el[1]+"_"+wp_sdtrk_hashId();
				var clickEventName = 'ButtonClick';
				btnCustomData.buttonTag = el[1];					
				ttq.track(clickEventName, customData,{"event_id":clickEventId})
			}
		});

	});
}

//Backload TT Pixel on Server
function wp_sdtrk_backload_tt_s() {
	//Dont fire if the consent was already given or the backload is called to 
	if (ttEventData === false || ttEventData.sc !== false || wp_sdtrk_tt.tt_s_e === "" || !ttEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	ttEventData.sc = true
	wp_sdtrk_track_tt_s();
}

//Backload TT Pixel in Browser
function wp_sdtrk_backload_tt_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (ttEventData === false || ttEventData.bc !== false || wp_sdtrk_tt.tt_b_e === "" || !ttEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	ttEventData.bc = true
	wp_sdtrk_track_tt_b();
}

//Converts an EventName to TT-EventName
function wp_sdtrk_convertEventNameToTt(name) {
	name = (!name || name === "") ? name : name.toLowerCase();
	switch (name) {
		case 'page_view':
			return 'ViewContent';
		case 'add_to_cart':
			return 'AddToCart';
		case 'purchase':
			return 'PlaceAnOrder';
		case 'sign_up':
			return 'CompleteRegistration';
		case 'generate_lead':
			return 'SubmitForm';
		case 'begin_checkout':
			return 'InitiateCheckout';
		case 'view_item':
			return 'ViewContent';
		default:
			ttCustomEvent = true;
			return name;
	}
}

//Hash an external Id
function wp_sdtrk_hashId() {
	var validDays = 90;
	if (wp_sdtrk_getCookie('_tthash', false)) {
		var value = wp_sdtrk_getCookie('_tthash', false);
		wp_sdtrk_setCookie('_tthash', value, validDays, false);		
		return value;
	}
	else{	
		sag = wp_sdtrk_event.getEventSourceAgent();
		sad = wp_sdtrk_event.getEventSourceAdress();
		key = sag.toLowerCase()+sad.toLowerCase();
		regex = /[\W_]+/g;
		key = key.replace(regex,"")	
		var hash = 0;
		if (key.length == 0) return hash;
		for (i = 0; i < key.length; i++) {
			char = key.charCodeAt(i);
			hash = ((hash<<5)-hash)+char;
			hash = hash & hash; // Convert to 32bit integer
		}		
		if(hash<0){
			hash = hash*-1;
		}
		wp_sdtrk_setCookie('_tthash', hash, validDays, false);		
		return hash;
	}
}

//Get ttclid from cookie or param
function wp_sdtrk_getTtc() {
	var validDays = 7;
	if (wp_sdtrk_getCookie('_ttc', false)) {
		var value = wp_sdtrk_getCookie('_ttc', false);
		wp_sdtrk_setCookie('_ttc', value, validDays, false);		
		return value;
	}
	else if (wp_sdtrk_getParam("ttclid")) {		
		var clid = wp_sdtrk_getParam("ttclid");
		wp_sdtrk_setCookie('_ttc', clid, validDays, false);
		return clid;
	}
	return ""
}