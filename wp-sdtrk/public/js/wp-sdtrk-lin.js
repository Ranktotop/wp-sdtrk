var linEventData = false;
var linEventData_finishedLoading = false;
var linCustomEvent = false;
var linScrollTracked_b = false;
var linClickedButtons_b = [];

function wp_sdtrk_runLIN() {
	jQuery(document).ready(function() {
		wp_sdtrk_collectLINData();
		wp_sdtrk_track_lin();
		linEventData_finishedLoading = true;
	});
}

/**
* Collects all available data for LIN
 */
function wp_sdtrk_collectLINData() {
	if (wp_sdtrk_lin.lin_id === "" || !wp_sdtrk_event) {
		return;
	}
	//Initialize	
	var convMap = wp_sdtrk_lin.lin_map;
	var convBtnMap = wp_sdtrk_lin.lin_btnmap;
	var prodName = wp_sdtrk_event.grabProdName();
	var prodId = wp_sdtrk_event.grabProdId();
	var eventId = wp_sdtrk_event.grabOrderId();
	var eventName = wp_sdtrk_event.grabEventName();
	var value = wp_sdtrk_event.grabValue();
	var timeTrigger = wp_sdtrk_event.getTimeTrigger();
	var scrollTrigger = wp_sdtrk_event.getScrollTrigger();
	var clickTrigger = wp_sdtrk_event.getClickTrigger();
	var firstName = wp_sdtrk_event.getUserFirstName();
	var lastName = wp_sdtrk_event.getUserLastName();
	var email = wp_sdtrk_event.getUserEmail();

	// Collect the Base-Data
	var baseData = {
		'pixelId': wp_sdtrk_lin.lin_id,
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

	//User-Data
	var userData = {};
	if (firstName !== "") {
		userData.fn = firstName;
	}
	if (lastName !== "") {
		userData.ln = lastName;
	}
	if (email !== "") {
		userData.em = email;
	}

	//Save to global
	linEventData = {};
	linEventData.baseData = baseData;
	linEventData.customData = customData;
	linEventData.mapData = convMap;
	linEventData.btnmapData = convBtnMap;
	linEventData.timeTrigger = timeTrigger;
	linEventData.scrollTrigger = scrollTrigger;
	linEventData.clickTrigger = clickTrigger;
	linEventData.userData = userData;
}

//Inits the tracker
function wp_sdtrk_track_lin() {
	if (linEventData === false) {
		return;
	}
	linEventData.bc = wp_sdtrk_checkServiceConsent(wp_sdtrk_lin.c_lin_b_i, wp_sdtrk_lin.c_lin_b_s);

	//Browser: If consent is given
	if (linEventData.bc !== false && wp_sdtrk_lin.lin_b_e !== "") {
		wp_sdtrk_track_lin_b();
	}
}

//Fire LIN Pixel in Browser
function wp_sdtrk_track_lin_b() {
	var baseData = linEventData.baseData;
	var customData = linEventData.customData;
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
		window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
		window._linkedin_data_partner_ids.push(pixelId);
		(function(l) {
			if (!l) {
				window.lintrk = function(a, b) { window.lintrk.q.push([a, b]) };
				window.lintrk.q = []
			}
			var s = document.getElementsByTagName("script")[0];
			var b = document.createElement("script");
			b.type = "text/javascript";
			b.async = true;
			b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
			s.parentNode.insertBefore(b, s);
		})(window.lintrk);

		//Event Pixel
		var activeEvents = wp_sdtrk_getActiveEvents(eventName);
		activeEvents.forEach(
			element => window.lintrk('track', { conversion_id: element })
		);

		//Time Trigger
		if (linEventData.timeTrigger.length > 0) {
			wp_sdtrk_track_lin_b_timeTracker(pixelId, eventId, cusD);
		}

		//Scroll-Trigger
		if (linEventData.scrollTrigger !== false) {
			wp_sdtrk_track_lin_b_scrollTracker(pixelId, eventId, cusD);
		}

		//Click-Trigger
		if (linEventData.clickTrigger !== false) {
			wp_sdtrk_track_lin_b_clickTracker(pixelId, eventId, cusD);
		}
	}
}

//Activate time-tracker for Browser
function wp_sdtrk_track_lin_b_timeTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || linEventData.timeTrigger.length < 1) {
		return;
	}
	linEventData.timeTrigger.forEach((triggerTime) => {
		var time = parseInt(triggerTime);
		if (!isNaN(time)) {
			time = time * 1000;
			jQuery(document).ready(function() {
				setTimeout(function() {
					var activeEvents = wp_sdtrk_getActiveEvents('timetracker-' + triggerTime);
					activeEvents.forEach(
						element => window.lintrk('track', { conversion_id: element })
					);
				}, time);
			});
		}
	});
}

//Activate scroll-tracker for Browser
function wp_sdtrk_track_lin_b_scrollTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || linEventData.scrollTrigger === false || linScrollTracked_b === true) {
		return;
	}
	window.addEventListener('scroll', function() {
		if (linScrollTracked_b === true) {
			return;
		}
		var st = jQuery(this).scrollTop();
		var wh = jQuery(document).height() - jQuery(window).height();
		var target = linEventData.scrollTrigger;
		var perc = Math.ceil((st * 100) / wh)

		if (perc >= target) {
			linScrollTracked_b = true;
			var activeEvents = wp_sdtrk_getActiveEvents('scrolltracker-' + linEventData.scrollTrigger);
			activeEvents.forEach(
				element => window.lintrk('track', { conversion_id: element })
			);
		}
	});
}

//Activate click-tracker for Browser
function wp_sdtrk_track_lin_b_clickTracker(pixelId, eventId, customData) {
	if (pixelId === false || eventId === false || linEventData.clickTrigger === false || wp_sdtrk_buttons.length < 1) {
		return;
	}
	wp_sdtrk_buttons.forEach((el) => {
		jQuery(el[0]).on('click', function() {
			if (!linClickedButtons_b.includes(el[1])) {
				linClickedButtons_b.push(el[1]);
				var convId = wp_sdtrk_checkBtnMapping(el[1]);
				if (convId !== false) {
					window.lintrk('track', { conversion_id: convId })
				}
			}
		});

	});
}

//Backload LIN Pixel in Browser
function wp_sdtrk_backload_lin_b() {
	//Dont fire if the consent was already given or the backload is called to 
	if (linEventData === false || linEventData.bc !== false || wp_sdtrk_lin.lin_b_e === "" || !linEventData_finishedLoading) {
		return;
	}
	//Save the given consent
	linEventData.bc = true
	wp_sdtrk_track_lin_b();
}

//Checks if the rules for given event are true
function wp_sdtrk_getActiveEvents(currentEventName) {
	var activeEvents = [];
	for (var i = 0; i < linEventData.mapData.length; i++) {
		var eventName = linEventData.mapData[i].eventName;
		var eventConvId = linEventData.mapData[i].convId;
		var eventRules = linEventData.mapData[i].rules;
		var state = (currentEventName === eventName);
		if (state) {
			for (const key in eventRules) {
				if (eventRules.hasOwnProperty(key)) {
					switch (key) {
						case 'prodname':
							if (wp_sdtrk_event.grabProdName() !== eventRules[key]) {
								state = false;
							}
							break;
						case 'prodid':
							if (wp_sdtrk_event.grabProdId() !== eventRules[key]) {
								state = false;
							}
							break;
						default:
							if (wp_sdtrk_getParam(key) !== eventRules[key]) {
								state = false;
							}
					}
				}
			}
		}
		if (state && eventConvId.length !== 0) {
			activeEvents.push(eventConvId);
		}
	};
	return activeEvents;
}

//Checks if the clicked button is mapped
function wp_sdtrk_checkBtnMapping(buttontag) {
	for (var i = 0; i < linEventData.btnmapData.length; i++) {
		var btnTag = linEventData.btnmapData[i].btnTag;
		var eventConvId = linEventData.btnmapData[i].convId;
		var state = (buttontag === btnTag);
		if (state && eventConvId.length !== 0) {
			return eventConvId
		}
	};
	return false;
}