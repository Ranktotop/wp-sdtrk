class Wp_Sdtrk_Event {
	constructor() {
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

	//Sets the Event-Source
	setEventSource(value) {
		this.eventSource = value;
	}

	//Gets the Event-Source
	getEventSource() {
		return this.eventSource;
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

	//Sets the Event-SourceAdress
	setEventSourceAdress(value) {
		this.eventSourceAdress = value;
	}

	//Gets the Event-SourceAdress
	getEventSourceAdress() {
		return this.eventSourceAdress;
	}
	
	//Sets the Time-Triggers
	setTimeTrigger(value) {
		this.timeTrigger = value;
	}
	
	//Gets the Time-Triggers
	getTimeTrigger() {
		if(this.timeTrigger){
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
		if(this.scrollTrigger){
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
		if(this.clickTrigger === '1'){
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

	//Converts the Event-Name to GA
	parseEventName(name) {
		name = (!name || name === "") ? name : name.toLowerCase();
		if (name === "" && this.grabFirstValue(this.orderId) !== "") {
			return "purchase";
		}
		if (name === "" && this.grabProdId() !== "") {
			return 'view_item';
		}		
		switch (name) {
			case 'pageview':
				return 'page_view';
			case 'addtocart':
				return 'add_to_cart';
			case 'purchase':
				return 'purchase';
			case 'completeregistration':
				return 'sign_up';
			case 'lead':
				return 'generate_lead';
			case 'initiatecheckout':
				return 'begin_checkout';
			case 'viewcontent':
				return 'view_item';
			default:
				return name === "" ? false : name;
		}
	}
}