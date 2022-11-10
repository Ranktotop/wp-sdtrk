//Backup for IE8
if (!Date.now) {
	Date.now = function() { return new Date().getTime(); }
}

class Wp_Sdtrk_Engine {

	/**
	* Constructor
	* @param {Array} decryptedData The decrypted GET-Data
	*/
	constructor(decryptedData) {
		this.data = decryptedData;
		this.event = new Wp_Sdtrk_Event();
		this.localizedData = wp_sdtrk_engine; //comes from wp
		this.helper = new Wp_Sdtrk_Helper(this.localizedData, this.data);
		this.fp = new Wp_Sdtrk_Fp();
		this.clickBtns = [];

		//this has to be global
		window.wp_sdtrk_clickedBtns = [];
		window.wp_sdtrk_visibilityItems = [];
		window.wp_sdtrk_visitedItems = [];
		window.wp_sdtrk_catchedScrolls = [];
		window.wp_sdtrk_history = [];

		//Settings
		if (this.localizedData.trkow !== "") {
			this.event.enableForce();
		}
		else {
			this.event.disableForce();
		}

		//Collect all data
		this.collect_eventData();
		this.collect_items();

		//intantiate catcher
		this.catcher_local = new Wp_Sdtrk_Catcher_Local(this.event, this.helper);
		this.catcher_fb = new Wp_Sdtrk_Catcher_Fb(this.event, this.helper);
		this.catcher_ga = new Wp_Sdtrk_Catcher_Ga(this.event, this.helper);
		this.catcher_tt = new Wp_Sdtrk_Catcher_Tt(this.event, this.helper);
		this.catcher_lin = new Wp_Sdtrk_Catcher_Lin(this.event, this.helper);
		this.catcher_fl = new Wp_Sdtrk_Catcher_Fl(this.event, this.helper);
		this.catcher_mtc = new Wp_Sdtrk_Catcher_Mtc(this.event, this.helper);

		//this has to be global
		window.wp_sdtrk_scrollDepths = this.event.getScrollTrigger()
	}

	/**
	* Get the event
	* @return  {Object} The event
	 */
	get_event() {
		return this.event;
	}

	/**
	* Get the helper
	* @return  {Wp_Sdtrk_Helper} The helper
	 */
	get_helper() {
		return this.helper;
	}

	/**
	* Get the fb catcher
	* @return  {Wp_Sdtrk_Catcher_Fb} The catcher-object
	 */
	get_catcher_fb() {
		return this.catcher_fb;
	}

	/**
	* Get the ga catcher
	* @return  {Wp_Sdtrk_Catcher_Ga} The catcher-object
	 */
	get_catcher_ga() {
		return this.catcher_ga;
	}
	/**
	* Get the tt catcher
	* @return  {Wp_Sdtrk_Catcher_Tt} The catcher-object
	 */
	get_catcher_tt() {
		return this.catcher_tt;
	}
	/**
	* Get the lin catcher
	* @return  {Wp_Sdtrk_Catcher_Lin} The catcher-object
	 */
	get_catcher_lin() {
		return this.catcher_lin;
	}

	/**
	* Get the fl catcher
	* @return  {Wp_Sdtrk_Catcher_Fl} The catcher-object
	 */
	get_catcher_fl() {
		return this.catcher_fl;
	}
	
	/**
	* Get the mtc catcher
	* @return  {Wp_Sdtrk_Catcher_Mtc} The catcher-object
	 */
	get_catcher_mtc() {
		return this.catcher_mtc;
	}

	/**
	* Collect all tracker elements
	 */
	collect_items() {
		//Search for clickTriggerItems
		if (this.event.getClickTrigger() !== false) {
			var clickElements = [];
			jQuery("[class*='trkbtn-']").each(function(index) {
				var el = jQuery(this);
				var classes = el.attr("class").split(/\s+/);
				for (const className of classes) {
					if (className.includes("trkbtn-")) {
						var tagName = className.replace('trkbtn-', "").replace('-trkbtn', "");
						clickElements.push([el, tagName]);
					}
				}
			});
			this.clickBtns = clickElements;
		}
		//Search for visibilityTriggerItems
		if (this.event.getVisibilityTrigger() !== false) {
			var watchElements = [];
			jQuery("[class*='watchitm-']").each(function(index) {
				var el = jQuery(this);
				var classes = el.attr("class").split(/\s+/);
				for (const className of classes) {
					if (className.includes("watchitm-")) {
						var tagName = className.replace('watchitm-', "").replace('-watchitm', "");
						watchElements.push([el, tagName]);
					}
				}
			});
			window.wp_sdtrk_visibilityItems = watchElements;
		}
	}

	/**
	* Fetch Data and fill up the event object
	 */
	collect_eventData() {
		
		//UTMs
		this.event.setUtm(this.helper.get_Params(this.helper.get_paramNames('utm')));		
		this.helper.persist(this.event.getUtm());
		this.event.setUtm(this.helper.get_Cookies(this.event.getUtm()));

		//Event
		this.event.setProdId(this.helper.get_Params(this.helper.get_paramNames('prodid')));
		this.event.addProdId('postProdId', this.localizedData.prodId);
		this.event.setProdName(this.helper.get_Params(this.helper.get_paramNames('prodname')));
		this.event.setPageName(this.localizedData.pageTitle);
		this.event.setPageId(this.localizedData.pageId);
		this.event.setOrderId(this.helper.get_Params(this.helper.get_paramNames('orderid')));
		this.event.setEventId(Math.floor(Math.random() * 100) + "" + Date.now());
		this.event.setValue(this.helper.get_Params(this.helper.get_paramNames('value')));
		this.event.setEventName(this.helper.get_Params(this.helper.get_paramNames('type')));
		this.event.setBrandName(this.localizedData.brandName);
		this.event.setLandingPage(this.localizedData.currentDomain);
		this.event.setEventTime(Date.now() / 1000 | 0);
		this.event.setEventTimeHour(this.helper.get_time()[0]);
		this.event.setEventTimeDay(this.helper.get_time()[1]);
		this.event.setEventTimeMonth(this.helper.get_time()[2]);
		this.event.setEventSource(this.localizedData.source); //the url of page without query
		this.event.setEventSourceAdress(this.localizedData.addr); //the ip
		this.event.setEventSourceAgent(this.localizedData.agent);
		this.event.setEventSourceReferer(this.localizedData.referer); //the referrer	
		this.event.setEventPath(document.location.pathname + document.location.search); //the page path
		this.event.setEventDomain(window.location.host); //the domain
		this.event.setEventUrl(window.location.href); //the url	
		this.event.setUserFirstName(this.helper.get_Params(this.helper.get_paramNames('firstname')));
		this.event.setUserLastName(this.helper.get_Params(this.helper.get_paramNames('lastname')));
		this.event.setUserFP(this.fp.get_fp());
		this.event.setUserEmail(this.helper.get_Params(this.helper.get_paramNames('email')));
		
		//Additional
		//TimeTrigger
		if (this.localizedData.timeTrigger) {
			this.event.setTimeTrigger(this.localizedData.timeTrigger);
		}
		//ScrollTrigger
		if (this.localizedData.scrollTrigger) {
			this.event.setScrollTrigger(this.localizedData.scrollTrigger);
		}

		//ClickTrigger
		if (this.localizedData.clickTrigger) {
			this.event.setClickTrigger(this.localizedData.clickTrigger);
		}

		//VisibilityTrigger
		if (this.localizedData.visibilityTrigger) {
			this.event.setVisibilityTrigger(this.localizedData.visibilityTrigger);
		}

		//Remove sensible queries from url
		window.history.replaceState({}, document.title, this.helper.get_privacyUrl([this.event.getUserFirstName_all(), this.event.getUserLastName_all(), this.event.getUserEmail_all()]));
	}



	/**
	* Start function for running the engine
	*/
	run() {
		//init
		var catcher_local = new Wp_Sdtrk_Catcher_Local(this.event, this.helper);
		var catcher_fb = this.catcher_fb;
		var catcher_ga = this.catcher_ga;
		var catcher_tt = this.catcher_tt;
		var catcher_lin = this.catcher_lin;
		var catcher_fl = this.catcher_fl;
		var catcher_mtc = this.catcher_mtc;

		//catchPageHit()
		catcher_local.catchPageHit();
		catcher_fb.catchPageHit(2);
		catcher_ga.catchPageHit(2);
		catcher_tt.catchPageHit(2);
		catcher_lin.catchPageHit(2);
		catcher_fl.catchPageHit(2);
		catcher_mtc.catchPageHit(2);
		window.wp_sdtrk_history.push('Page_0');

		//catchTimeHit(time)
		if (this.event.getTimeTrigger() !== false) {
			this.event.getTimeTrigger().forEach((triggerTime) => {
				var time = parseInt(triggerTime);
				if (!isNaN(time)) {
					time = time * 1000;
					jQuery(document).ready(function() {
						setTimeout(function() {
							catcher_local.catchTimeHit(triggerTime);
							catcher_fb.catchTimeHit(triggerTime, 2);
							catcher_ga.catchTimeHit(triggerTime, 2);
							catcher_tt.catchTimeHit(triggerTime, 2);
							catcher_lin.catchTimeHit(triggerTime, 2);
							catcher_fl.catchTimeHit(triggerTime, 2);
							catcher_mtc.catchTimeHit(triggerTime, 2);
							window.wp_sdtrk_history.push('Time_' + triggerTime.toString());
						}, time);
					});
				}

			});
		}

		//catchClickHit(tag)	
		if (this.event.getClickTrigger() !== false) {
			this.clickBtns.forEach((el) => {
				jQuery(el[0]).on('click', function() {
					if (!window.wp_sdtrk_clickedBtns.includes(el[1])) {
						window.wp_sdtrk_clickedBtns.push(el[1]);
						catcher_local.catchClickHit(el[1]);
						catcher_fb.catchClickHit(el[1], 2);
						catcher_ga.catchClickHit(el[1], 2);
						catcher_tt.catchClickHit(el[1], 2);
						catcher_lin.catchClickHit(el[1], 2);
						catcher_fl.catchClickHit(el[1], 2);
						catcher_mtc.catchClickHit(el[1], 2);
						window.wp_sdtrk_history.push('Click_' + el[1]);
					}
				});
			});
		}

		//catchScrollHit(percent)
		if (window.wp_sdtrk_scrollDepths !== false) {
			window.addEventListener('scroll', function() {
				if (window.wp_sdtrk_scrollDepths.length >= window.wp_sdtrk_catchedScrolls.length) {
					window.wp_sdtrk_scrollDepths.forEach((depth) => {
						if (!window.wp_sdtrk_catchedScrolls.includes(depth)) {
							var st = jQuery(window).scrollTop();
							var wh = jQuery(document).height() - jQuery(window).height();
							var perc = Math.ceil((st * 100) / wh)
							if (perc >= depth) {
								window.wp_sdtrk_catchedScrolls.push(depth);
								catcher_local.catchScrollHit(depth);
								catcher_fb.catchScrollHit(depth, 2);
								catcher_ga.catchScrollHit(depth, 2);
								catcher_tt.catchScrollHit(depth, 2);
								catcher_lin.catchScrollHit(depth, 2);
								catcher_fl.catchScrollHit(depth, 2);
								catcher_mtc.catchScrollHit(depth, 2);
								window.wp_sdtrk_history.push('Scroll_' + depth.toString());
							}
						}
					});
				}
			});
		}

		//catchVisibilityHit(tag)
		if (this.event.getVisibilityTrigger() !== false) {
			window.addEventListener('scroll', function() {
				window.wp_sdtrk_visibilityItems.forEach((el) => {
					var docViewTop = jQuery(window).scrollTop();
					var docViewBottom = docViewTop + jQuery(window).height();
					var elemTop = jQuery(el[0]).offset().top;
					var elemBottom = elemTop + jQuery(el[0]).height();
					if ((elemBottom <= docViewBottom) && (elemTop >= docViewTop) && !window.wp_sdtrk_visitedItems.includes(el[1])) {
						window.wp_sdtrk_visitedItems.push(el[1]);
						catcher_local.catchVisibilityHit(el[1]);
						catcher_fb.catchVisibilityHit(el[1], 2);
						catcher_ga.catchVisibilityHit(el[1], 2);
						catcher_tt.catchVisibilityHit(el[1], 2);
						catcher_lin.catchVisibilityHit(el[1], 2);
						catcher_fl.catchVisibilityHit(el[1], 2);
						catcher_mtc.catchVisibilityHit(el[1], 2);
						window.wp_sdtrk_history.push('Visited_' + el[1]);
					}
				});
			});
		}
	}
}

/**
* Start the engine
* @param {Wp_Sdtrk_Decrypter} decrypter The decrypter which decrypted the data
*/
function wp_sdtrk_startEngine(decrypter) {
	jQuery(document).ready(function() {
		//has to be global for access while backloading
		window.wp_sdtrk_engine_class = new Wp_Sdtrk_Engine(decrypter.getDecryptedData());
		window.wp_sdtrk_engine_class.run();
	});
}

//Initialize the Decrypter
wp_sdtrk_decrypter = new Wp_Sdtrk_Decrypter();
wp_sdtrk_decrypter.decrypt();

