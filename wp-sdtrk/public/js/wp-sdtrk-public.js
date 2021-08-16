let wp_sdtrk_event = new Wp_Sdtrk_Event();
//Backup for IE8
if (!Date.now) {
    Date.now = function() { return new Date().getTime(); }
}
wp_sdtrk_collectEventObject();
//console.log(wp_sdtrk_event);

/**
* Collects all available data for wp_sdtrk_event-Object
 */
function wp_sdtrk_collectEventObject() {
	//UTMs
	wp_sdtrk_event.setUtm(wp_sdtrk_collectParams(['utm_source', 'utm_medium', 'utm_term', 'utm_content', 'utm_campaign']));
	wp_sdtrk_persistData(wp_sdtrk_event.getUtm());
	wp_sdtrk_event.setUtm(wp_sdtrk_collectCookies(wp_sdtrk_event.getUtm()));	

	//Event
	wp_sdtrk_event.setProdId(wp_sdtrk_collectParams(['prodid', 'product_id']));
	wp_sdtrk_event.addProdId('postProdId',wp_sdtrk.prodId);
	wp_sdtrk_event.setProdName(wp_sdtrk_collectParams(['product_name']));
	wp_sdtrk_event.setOrderId(wp_sdtrk_collectParams(['order_id']));
	wp_sdtrk_event.setEventId(Math.floor(Math.random() * 100) + "" + Date.now());
	wp_sdtrk_event.setValue(wp_sdtrk_collectParams(['value', 'net_amount', 'amount']));
	wp_sdtrk_event.setEventName(wp_sdtrk_collectParams(['type']));
	wp_sdtrk_event.setBrandName(wp_sdtrk.brandName);
	wp_sdtrk_event.setEventTime(Date.now() / 1000 | 0);
	wp_sdtrk_event.setEventSource(wp_sdtrk.source);
	wp_sdtrk_event.setEventSourceAdress(wp_sdtrk.addr);
	wp_sdtrk_event.setEventSourceAgent(wp_sdtrk.agent);
}

/**
* Checks Cookie-Consent for given service
* @param {String} id The ID of the cookie
* @param {String} service The Name of the service
* @return  {Boolean|Number} The consent-state or -1 in error case
 */
function wp_sdtrk_checkServiceConsent(id, service) {
	switch (service) {
		case 'borlabs':
			if (typeof window.BorlabsCookie != "undefined") {
				return window.BorlabsCookie.checkCookieConsent(id);
			}
			return -1;
		default:
			return -1;
	}
}

//Helper functions
//Store Data to cookies
function wp_sdtrk_persistData(data) {
	for (var k in data) {
		if (data[k] !== '') {
			wp_sdtrk_setCookie(k, data[k], 14);
		}
	}
}

//Collects Data from GET
function wp_sdtrk_collectParams(paramNames) {
	var data = {};
	for (const k of paramNames) {
		var value = wp_sdtrk_getParam(k);
		data[k] = (value) ? value : "";
	}
	return data;
}

//Collects Data from Cookies
function wp_sdtrk_collectCookies(data, firstparty = true) {
	for (var k in data) {
		if (data[k] == '') {
			var value = wp_sdtrk_getCookie(k, firstparty);
			data[k] = (value) ? value : "";
		}
	}
	return data;
}

//Gets a GET
function wp_sdtrk_getParam(parameterName) {
	var result = null,
		tmp = [];
	location.search
		.substr(1)
		.split("&")
		.forEach(function(item) {
			tmp = item.split("=");
			if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
		});
	return result;
}

//Gets a Cookie
function wp_sdtrk_getCookie(cookieName, firstparty = true) {
	var name = (firstparty) ? 'wpsdtrk_' + cookieName + "=" : cookieName + "=";
	var decodedCookie = decodeURIComponent(document.cookie);
	var ca = decodedCookie.split(';');
	for (var i = 0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') {
			c = c.substring(1);
		}
		if (c.indexOf(name) == 0) {
			return c.substring(name.length, c.length);
		}
	}
	return null;
}

//Sets a Cookie
function wp_sdtrk_setCookie(cookieName, cookieValue, cookieDays, firstparty = true) {
	var partyName = (firstparty) ? 'wpsdtrk_' + cookieName : cookieName;
	var a = new Date();
	a = new Date(a.getTime() + 1000 * 60 * 60 * 24 * cookieDays);
	window.document.cookie = partyName + "=" + cookieValue + ";expires=" +
		a.toGMTString() + "; path=/; domain=." + wp_sdtrk.rootDomain + ";";
}

//Converts Array to JSON
function wp_sdtrk_queryToJSON(dataItem) {
	// Convert to JSON array
	return dataItem ? JSON.parse('{"'
		+ dataItem.replace(/&/g, '","').replace(/=/g, '":"') + '"}',
		function(key, value) {
			return key === "" ? value : decodeURIComponent(value);
		}) : {};
}

//Clones an object
function wp_sdtrk_clone(obj) {
	if (null == obj || "object" != typeof obj) return obj;
	var copy = obj.constructor();
	for (var attr in obj) {
		if (obj.hasOwnProperty(attr)) copy[attr] = wp_sdtrk_clone(obj[attr]);
	}
	return copy;
}

//Sends an Ajax-Request to Server
function wp_sdtrk_sendAjax(metaData) {
	var dataJSON = {};
	dataJSON["action"] = 'wp_sdtrk_handleAjaxCallback';
	dataJSON["func"] = 'validateTracker';
	dataJSON["data"] = [];
	dataJSON["meta"] = metaData;
	dataJSON['_nonce'] = wp_sdtrk._nonce;
	jQuery.ajax({
		cache: false,
		type: "POST",
		url: wp_sdtrk.ajax_url,
		data: dataJSON,
		success: function(response) {
			//console.log(response);
		},
		error: function(xhr, status, error) {
			console.log('Status: ' + xhr.status);
			console.log('Error: ' + xhr.responseText);
		}
	});
}