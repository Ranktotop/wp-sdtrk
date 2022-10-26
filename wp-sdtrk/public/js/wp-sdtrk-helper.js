class Wp_Sdtrk_Helper {

	/**
	* Constructor
	* @param {Array} localizedData The localized engine data
	* @param {Array} data The decrypted GET-Data
	*/
	constructor(localizedData, data) {
		this.localizedData = localizedData;
		this.data = data;
	}

	/**
	* Converts an array to json
	* @param {Array} dataItem The array which shall be converted
	* @return  {String} The JSON
	*/
	convert_arrayToJson(dataItem) {
		// Convert to JSON array
		return dataItem ? JSON.parse('{"'
			+ dataItem.replace(/&/g, '","').replace(/=/g, '":"') + '"}',
			function(key, value) {
				return key === "" ? value : decodeURIComponent(value);
			}) : {};
	}

	/**
	* Clones an object
	* @param {Object} obj The object which shall be cloned
	* @return  {Object} The Clone
	*/
	clone(obj) {
		if (null == obj || "object" != typeof obj) return obj;
		var copy = obj.constructor();
		for (var attr in obj) {
			if (obj.hasOwnProperty(attr)) copy[attr] = this.clone(obj[attr]);
		}
		return copy;
	}

	/**
	* Sends an AJAX to Server
	* @param {Object} data The object which shall be sent
	* @param {Boolean} debugMode If the log shall be printed
	*/
	send_ajax(data, debugMode = false) {
		var helper = this; // for access to the logger
		var dataJSON = {};
		dataJSON["action"] = 'wp_sdtrk_handleAjaxCallback';
		dataJSON["func"] = 'validateTracker';
		dataJSON["data"] = data;
		dataJSON["debug"] = debugMode;
		dataJSON['_nonce'] = this.localizedData._nonce;
		helper.debugLog(debugMode, data, 'Sent Data to Server (' + data.type + '-' + data.handler + ')');
		jQuery.ajax({
			cache: false,
			type: "POST",
			url: this.localizedData.ajax_url,
			data: dataJSON,
			success: function(response) {
				try {
					var r = JSON.parse(response);
					if (r.state) {
						var debugging = (debugMode === '1' && r.debug && r.debug === true);
						helper.debugLog(debugging, r.state, 'Response Data from Server (' + data.type + '-' + data.handler + ')');
					}
				} catch (e) {
					helper.debugLog(debugMode, e, 'JSON Error in Response from Server (' + data.type + '-' + data.handler + ')');
				}
			},
			error: function(xhr, status, error) {
				helper.debugLog(debugMode, xhr.status, 'Server-Status (' + data.type + '-' + data.handler + ')');
				helper.debugLog(debugMode, xhr.responseText, 'Server-Error (' + data.type + '-' + data.handler + ')');
			}
		});
	}

	/**
	* Gets the time as object with hours, days and months attributes
	* @return  {Object} The Time-Object
	*/
	get_time() {
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

	/**
	* Gets the URL with stripped of privacy data
	* @param {Array} privacyData An array of arrays which holds all privacy parameters
	* @return  {String} The stripped url
	*/
	get_privacyUrl(privacyData) {
		var privacyParams = [];
		for (var i = 0; i < privacyData.length; i++) {
			var dataSet = privacyData[i];
			for (const key in dataSet) {
				privacyParams.push(key);
			}
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

	/**
	* Returns true if the given jquery element is currently in view
	* @param {Object} element The element which should be checked
	* @return  {Boolean} Is in view or not
	*/
	isInView(element) {
		var docViewTop = $(window).scrollTop();
		var docViewBottom = docViewTop + $(window).height();

		var elemTop = $(element).offset().top;
		var elemBottom = elemTop + $(element).height();

		return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
	}

	/**
	* Checks Cookie-Consent for given service
	* @param {String} id The ID of the cookie
	* @param {String} service The Name of the service
	* @param {Wp_Sdtrk_Event} event The event
	* @return  {Boolean|Number} The consent-state or -1 in error case
	*/
	has_consent(id, service, event) {
		if (event.getForce()) {
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

	/**
	* Collect value from Cookie
	* @param {String} cookieName The names of the cookies which shall be returned
	* @param {Boolean} firstparty If the cookie-name is prefixed with tag
	* @return  {String} The value of the cookie
	*/
	get_Cookie(cookieName, firstparty = true) {
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

	/**
	* Collect values from Cookies
	* @param {Array} data The names of the cookies which shall be returned
	* @param {Boolean} firstparty If the names shall be prefixed with tag
	* @return  {object} The key-value-pairs as js object
	*/
	get_Cookies(data, firstparty = true) {
		for (var k in data) {
			if (data[k] == '') {
				var value = this.get_Cookie(k, firstparty);
				data[k] = (value) ? value : "";
			}
		}
		return data;
	}

	/**
	* Save given data to cookie
	* @param {String} cookieName The name of the cookie
	* @param {String} cookieValue The value of the cookie
	* @param {Integer} cookieDays The expiration-time in days
	* @param {Boolean} firstparty Shall the cookie be prefixed with tag
	*/
	save_cookie(cookieName, cookieValue, cookieDays, firstparty = true) {
		var partyName = (firstparty) ? 'wpsdtrk_' + cookieName : cookieName;
		var a = new Date();
		a = new Date(a.getTime() + 1000 * 60 * 60 * 24 * cookieDays);
		window.document.cookie = partyName + "=" + cookieValue + ";expires=" +
			a.toGMTString() + "; path=/; domain=." + this.localizedData.rootDomain + ";";
	}

	/**
	* Save given data to cookies
	* @param {Array} data The array with values which shall be stored
	*/
	persist(data) {
		for (var k in data) {
			if (data[k] !== '') {
				this.save_cookie(k, data[k], 14);
			}
		}
	}

	/**
	* Collect value from GET Params
	* @param {String} parameterName The name of the param which shall be returned
	* @return {String} The value of the param
	*/
	get_Param(parameterName) {
		for (const key in this.data) {
			if (this.data.hasOwnProperty(key) && key === parameterName) {
				return this.data[key];
			}
		}
		return null;
	}

	/**
	* Collect data from GET Params
	* @param {Array} paramNames The names of the params which shall be returned
	* @return  {object} The key-value-pairs as js object
	*/
	get_Params(paramNames) {
		var data = {};
		for (const k of paramNames) {
			var value = this.get_Param(k);
			data[k] = (value) ? value : "";
		}
		return data;
	}

	/**
	* Debug to console if enabled
	* @param {Boolean} mode The debug mode
	* @param {Object} msg The object to log
	* @param {String} headline The Headline
	 */
	debugLog(mode, msg, headline = "") {
		if (mode || mode === '1') {
			if (headline !== "") {
				console.log(headline + ":");
			}
			console.log(msg);
		}
	}

	/**
	* Gives an Event-Name for given type
	* @param {String} type The type
	* @param {String} data additional data
	* @return {String} The Event-Name
	 */
	get_EventName(type, data = '0') {
		var map = this.localizedData.evmap;
		//if type exists in map
		if(map[type]){
			//return the value and replace variable with given data
			return map[type].replace('%', data);
		}
		return type;
	}
	
	/**
	* Checks if the current user is an admin
	* @return {Boolean} is admin
	 */
	isAdmin(){
		if(this.localizedData.admin === "1" || this.localizedData.admin === true){
			return true;
		}
		return false;
	}
}