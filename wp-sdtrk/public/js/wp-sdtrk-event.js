class Wp_Sdtrk_Event {
	constructor() {
	}

	//Activate force mode
	enableForce() {
		this.force = true;
	}

	//Disable force mode
	disableForce() {
		this.force = false;
	}

	//Get force state
	getForce() {
		return this.force;
	}

	//Sets the UTM-Parameters
	setUtm(value) {
		this.utm = value;
	}

	//Gets the UTM-Parameters
	getUtm() {
		return this.utm;
	}

	//Sets the Product-ID
	setProdId(value) {
		this.prodId = value;
	}

	//Adds a Product-ID
	addProdId(key, value) {
		this.prodId[key] = value;
	}

	//Grabs the first entry in Product-ID
	grabProdId() {
		return this.grabFirstValue(this.prodId);
	}

	//Gets the Product-ID
	getProdId() {
		return this.prodId;
	}

	//Sets the Product-Name
	setProdName(value) {
		this.prodName = value;
	}

	//Gets the Product-Name
	getProdName() {
		return this.prodName;
	}

	//Sets the User-FirstName
	setUserFirstName(value) {
		this.userFirstName = value;
	}

	//Gets the User-FirstName
	getUserFirstName() {
		return this.grabFirstValue(this.userFirstName);
	}

	//Gets the User-FirstName as array
	getUserFirstName_all() {
		return this.userFirstName;
	}

	//Sets the User-LastName
	setUserLastName(value) {
		this.userLastName = value;
	}

	//Gets the User-LastName
	getUserLastName() {
		return this.grabFirstValue(this.userLastName);
	}

	//Gets the User-LastName as array
	getUserLastName_all() {
		return this.userLastName;
	}

	//Sets the User-Email
	setUserEmail(value) {
		this.userEmail = value;
	}

	//Gets the User-Email
	getUserEmail() {
		return this.grabFirstValue(this.userEmail);;
	}

	//Gets the User-Email as array
	getUserEmail_all() {
		return this.userEmail;
	}

	//Grabs the first entry in Product-Name and fallback to 'custom'
	grabProdName() {
		var name = this.grabFirstValue(this.prodName);
		return (name === "") ? 'custom' : name;
	}

	//Sets the Order-ID
	setOrderId(value) {
		this.orderId = value;
	}

	//Gets the Order-ID
	getOrderId() {
		return this.orderId;
	}

	//Grabs the first entry in Order-ID and fallback to eventId
	grabOrderId() {
		var name = this.grabFirstValue(this.orderId);
		return (name === "") ? this.getEventId() : name;
	}

	//Sets the Event-ID
	setEventId(value) {
		this.eventId = value;
	}

	//Gets the Event-ID
	getEventId() {
		return this.eventId;
	}

	//Sets the Value
	setValue(value) {
		this.value = value;
	}

	//Gets the Value
	getValue() {
		return this.value;
	}

	//Grabs the first entry in Value and validates it
	grabValue() {
		var value = this.grabFirstValue(this.value);
		return (!isNaN(+(value) + 0.0)) ? (+(value) + 0.0) : 0;
	}

	//Sets the Event-Name
	setEventName(value) {
		this.eventName = value;
	}

	//Gets the Type
	getEventName() {
		return this.eventName;
	}

	//Grabs the first entry in Event-Name and parses it
	grabEventName() {
		var name = this.grabFirstValue(this.eventName);
		return this.parseEventName(name);
	}

	//Gets the Event-Hour
	getEventTimeHour() {
		return this.eventTimeHour;
	}

	//Sets the Event-Hour
	setEventTimeHour(value) {
		this.eventTimeHour = value;
	}

	//Gets the Event-Day
	getEventTimeDay() {
		return this.eventTimeDay;
	}

	//Sets the Event-Day
	setEventTimeDay(value) {
		this.eventTimeDay = value;
	}

	//Gets the Event-Month
	getEventTimeMonth() {
		return this.eventTimeMonth;
	}

	//Sets the Event-Month
	setEventTimeMonth(value) {
		this.eventTimeMonth = value;
	}

	//Sets the Landingpage-URL
	setLandingPage(value) {
		this.landingPage = value;
	}

	//Gets the Landingpage-URL
	getLandingPage() {
		return this.landingPage;
	}

	//Sets the Brand-Name
	setBrandName(value) {
		this.brandName = value;
	}

	//Gets the Brand-Name
	getBrandName() {
		return this.brandName;
	}

	//Sets the Page-Name
	setPageName(value) {
		this.pageName = value;
	}

	//Gets the Page-Name
	getPageName() {
		return this.pageName;
	}

	//Sets the Page-ID
	setPageId(value) {
		this.pageId = value;
	}

	//Gets the Page-ID
	getPageId() {
		return this.pageId;
	}

	//Sets the Event-Time
	setEventTime(value) {
		this.eventTime = value;
	}

	//Gets the Brand-Name
	getEventTime() {
		return this.eventTime;
	}

	//Sets the Event-Source e.g the url of page without query
	setEventSource(value) {
		this.eventSource = value;
	}

	//Gets the Event-Source e.g the url of page without query
	getEventSource() {
		return this.eventSource;
	}

	//Gets the full url of page with query
	getEventUrl() {
		return this.eventUrl;
	}

	//Sets the full url of page with query
	setEventUrl(value) {
		this.eventUrl = value;
	}

	//Gets the path of page with subpages and query
	getEventPath() {
		return this.eventPath;
	}

	//Sets the path of page with subpages and query
	setEventPath(value) {
		this.eventPath = value;
	}

	//Gets the domain (without protocol)
	getEventDomain() {
		return this.eventDomain;
	}

	//Sets the domain (without protocol)
	setEventDomain(value) {
		this.eventDomain = value;
	}

	//Sets the Event-SourceAgent
	setEventSourceAgent(value) {
		this.eventSourceAgent = value;
	}

	//Gets the Event-SourceAgent
	getEventSourceAgent() {
		return this.eventSourceAgent;
	}

	//Sets the Event-SourceReferer
	setEventSourceReferer(value) {
		this.eventSourceReferer = value;
	}

	//Gets the Event-SourceReferer
	getEventSourceReferer() {
		return this.eventSourceReferer;
	}

	//Sets the Event-SourceAdress e.g ip
	setEventSourceAdress(value) {
		this.eventSourceAdress = value;
	}

	//Gets the Event-SourceAdress e.g ip
	getEventSourceAdress() {
		return this.eventSourceAdress;
	}

	//Sets the Time-Triggers
	setTimeTrigger(value) {
		this.timeTrigger = value;
	}

	//Gets the Time-Triggers
	getTimeTrigger() {
		if (this.timeTrigger) {
			return this.timeTrigger;
		}
		return [];
	}

	//Sets the Scroll-Trigger
	setScrollTrigger(value) {
		this.scrollTrigger = value;
	}

	//Gets the Scroll-Trigger
	getScrollTrigger() {
		if (this.scrollTrigger) {
			return this.scrollTrigger;
		}
		return false;
	}

	//Sets the Click-Trigger
	setClickTrigger(value) {
		this.clickTrigger = value;
	}

	//Gets the Click-Trigger
	getClickTrigger() {
		if (this.clickTrigger === '1') {
			return true;
		}
		return false;
	}

	//Sets the Visibility-Trigger
	setVisibilityTrigger(value) {
		this.visibilityTrigger = value;
	}

	//Gets the Visibility-Trigger
	getVisibilityTrigger() {
		if (this.visibilityTrigger === '1') {
			return true;
		}
		return false;
	}

	//Grab first Value of object
	grabFirstValue(customObj) {
		for (var k in customObj) {
			if (customObj[k] !== "") {
				return customObj[k];
			}
		}
		return "";
	}

	//Converts the Event-Name to GA-default
	parseEventName(name) {
		//convert to lowercase without underlines or hyphens if valid value
		name = (!name || name === "") ? name : name.toLowerCase().replace(/_/g, "").replace(/-/g, "");

		//if no name is set but there is an order-id, setup as purchase
		if (name === "" && this.grabFirstValue(this.orderId) !== "") {
			return "purchase";
		}
		//if no name is set but there is an product-id, setup as view-item
		if (name === "" && this.grabProdId() !== "") {
			return 'view_item';
		}
		//map typical names (eg. from fb or tt) to ga-events
		var map = {
			//page_view: ['pageview', 'viewpage', 'view'], //The page-view
			view_item: ['viewitem', 'viewcontent'], //If an page is visited which relates to an product
			generate_lead: ['generatelead', 'lead', 'submitform'], // The leads before doi
			sign_up: ['signup', 'completeregistration', 'doi'], // The leads after doi
			add_to_cart: ['addtocart', 'atc'],  //The add to cart
			begin_checkout: ['begincheckout', 'initiatecheckout'], //The start of the checkout process
			purchase: ['purchase', 'placeanorder', 'sale'],  //The purchase
		}
		for (var key in map) {
			if (map.hasOwnProperty(key)) {
				if(map[key].includes(name)){
					return key;
				}
			}
		}
		return false;
	}
}