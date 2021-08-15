function clone(obj) {
	if (null == obj || "object" != typeof obj) return obj;
	var copy = obj.constructor();
	for (var attr in obj) {
		if (obj.hasOwnProperty(attr)) copy[attr] = clone(obj[attr]);
	}
	return copy;
}

function fireFBTracking(baseData, customData) {
	var baseD = clone(baseData);
	var cusD = clone(customData);
	var pixelId = (baseD['pixelId']) ? baseD['pixelId'] : false;
	var eventId = (baseD['eventId']) ? baseD['eventId'] : false;

	if (cusD.hasOwnProperty('value')) {
		delete cusD['value'];
		delete cusD['currency'];
	}

	if (pixelId !== false && eventId !== false) {
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
	}
}

function fireFBEvent(baseData, customData, eventName) {
	var pixelId = (baseData['pixelId']) ? baseData['pixelId'] : false;
	var eventId = (baseData['eventId']) ? baseData['eventId'] : false;
	var eventName = (baseData['eventName']) ? baseData['eventName'] : false;

	if (pixelId !== false && eventId !== false && eventName !== false && eventName !== 'PageView') {
		fbq('trackSingle', pixelId, eventName, customData, { eventID: eventId });
	}
}

// Load Scripts
jQuery(document).ready(function() {
	if (wp_sdtrk_fb.wp_sdtrk_fb_basedata && wp_sdtrk_fb.wp_sdtrk_fb_customdata && wp_sdtrk_fb.wp_sdtrk_fb_b_consent) {
		fireFBTracking(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);
		fireFBEvent(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);
	}
});

//Backload B Data
function backloadFB_b() {
	if (wp_sdtrk_fb.wp_sdtrk_fb_basedata && wp_sdtrk_fb.wp_sdtrk_fb_customdata && !wp_sdtrk_fb.wp_sdtrk_fb_b_consent) {
		fireFBTracking(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);
		fireFBEvent(wp_sdtrk_fb.wp_sdtrk_fb_basedata, wp_sdtrk_fb.wp_sdtrk_fb_customdata);
	}
}

//Backload S Data
function backloadFB_s() {
	var metaData = { s_consent: wp_sdtrk_fb.wp_sdtrk_fb_s_consent, eventData: wp_sdtrk_fb.wp_sdtrk_fb_eventData };
	var dataJSON = {};
	dataJSON["action"] = 'wp_sdtrk_handleAjaxCallback';
	dataJSON["func"] = 'backload_fb_s';
	dataJSON["data"] = [];
	dataJSON["meta"] = metaData;
	dataJSON['_nonce'] = wp_sdtrk_fb._nonce;
	jQuery.ajax({
		cache: false,
		type: "POST",
		url: wp_sdtrk_fb.ajax_url,
		data: dataJSON,
		success: function(response) {
		},
		error: function(xhr, status, error) {
			console.log('Status: ' + xhr.status);
			console.log('Error: ' + xhr.responseText);
		}
	});
}
