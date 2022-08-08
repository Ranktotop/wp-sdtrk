let wp_sdtrk_event = new Wp_Sdtrk_Event();
let wp_sdtrk_buttons = [];
let wp_sdtrk_decrypter = new Wp_Sdtrk_Decrypter();
wp_sdtrk_decrypter.initialize();

//Backup for IE8
if (!Date.now) {
	Date.now = function() { return new Date().getTime(); }
}

//Start tracking after decrypter has finished loading
function wp_sdtrk_startTracker() {
	wp_sdtrk_collectEventObject();
	wp_sdtrk_collectTrackerButtons();
	wp_sdtrk_runFB();
	wp_sdtrk_runGA();
	wp_sdtrk_runTT();
	wp_sdtrk_runFL();
	wp_sdtrk_runMTC();
}


/**
* Collects all available data for wp_sdtrk_event-Object
 */
function wp_sdtrk_collectEventObject() {
	//UTMs
	wp_sdtrk_event.setUtm(wp_sdtrk_collectParams(['utm_source', 'utm_medium', 'utm_term', 'utm_content', 'utm_campaign']));
	wp_sdtrk_persistData(wp_sdtrk_event.getUtm());
	wp_sdtrk_event.setUtm(wp_sdtrk_collectCookies(wp_sdtrk_event.getUtm()));

	//Settings
	if(wp_sdtrk.trkow !== ""){
		wp_sdtrk_event.enableForce();		
	}
	else{
		wp_sdtrk_event.disableForce();
	}	
	
	//Event
	wp_sdtrk_event.setProdId(wp_sdtrk_collectParams(['prodid', 'product_id']));
	wp_sdtrk_event.addProdId('postProdId', wp_sdtrk.prodId);
	wp_sdtrk_event.setProdName(wp_sdtrk_collectParams(['product_name']));
	wp_sdtrk_event.setPageName(wp_sdtrk.pageTitle);
	wp_sdtrk_event.setPageId(wp_sdtrk.pageId);
	wp_sdtrk_event.setOrderId(wp_sdtrk_collectParams(['order_id']));
	wp_sdtrk_event.setEventId(Math.floor(Math.random() * 100) + "" + Date.now());
	wp_sdtrk_event.setValue(wp_sdtrk_collectParams(['value', 'net_amount', 'amount']));
	wp_sdtrk_event.setEventName(wp_sdtrk_collectParams(['type']));
	wp_sdtrk_event.setBrandName(wp_sdtrk.brandName);
	wp_sdtrk_event.setLandingPage(wp_sdtrk.currentDomain);
	wp_sdtrk_event.setEventTime(Date.now() / 1000 | 0);	
	wp_sdtrk_event.setEventTimeHour(wp_sdtrk_getDateTime()[0]);
	wp_sdtrk_event.setEventTimeDay(wp_sdtrk_getDateTime()[1]);
	wp_sdtrk_event.setEventTimeMonth(wp_sdtrk_getDateTime()[2]);	
	wp_sdtrk_event.setEventSource(wp_sdtrk.source); //the url of page without query
	wp_sdtrk_event.setEventSourceAdress(wp_sdtrk.addr); //the ip
	wp_sdtrk_event.setEventSourceAgent(wp_sdtrk.agent);
	wp_sdtrk_event.setEventSourceReferer(wp_sdtrk.referer); //the referrer	
	wp_sdtrk_event.setEventPath(document.location.pathname + document.location.search); //the referrer
	wp_sdtrk_event.setEventDomain(window.location.host); //the referrer
	wp_sdtrk_event.setEventUrl(window.location.href); //the referrer	
	wp_sdtrk_event.setUserFirstName(wp_sdtrk_collectParams(['buyer_first_name', 'first_name', 'firstname', 'vorname','license_data_first_name']));
	wp_sdtrk_event.setUserLastName(wp_sdtrk_collectParams(['buyer_last_name', 'last_name', 'lastname', 'nachname','license_data_last_name']));
	wp_sdtrk_event.setUserEmail(wp_sdtrk_collectParams(['buyer_email', 'email','license_data_email']));

	//Additional
	//TimeTrigger
	if (wp_sdtrk.timeTrigger) {
		wp_sdtrk_event.setTimeTrigger(wp_sdtrk.timeTrigger);
	}
	//ScrollTrigger
	if (wp_sdtrk.scrollTrigger) {
		wp_sdtrk_event.setScrollTrigger(wp_sdtrk.scrollTrigger);
	}

	//ClickTrigger
	if (wp_sdtrk.clickTrigger) {
		wp_sdtrk_event.setClickTrigger(wp_sdtrk.clickTrigger);
	}

	//Remove sensible queries from url
	window.history.replaceState({}, document.title, wp_sdtrk_getPrivacyUrl());
}

/**
* Collects all tracker-buttons
 */
function wp_sdtrk_collectTrackerButtons() {
	if (wp_sdtrk_event.getClickTrigger() !== false) {
		jQuery(document).ready(function() {
			elements = [];
			jQuery("[class*='trkbtn-']").each(function(index) {
				var el = jQuery(this);
				var classes = el.attr("class").split(/\s+/);
				for (const className of classes) {
					if (className.includes("trkbtn-")) {
						var tagName = className.replace('trkbtn-', "").replace('-trkbtn', "");
						elements.push([el, tagName]);
					}
				}
			});
			wp_sdtrk_buttons = elements;
		});
	}
}

/**
* Checks Cookie-Consent for given service
* @param {String} id The ID of the cookie
* @param {String} service The Name of the service
* @return  {Boolean|Number} The consent-state or -1 in error case
 */
function wp_sdtrk_checkServiceConsent(id, service) {
	if(wp_sdtrk_event.getForce()){
		return -1;
	}
	
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
	let data = wp_sdtrk_decrypter.getDecryptedData();

	for (const key in data) {
		if (data.hasOwnProperty(key) && key === parameterName) {
			return data[key];
		}
	}
	return null;
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

//Get Datetime
function wp_sdtrk_getDateTime() {
	var dateTime = new Array();
	var date = new Date(),
		days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
		months = ['January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
		],
		hours = ['00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08',
			'08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17',
			'17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-24'
		];
	dateTime.push(hours[date.getHours()]);
	dateTime.push(days[date.getDay()]);
	dateTime.push(months[date.getMonth()]);
	return dateTime;
}

//Get privacy valid url
function wp_sdtrk_getPrivacyUrl() {
	var privacyParams = [];	
	var firstNameParams = wp_sdtrk_event.getUserFirstName_all();
	var lastNameParams = wp_sdtrk_event.getUserLastName_all();
	var emailParams = wp_sdtrk_event.getUserEmail_all();
	
	for (const key in firstNameParams) {
			privacyParams.push(key);
	}
	
	for (const key in lastNameParams) {
			privacyParams.push(key);
	}
	
	for (const key in emailParams) {
			privacyParams.push(key);
	}
	
	var baseUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
	var urlParams = new URLSearchParams(window.location.search);
	var hash = (window.location.hash) ? "#" + window.location.hash.substring(1) : "";

	//Delete sensitive params
	privacyParams.forEach(function(param) {
		if (urlParams.has(param)) {
			urlParams.delete(param);
		}
	});
	var params = urlParams.toString();
	if (params != "") {
		return baseUrl + "?" + params + hash;
	}
	return baseUrl + hash
}