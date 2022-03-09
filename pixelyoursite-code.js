/* global pysOptions */

// https://bitbucket.org/pixelyoursite/pys_pro_7/issues/7/possible-ie-11-error
// https://tc39.github.io/ecma262/#sec-array.prototype.includes
if (!Array.prototype.includes) {
    Object.defineProperty(Array.prototype, 'includes', {
        value: function (searchElement, fromIndex) {

            if (this == null) {
                throw new TypeError('"this" is null or not defined');
            }

            // 1. Let O be ? ToObject(this value).
            var o = Object(this);

            // 2. Let len be ? ToLength(? Get(O, "length")).
            var len = o.length >>> 0;

            // 3. If len is 0, return false.
            if (len === 0) {
                return false;
            }

            // 4. Let n be ? ToInteger(fromIndex).
            //    (If fromIndex is undefined, this step produces the value 0.)
            var n = fromIndex | 0;

            // 5. If n ≥ 0, then
            //  a. Let k be n.
            // 6. Else n < 0,
            //  a. Let k be len + n.
            //  b. If k < 0, let k be 0.
            var k = Math.max(n >= 0 ? n : len - Math.abs(n), 0);

            function sameValueZero(x, y) {
                return x === y || (typeof x === 'number' && typeof y === 'number' && isNaN(x) && isNaN(y));
            }

            // 7. Repeat, while k < len
            while (k < len) {
                // a. Let elementK be the result of ? Get(O, ! ToString(k)).
                // b. If SameValueZero(searchElement, elementK) is true, return true.
                if (sameValueZero(o[k], searchElement)) {
                    return true;
                }
                // c. Increase k by 1.
                k++;
            }

            // 8. Return false
            return false;
        }
    });
}

if (!String.prototype.startsWith) {
    Object.defineProperty(String.prototype, 'startsWith', {
        enumerable: false,
        configurable: false,
        writable: false,
        value: function (searchString, position) {
            position = position || 0;
            return this.indexOf(searchString, position) === position;
        }
    });
}

if (!String.prototype.trim) {
    (function () {
        String.prototype.trim = function () {
            return this.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
        };
    })();
}

! function ($, options) {

    if (options.debug) {
        console.log('PYS:', options);
    }

    var Utils = function (options) {
        var gtag_loaded = false;
		
        function loadPixels() {

            if (!options.gdpr.all_disabled_by_api) {
                
                if (!options.gdpr.analytics_disabled_by_api) {
                    Analytics.loadPixel();
                }

            }

        }

        /**
         * WATCHVIDEO UTILS
         */

        function isJSApiAttrEnabled(url) {
            return url.indexOf('enablejsapi') > -1;
        }

        function isOriginAttrEnabled(url) {
            return url.indexOf('origin') > -1;
        }

        /**
         * COOKIES UTILS
         */

        var utmTerms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

        var requestParams = [];

        function validateEmail(email) {
            var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email);
        }
        function getDomain(url) {

            url = url.replace(/(https?:\/\/)?(www.)?/i, '');

            if (url.indexOf('/') !== -1) {
                return url.split('/')[0];
            }

            return url;
        }

        function getTrafficSource() {

            try {

                var referrer = document.referrer.toString(),
                    source;

                var direct = referrer.length === 0;
                var internal = direct ? false : referrer.indexOf(options.siteUrl) === 0;
                var external = !direct && !internal;
                var cookie = typeof Cookies.get('pysTrafficSource') === 'undefined' ? false : Cookies.get('pysTrafficSource');

                if (external === false) {
                    source = cookie ? cookie : 'direct';
                } else {
                    source = cookie && cookie === referrer ? cookie : referrer;
                }

                if (source !== 'direct') {
                    // leave only domain (Issue #70)
                    return getDomain(source);
                } else {
                    return source;
                }

            } catch (e) {
                console.error(e);
                return 'direct';
            }

        }

        /**
         * Return query variables object with where property name is query variable
         * and property value is query variable value.
         */
        function getQueryVars() {

            try {

                var result = {},
                    tmp = [];

                window.location.search
                    .substr(1)
                    .split("&")
                    .forEach(function (item) {

                        tmp = item.split('=');

                        if (tmp.length > 1) {
                            result[tmp[0]] = tmp[1];
                        }

                    });

                return result;

            } catch (e) {
                console.error(e);
                return {};
            }

        }

        function getLandingPage() {
            if(Cookies.get('pys_landing_page') === 'undefined') {
                return "";
            } else {
                return Cookies.get('pys_landing_page');
            }
        }

        /**
         * Return UTM terms from request query variables or from cookies.
         */
        function getUTMs() {

            try {

                var terms = [];
                var queryVars = getQueryVars();

                $.each(utmTerms, function (index, name) {

                    var value;

                    if (Cookies.get('pys_' + name)) {
                        value = Cookies.get('pys_' + name);
                        // do not allow email in request params (Issue #70)
                        terms[name] = filterEmails(value);
                    } else if (queryVars.hasOwnProperty(name)) {
                        value = queryVars[name];
                        // do not allow email in request params (Issue #70)
                        terms[name] = filterEmails(value);
                    }

                });

                return terms;

            } catch (e) {
                console.error(e);
                return [];
            }

        }

        function getDateTime() {
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
         * PUBLIC API
         */
        return {

            PRODUCT_SIMPLE : 0,
            PRODUCT_VARIABLE : 1,
            PRODUCT_BUNDLE : 2,
            PRODUCT_GROUPED : 3,

             fireEventForAllPixel:function(functionName,events){
                if (events.hasOwnProperty(Facebook.tag()))
                    Facebook[functionName](events[Facebook.tag()]);
                if (events.hasOwnProperty(Analytics.tag()))
                    Analytics[functionName](events[Analytics.tag()]);
                if (events.hasOwnProperty(GAds.tag()))
                    GAds[functionName](events[GAds.tag()]);
                if (events.hasOwnProperty(Pinterest.tag()))
                    Pinterest[functionName](events[Pinterest.tag()]);
                if (events.hasOwnProperty(Bing.tag()))
                    Bing[functionName](events[Bing.tag()]);
                 if (events.hasOwnProperty(TikTok.tag()))
                     TikTok[functionName](events[TikTok.tag()]);
            },

            getQueryValue:function (name){
                return getQueryVars()[name];
            },

            filterEmails: function (value) {
                return filterEmails(value);
            },

            setupPinterestObject: function () {
                Pinterest = window.pys.Pinterest || Pinterest;
                return Pinterest;
            },

            setupBingObject: function () {
                Bing = window.pys.Bing || Bing;
                return Bing;
            },

            // Clone all object members to another and return it
            copyProperties: function (from, to) {
                for (var key in from) {
                    if("function" == typeof from[key]) {
                        continue;
                    }
                    to[key] = from[key];
                }
                return to;
            },

            clone: function(obj) {
                var copy;

                // Handle the 3 simple types, and null or undefined
                if (null == obj || "object" != typeof obj) return obj;

                // Handle Date
                if (obj instanceof Date) {
                    copy = new Date();
                    copy.setTime(obj.getTime());
                    return copy;
                }

                // Handle Array
                if (obj instanceof Array) {
                    copy = [];
                    for (var i = 0, len = obj.length; i < len; i++) {
                        if("function" == typeof obj[i]) {
                            continue;
                        }
                        copy[i] = Utils.clone(obj[i]);
                    }
                    return copy;
                }

                // Handle Object
                if (obj instanceof Object) {
                    copy = {};
                    for (var attr in obj) {
                        if (obj.hasOwnProperty(attr)) {
                            if("function" == typeof obj[attr]) {
                                continue;
                            }
                            copy[attr] = Utils.clone(obj[attr]);
                        }
                    }
                    return copy;
                }

                return obj;
            },

            // Returns array of elements with given tag name
            getTagsAsArray: function (tag) {
                return [].slice.call(document.getElementsByTagName(tag));
            },

            /**
             * Load and initialize YouTube API
             *
             * @link: https://developers.google.com/youtube/iframe_api_reference
             */
            initYouTubeAPI: function () {
                if(!options.signal_watch_video_enabled) return;

                // maybe load YouTube JS API
                if (typeof window.YT === 'undefined') {
                    var tag = document.createElement('script');
                    tag.src = '//www.youtube.com/iframe_api';
                    var firstScriptTag = document.getElementsByTagName('script')[0];
                    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                }

                // initialize when API is ready
                window.onYouTubeIframeAPIReady = function () {

                    // collect all possible YouTube tags
                    var potentialVideos = Utils.getTagsAsArray('iframe').concat(Utils.getTagsAsArray('embed'));

                    // turn videos into trackable videos with events
                    for (var i = 0; i < potentialVideos.length; i++) {
                        var video = potentialVideos[i];
                        if (tagIsYouTubeVideo(video)) {
                            var iframe = normalizeYouTubeIframe(video);
                            addYouTubeEvents(iframe);
                        } else {
                            if(tagIsYouTubeAsyncVideo(video)) {
                                video.addEventListener("load", function(evt) {
                                    var iframe = normalizeYouTubeIframe(evt.currentTarget);
                                    addYouTubeEvents(iframe);
                                });
                            }
                        }
                    }



                    var targets = document.querySelectorAll('.elementor-widget-video .elementor-wrapper');

                    const config = {
                        attributes: false,
                        childList: true,
                        subtree: true
                    };

                    const callback = function(mutationsList, observer) {
                        for (let mutation of mutationsList) {
                            if (mutation.type === 'childList') {
                                for(var m = 0;m<mutation.addedNodes.length;m++) {
                                    addDynYouTubeVideos(mutation.addedNodes[m]);
                                }
                            }
                        }
                    };
                    // observe elementator widget-video and add event when it add iframe
                    for(var i=0;i<targets.length;i++) {
                        const observer = new MutationObserver(callback);
                        observer.observe(targets[i], config);//maybe remove before add
                    }


                };

            },

            /**
             * Load and initialize Vimeo API
             *
             * @link: https://github.com/vimeo/player.js
             */
            initVimeoAPI: function () {
                if(!options.signal_watch_video_enabled) return;
              
                $(document).ready(function () {

                    var potentialVideos = Utils.getTagsAsArray('iframe').concat(Utils.getTagsAsArray('embed'));

                    for (var i = 0; i < potentialVideos.length; i++) {
                        var tag = potentialVideos[i];
                        if (tagIsVimeoVideo(tag)) {
                            attachVimeoPlayerToTag(tag);
                        } else {
                            if (tagIsAsincVimeoVideo(tag)) {
                                tag.addEventListener("load", function(evt) {
                                    attachVimeoPlayerToTag(evt.currentTarget);
                                });
                            }
                        }
                    }

                });

            },

            manageCookies: function () {

                try {

                    var expires = parseInt(options.cookie_duration); //  days

                    var source = getTrafficSource();

                    // manage traffic source cookie
                    if (source !== 'direct') {
                        Cookies.set('pysTrafficSource', source, { expires: expires });
                    } else {
                        Cookies.remove('pysTrafficSource');
                    }

                    var queryVars = getQueryVars();

                    // manage utm cookies
                    $.each(utmTerms, function (index, name) {

                        if (Cookies.get('pys_' + name) === undefined && queryVars.hasOwnProperty(name)) {
                            Cookies.set('pys_' + name, queryVars[name], { expires: expires });
                        }

                    });

                    // manage landing cookies
                    if(Cookies.get('pys_landing_page') === undefined){
                        var landing = window.location.href.split('?')[0];
                        Cookies.set('pys_landing_page',landing,{ expires: expires });
                    }

                } catch (e) {
                    console.error(e);
                }

            },

            initializeRequestParams: function () {

                if (options.trackTrafficSource) {
                    requestParams.traffic_source = getTrafficSource();
                }

                if (options.trackUTMs) {

                    var utms = getUTMs();

                    $.each(utmTerms, function (index, term) {
                        if (term in utms) {
                            requestParams[term] = utms[term];
                        }
                    });

                }


                var dateTime = getDateTime();
                if(options.enable_event_time_param) {
                    requestParams.event_time = dateTime[0];
                }

                if(options.enable_event_day_param) {
                    requestParams.event_day = dateTime[1];
                }
                if(options.enable_event_month_param) {
                    requestParams.event_month = dateTime[2];
                }

                if(options.enable_lading_page_param){
                    requestParams.landing_page = getLandingPage();
                } else {
                    Cookies.remove('pys_landing_page');
                }
            },

            getRequestParams: function () {
                return requestParams;
            },

            /**
             * DOWNLOAD DOCS
             */

            getLinkExtension: function (link) {

                // Remove anchor, query string and everything before last slash
                link = link.substring(0, (link.indexOf("#") === -1) ? link.length : link.indexOf("#"));
                link = link.substring(0, (link.indexOf("?") === -1) ? link.length : link.indexOf("?"));
                link = link.substring(link.lastIndexOf("/") + 1, link.length);

                // If there's a period left in the URL, then there's a extension
                if (link.length > 0 && link.indexOf('.') !== -1) {
                    link = link.substring(link.indexOf(".") + 1); // Remove everything but what's after the first period
                    return link;
                } else {
                    return "";
                }
            },

            getLinkFilename: function (link) {

                // Remove anchor, query string and everything before last slash
                link = link.substring(0, (link.indexOf("#") === -1) ? link.length : link.indexOf("#"));
                link = link.substring(0, (link.indexOf("?") === -1) ? link.length : link.indexOf("?"));
                link = link.substring(link.lastIndexOf("/") + 1, link.length);

                // If there's a period left in the URL, then there's a extension
                if (link.length > 0 && link.indexOf('.') !== -1) {
                    return link;
                } else {
                    return "";
                }
            },

            /**
             * CUSTOM EVENTS
             */

            setupMouseOverClickEvents: function (eventId, triggers) {

                // Non-default binding used to avoid situations when some code in external js
                // stopping events propagation, eg. returns false, and our handler will never called
                $(document).onFirst('mouseover', triggers.join(','), function () {

                    // do not fire event multiple times
                    if ($(this).hasClass('pys-mouse-over-' + eventId)) {
                        return true;
                    } else {
                        $(this).addClass('pys-mouse-over-' + eventId);
                    }

                    Utils.fireTriggerEvent(eventId);

                });

            },

            setupCSSClickEvents: function (eventId, triggers) {

                // Non-default binding used to avoid situations when some code in external js
                // stopping events propagation, eg. returns false, and our handler will never called
                // add event to document to support dyn class
                $(document).onFirst('click', triggers.join(','), function () {
                    Utils.fireTriggerEvent(eventId);
                });

            },

            setupURLClickEvents: function () {

                if( !options.triggerEventTypes.hasOwnProperty('url_click') ) {
                    return;
                }
                // Non-default binding used to avoid situations when some code in external js
                // stopping events propagation, eg. returns false, and our handler will never called
                $('a').onFirst('click', function (evt) {

                    var url  = $(this).attr('href');
                    $.each(options.triggerEventTypes.url_click, function (eventId, triggers) {
                        if(Utils.compareUrl(url,triggers.value,triggers.rule)) {
                            Utils.fireTriggerEvent(eventId);
                        }
                    });
                });

            },

            removeUrlDomain(url) {
                if(url.indexOf("/#") > -1) {
                    url = url.substring(0, url.indexOf("/#"));
                }
                return url.replace('http://','')
                    .replace('https://','')
                    .replace('www.','')
                    .trim()
                    .replace(/^\/+/g, '')

            },

            compareUrl: function(base,url,rule){

                if(url == "*" || url == '') return true;

                base = Utils.removeUrlDomain(base)
                url = Utils.removeUrlDomain(url)

                if(rule == 'match') {
                    return url == base;
                } else {
                    return base.indexOf(url) > -1
                }

            },

            setupScrollPosEvents: function (eventId, triggers) {

                var scrollPosThresholds = {},
                    docHeight = $(document).height() - $(window).height();

                // convert % to absolute positions
                $.each(triggers, function (index, scrollPos) {

                    // convert % to pixels
                    scrollPos = docHeight * scrollPos / 100;
                    scrollPos = Math.round(scrollPos);

                    scrollPosThresholds[scrollPos] = eventId;

                });

                $(document).on("scroll",function () {

                    var scrollPos = $(window).scrollTop();

                    $.each(scrollPosThresholds, function (threshold, eventId) {

                        // position has not reached yes
                        if (scrollPos <= threshold) {
                            return true;
                        }

                        // fire event only once
                        if (eventId === null) {
                            return true;
                        } else {
                            scrollPosThresholds[threshold] = null;
                        }

                        Utils.fireTriggerEvent(eventId);

                    });

                });

            },
            setupCommentEvents : function (eventId,triggers) {
                $('form.comment-form').on("submit",function () {
                    Utils.fireTriggerEvent(eventId);
                });
            },
            /**
             * Events
             */

            isEventInTimeWindow: function (eventName, event, prefix) {

                if(event.hasOwnProperty("hasTimeWindow") && event.hasTimeWindow) {
                    var cookieName = prefix+"_"+eventName;
                    var now = new Date().getTime();

                    if(Cookies.get(cookieName) !== undefined) {

                        var lastTimeFire = Cookies.get(cookieName);
                        var fireTime = event.timeWindow * 60*60*1000;

                        if( now - lastTimeFire > fireTime) {
                            Cookies.set(cookieName,now, { expires: event.timeWindow / 24.0} );
                        } else {
                            return false;
                        }
                    } else {
                        Cookies.set(cookieName,now, { expires: event.timeWindow / 24.0} );
                    }
                }
                return true
            },

            fireTriggerEvent: function (eventId) {

                if (!options.triggerEvents.hasOwnProperty(eventId)) {
                    return;
                }

                var event = {};
                var events = options.triggerEvents[eventId];

                if (events.hasOwnProperty('facebook')) {
                    event = events.facebook;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_facebook_"+eventId)) {
                        Facebook.fireEvent(event.name, event);
                    }
                }

                if (events.hasOwnProperty('ga')) {
                    event = events.ga;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_ga_"+eventId)) {
                        Analytics.fireEvent(event.name, event);
                    }
                }

                if (events.hasOwnProperty('google_ads')) {
                    event = events.google_ads;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_google_ads_"+eventId)) {
                        GAds.fireEvent(event.name, event);
                    }
                }

                if (events.hasOwnProperty('pinterest')) {
                    event = events.pinterest;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_pinterest_"+eventId)) {
                        Pinterest.fireEvent(event.name, event);;
                    }
                }

                if (events.hasOwnProperty('bing')) {
                    event = events.bing;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_bing_"+eventId)) {
                        Bing.fireEvent(event.name, event);;
                    }
                }
                if (events.hasOwnProperty('tiktok')) {
                    event = events.tiktok;
                    if(Utils.isEventInTimeWindow(event.name,event,"dyn_bing_"+eventId)) {
                        TikTok.fireEvent(event.name, event);
                    }
                }


            },

            isFirstPurchaseFire: function ($eventName,orderId,pixel) {

                if(Cookies.get("pys_"+$eventName+"_order_id_"+pixel) == orderId) {
                    return false;
                } else {
                    Cookies.set("pys_"+$eventName+"_order_id_"+pixel, orderId, { expires: 1 });
                }
                return true;
            },

            fireStaticEvents: function (pixel) {

                if (options.staticEvents.hasOwnProperty(pixel)) {

                    $.each(options.staticEvents[pixel], function (eventId, events) {

                        //skip purchase event if this order was fired
                        if( options.woo.hasOwnProperty('woo_purchase_on_transaction') &&
                            options.woo.woo_purchase_on_transaction &&
                            (eventId === "woo_purchase" || eventId === "woo_purchase_category") ) {
                            if(!Utils.isFirstPurchaseFire(eventId,events[0].woo_order,pixel)) {
                                return;
                            }
                        }

                        if( options.edd.hasOwnProperty('edd_purchase_on_transaction') &&
                            options.edd.edd_purchase_on_transaction &&
                            (eventId === "edd_purchase" || eventId === "edd_purchase_category") ) {
                            if(!Utils.isFirstPurchaseFire(eventId,events[0].edd_order,pixel)) {
                                return;
                            }
                        }


                        $.each(events, function (index, event) {

                            event.fired = event.fired || false;

                            if (!event.fired && Utils.isEventInTimeWindow(event.name,event,'static_' + pixel+"_")) {


                                var fired = false;

                                // fire event
                                getPixelBySlag(pixel).fireEvent(event.name, event);

                                // prevent event double event firing
                                event.fired = fired;
                            }

                        });
                    });

                }
            },

            /**
             * Load tag's JS
             *
             * @link: https://developers.google.com/analytics/devguides/collection/gtagjs/
             * @link: https://developers.google.com/analytics/devguides/collection/gtagjs/custom-dims-mets
             */
            loadGoogleTag: function (id) {

                if (!gtag_loaded) {

                    (function (window, document, src) {
                        var a = document.createElement('script'),
                            m = document.getElementsByTagName('script')[0];
                        a.async = 1;
                        a.src = src;
                        m.parentNode.insertBefore(a, m);
                    })(window, document, '//www.googletagmanager.com/gtag/js?id=' + id);

                    window.dataLayer = window.dataLayer || [];
                    window.gtag = window.gtag || function gtag() {
                        dataLayer.push(arguments);
                    };

                    gtag('js', new Date());

                    gtag_loaded = true;

                }

            },

            /**
             * GDPR
             */

            loadPixels: function () {

                if (options.gdpr.ajax_enabled && !options.gdpr.consent_magic_integration_enabled) {

                    // retrieves actual PYS GDPR filters values which allow to avoid cache issues
                    $.get({
                        url: options.ajaxUrl,
                        dataType: 'json',
                        data: {
                            action: 'pys_get_gdpr_filters_values'
                        },
                        success: function (res) {

                            if (res.success) {

                                options.gdpr.all_disabled_by_api = res.data.all_disabled_by_api;
                                options.gdpr.facebook_disabled_by_api = res.data.facebook_disabled_by_api;
                                options.gdpr.tiktok_disabled_by_api = res.data.tiktok_disabled_by_api;
                                options.gdpr.analytics_disabled_by_api = res.data.analytics_disabled_by_api;
                                options.gdpr.google_ads_disabled_by_api = res.data.google_ads_disabled_by_api;
                                options.gdpr.pinterest_disabled_by_api = res.data.pinterest_disabled_by_api;
                                options.gdpr.bing_disabled_by_api = res.data.bing_disabled_by_api;

                            }

                            loadPixels();

                        }
                    });

                } else {
                    loadPixels();
                }

            },

            consentGiven: function (pixel) {

                /**
                 * Cookiebot
                 */
                if (options.gdpr.cookiebot_integration_enabled && typeof Cookiebot !== 'undefined') {

                    var cookiebot_consent_category = options.gdpr['cookiebot_' + pixel + '_consent_category'];

                    if (options.gdpr[pixel + '_prior_consent_enabled']) {
                        if (Cookiebot.consented === false || Cookiebot.consent[cookiebot_consent_category]) {
                            return true;
                        }
                    } else {
                        if (Cookiebot.consent[cookiebot_consent_category]) {
                            return true;
                        }
                    }

                    return false;

                }

                /**
                 * Cookie Notice
                 */
                if (options.gdpr.cookie_notice_integration_enabled && typeof cnArgs !== 'undefined') {

                    var cn_cookie = Cookies.get(cnArgs.cookieName);

                    if (options.gdpr[pixel + '_prior_consent_enabled']) {
                        if (typeof cn_cookie === 'undefined' || cn_cookie === 'true') {
                            return true;
                        }
                    } else {
                        if (cn_cookie === 'true') {
                            return true;
                        }
                    }

                    return false;

                }

                /**
                 * Cookie Law Info
                 */
                if (options.gdpr.cookie_law_info_integration_enabled) {

                    var cli_cookie = Cookies.get('viewed_cookie_policy');

                    if (options.gdpr[pixel + '_prior_consent_enabled']) {
                        if (typeof cli_cookie === 'undefined' || cli_cookie === 'yes') {
                            return true;
                        }
                    } else {
                        if (cli_cookie === 'yes') {
                            return true;
                        }
                    }

                    return false;

                }

                /**
                 * ConsentMagic
                 */
                if (options.gdpr.consent_magic_integration_enabled && typeof CS_Data !== "undefined") {

                    var cs_cookie = Cookies.get('cs_viewed_cookie_policy'+test_prefix);

                    if (options.gdpr[pixel + '_prior_consent_enabled']) {
                        if (typeof cs_cookie === 'undefined' || cs_cookie === 'yes') {
                            return true;
                        }
                    } else {
                        if (typeof cs_cookie === 'undefined' || cs_cookie === 'yes') {
                            return true;
                        }
                    }

                    return false;

                }


                /**
                 * Real Cookie Banner
                 */
                if (options.gdpr.real_cookie_banner_integration_enabled) {
                    var consentApi = window.consentApi;
                    if (consentApi) {
                        switch (pixel) {
                            case "analytics":
                                return consentApi.consentSync("http", "_ga", "*").cookieOptIn;
                            case "facebook":
                                return consentApi.consentSync("http", "_fbp", "*").cookieOptIn;
                            case "pinterest":
                                return consentApi.consentSync("http", "_pinterest_sess", ".pinterest.com").cookieOptIn;
                            case "bing":
                                return consentApi.consentSync("http", "_uetsid", "*").cookieOptIn;
                            case "google_ads":
                                return consentApi.consentSync("http", "1P_JAR", ".google.com").cookieOptIn;
                            case 'tiktok':
                                return consentApi.consentSync("http", "tt_webid_v2", ".tiktok.com").cookieOptIn;
                            default:
                                return true;
                        }
                    }
                }

                return true;

            },

            setupGdprCallbacks: function () {

                /**
                 * Cookiebot
                 */
                if (options.gdpr.cookiebot_integration_enabled && typeof Cookiebot !== 'undefined') {

                    window.addEventListener("CookiebotOnConsentReady", function() {
                        if (Cookiebot.consent.marketing) {
                            Facebook.loadPixel();
                            Bing.loadPixel();
                            Pinterest.loadPixel();
                            GAds.loadPixel();
                            TikTok.loadPixel();
                        }
                        if (Cookiebot.consent.statistics) {
                            Analytics.loadPixel();
                        }
                        if (!Cookiebot.consent.marketing) {
                            Facebook.disable();
                            Pinterest.disable();
                            Bing.disable()
                            GAds.disable();
                            TikTok.disable();
                        }
                        if (!Cookiebot.consent.statistics) {
                            Analytics.disable();
                        }
                    });
                }

                /**
                 * Cookie Notice
                 */
                if (options.gdpr.cookie_notice_integration_enabled) {

                    $(document).onFirst('click', '.cn-set-cookie', function () {

                        if ($(this).data('cookie-set') === 'accept') {
                            Facebook.loadPixel();
                            Analytics.loadPixel();
                            GAds.loadPixel();
                            Pinterest.loadPixel();
                            Bing.loadPixel();
                            TikTok.loadPixel();
                        } else {
                            Facebook.disable();
                            Analytics.disable();
                            GAds.disable();
                            Pinterest.disable();
                            Bing.disable();
                            TikTok.disable();
                        }

                    });

                    $(document).onFirst('click', '.cn-revoke-cookie', function () {
                        Facebook.disable();
                        Analytics.disable();
                        GAds.disable();
                        Pinterest.disable();
                        Bing.disable();
                        TikTok.disable();
                    });

                }

                /**
                 * Cookie Law Info
                 */
                if (options.gdpr.cookie_law_info_integration_enabled) {

                    $(document).onFirst('click', '#cookie_action_close_header', function () {
                        Facebook.loadPixel();
                        Analytics.loadPixel();
                        GAds.loadPixel();
                        Pinterest.loadPixel();
                        Bing.loadPixel();
                        TikTok.loadPixel();
                    });

                    $(document).onFirst('click', '#cookie_action_close_header_reject', function () {
                        Facebook.disable();
                        Analytics.disable();
                        GAds.disable();
                        Pinterest.disable();
                        Bing.disable();
                        TikTok.disable();
                    });

                }

                /**
                 * ConsentMagic
                 */
                if (options.gdpr.consent_magic_integration_enabled && typeof CS_Data !== "undefined") {
                    var test_prefix = CS_Data.test_prefix,
                        cs_refresh_after_consent = false,
                        substring = "cs_enabled_cookie_term";

                    if (CS_Data.cs_refresh_after_consent == 1) {
                        cs_refresh_after_consent = CS_Data.cs_refresh_after_consent;
                    }

                    if (!cs_refresh_after_consent) {
                        var theCookies = document.cookie.split(';');
                        for (var i = 1 ; i <= theCookies.length; i++) {
                            if (theCookies[i-1].indexOf(substring) !== -1) {
                                var categoryCookie = theCookies[i-1].replace('cs_enabled_cookie_term'+test_prefix+'_','');
                                categoryCookie = Number(categoryCookie.replace(/\D+/g,""));
                                var cs_cookie_val = Cookies.get('cs_enabled_cookie_term'+test_prefix+'_'+categoryCookie);
                                if(cs_cookie_val == 'yes') {
                                    if (categoryCookie === CS_Data.cs_script_cat.facebook) {
                                        Facebook.loadPixel();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.bing) {
                                        Bing.loadPixel();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.analytics) {
                                        Analytics.loadPixel();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.gads) {
                                        GAds.loadPixel();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.pinterest) {
                                        Pinterest.loadPixel();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.tiktok) {
                                        TikTok.loadPixel();
                                    }
                                } else {
                                    if (categoryCookie === CS_Data.cs_script_cat.facebook) {
                                        Facebook.disable();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.bing) {
                                        Bing.disable();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.analytics) {
                                        Analytics.disable();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.gads) {
                                        GAds.disable();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.pinterest) {
                                        Pinterest.disable();
                                    }

                                    if (categoryCookie === CS_Data.cs_script_cat.tiktok) {
                                        TikTok.disable();
                                    }
                                }
                                if (Cookies.get('cs_enabled_advanced_matching') == 'yes') {
                                    Facebook.loadPixel();
                                }
                            }
                        }

                        $(document).on('click','.cs_action_btn',function(e) {
                            e.preventDefault();
                            var elm = $(this),
                                button_action = elm.attr('data-cs_action');

                            if(button_action === 'allow_all') {
                                Facebook.loadPixel();
                                Bing.loadPixel();
                                Analytics.loadPixel();
                                GAds.loadPixel();
                                Pinterest.loadPixel();
                                TikTok.loadPixel();
                            } else if(button_action === 'disable_all') {
                                Facebook.disable();
                                Bing.disable();
                                Analytics.disable();
                                GAds.disable();
                                Pinterest.disable();
                                TikTok.disable();
                            }
                        });
                    }
                }

                /**
                 * Real Cookie Banner
                 */
                if (options.gdpr.real_cookie_banner_integration_enabled) {
                    var consentApi = window.consentApi;
                    if (consentApi) {
                        consentApi.consent("http", "_ga", "*")
                            .then(Analytics.loadPixel.bind(Analytics), Analytics.disable.bind(Analytics));

                        consentApi.consent("http", "_fbp", "*")
                            .then(Facebook.loadPixel.bind(Facebook), Facebook.disable.bind(Facebook));

                        consentApi.consent("http", "_pinterest_sess", ".pinterest.com")
                            .then(Pinterest.loadPixel.bind(Pinterest), Pinterest.disable.bind(Pinterest));

                        consentApi.consent("http", "_uetsid", "*")
                            .then(Bing.loadPixel.bind(Bing), Bing.disable.bind(Bing));

                        consentApi.consent("http", "1P_JAR", ".google.com")
                            .then(GAds.loadPixel.bind(GAds), GAds.disable.bind(GAds));
                        consentApi.consent("http", "tt_webid_v2", ".tiktok.com")
                            .then(TikTok.loadPixel.bind(GAds), TikTok.disable.bind(GAds));
                    }
                }

            },

            /**
             * Enrich
             */
            isCheckoutPage: function () {
                return $('body').hasClass('woocommerce-checkout') ||
                    $('body').hasClass('edd-checkout');
            },
            addCheckoutFields : function() {

                var utm = "";
                var utms = getUTMs()
                $.each(utmTerms, function (index, name) {
                    if(index > 0) {
                        utm+="|";
                    }
                    utm+=name+":"+utms[name];
                });
                var dateTime = getDateTime();
                var landing = Cookies.get('pys_landing_page');

                var $form = null;
                if($('body').hasClass('woocommerce-checkout')) {
                    $form = $("form.woocommerce-checkout");
                } else {
                    $form = $("#edd_purchase_form");
                }
                var inputs = {'pys_utm':utm,
                    'pys_browser_time':dateTime.join("|"),
                    'pys_landing':landing,
                    'pys_source':getTrafficSource(),
                    'pys_order_type': $(".wcf-optin-form").length > 0 ? "wcf-optin" : "normal"
                }

                Object.keys(inputs).forEach(function(key,index) {
                    $form.append("<input type='hidden' name='"+key+"' value='"+inputs[key]+"' /> ");
                });


            }
        };

    }(options);

    var Analytics = function (options) {

        var initialized = false;

        /**
         * Fires event
         *
         * @link: https://developers.google.com/analytics/devguides/collection/gtagjs/sending-data
         * @link: https://developers.google.com/analytics/devguides/collection/gtagjs/events
         * @link: https://developers.google.com/gtagjs/reference/event
         * @link: https://developers.google.com/gtagjs/reference/parameter
         *
         * @link: https://developers.google.com/analytics/devguides/collection/gtagjs/custom-dims-mets
         *
         * @param name
         * @param data
         */
        function fireEvent(name, data) {
            if(typeof window.pys_event_data_filter === "function" && window.pys_disable_event_filter(name,'ga')) {
                return;
            }

            var eventParams = Utils.copyProperties(data.params, {});
            Utils.copyProperties(Utils.getRequestParams(), eventParams);

            var _fireEvent = function (tracking_id,name,params) {

                params['send_to'] = tracking_id;
                if (options.debug) {
                    console.log('[Google Analytics #' + tracking_id + '] ' + name, params);
                }

                gtag('event', name, params);

            };

            data.trackingIds.forEach(function (tracking_id) {
                var copyParams = Utils.copyProperties(eventParams, {}); // copy params because mapParamsTov4 can modify it
                var params = mapParamsTov4(tracking_id,name,copyParams)
                _fireEvent(tracking_id, name, params);
            });

        }

        function normalizeEventName(eventName) {

            var matches = {
                ViewContent: 'view_item',
                AddToCart: 'add_to_cart',
                AddToWishList: 'add_to_wishlist',
                InitiateCheckout: 'begin_checkout',
                Purchase: 'purchase',
                Lead: 'generate_lead',
                CompleteRegistration: 'sign_up',
                AddPaymentInfo: 'set_checkout_option'
            };

            return matches.hasOwnProperty(eventName) ? matches[eventName] : eventName;

        }

        function mapParamsTov4(tag,name,param) {
            if(isv4(tag)) {
                delete param.traffic_source;
                delete param.event_category;
                delete param.event_label;
                delete param.ecomm_prodid;
                delete param.ecomm_pagetype;
                delete param.ecomm_totalvalue;
                if(name === 'search') {
                    param['search'] = param.search_term;
                    delete param.search_term;
                    delete param.non_interaction;
                    delete param.dynx_itemid;
                    delete param.dynx_pagetype;
                    delete param.dynx_totalvalue;
                }
            } else {
                //delete standard params
                delete param.page_title;
                delete param.post_type;
                delete param.post_id;
                delete param.plugin;
                delete param.page_title;
                delete param.event_url;
                delete param.user_role;
                delete param.cartlows;
                delete param.cartflows_flow;
                delete param.cartflows_step;

                if(name === 'Signal') {
                    switch (param.event_action) {
                        case 'External Click':
                        case 'Internal Click':
                        case 'Tel':
                        case 'Email': {
                            let params = {
                                event_category: name,
                                event_action: param.event_action,
                                non_interaction: param.non_interaction,
                            }
                            if(options.trackTrafficSource) {
                                params['traffic_source'] = param.traffic_source
                            }
                            return params;
                        }break;
                        case 'Video': {
                            let params = {
                                event_category: name,
                                event_action: param.event_action,
                                event_label: param.video_title,
                                non_interaction: param.non_interaction,
                            }
                            if(options.trackTrafficSource) {
                                params['traffic_source'] = param.traffic_source
                            }
                            return params;
                        }break;
                        case 'Comment': {
                            let params = {
                                event_category: name,
                                event_action: param.event_action,
                                event_label: document.location.href,
                                non_interaction: param.non_interaction,
                            }
                            if(options.trackTrafficSource) {
                                params['traffic_source'] = param.traffic_source
                            }
                            return params;
                        }break;
                        case 'Form': {
                            var params = {
                                event_category: name,
                                event_action: param.event_action,
                                non_interaction: param.non_interaction,
                            };
                            if(options.trackTrafficSource) {
                                params['traffic_source'] = param.traffic_source
                            }
                            var formClass = (typeof param.form_class != 'undefined') ? 'class: ' + param.form_class : '';
                            if(formClass != "") {
                                params["event_label"] = formClass;
                            }
                            return params;
                        }break;
                        case 'Download': {
                            return {
                                event_category: name,
                                event_action: param.event_action,
                                event_label: param.download_name,
                                non_interaction: param.non_interaction,
                            }
                        }break;
                    }
                    if(param.event_action.indexOf('Scroll') === 0){
                        var scroll_percent = param.event_action.substring(
                            param.event_action.indexOf(' ')+1,
                            param.event_action.indexOf('%')
                        );
                        let params =  {
                            event_category: name,
                            event_action: param.event_action,
                            event_label: scroll_percent,
                            non_interaction: param.non_interaction,
                        }
                        if(options.trackTrafficSource) {
                            params['traffic_source'] = param.traffic_source
                        }
                        return params;
                    }
                    if(param.event_action.indexOf('Time on page') === 0) {
                        let time_on_page = param.event_action.substring(
                            14,
                            param.event_action.indexOf(' seconds')
                        );
                        let params = {
                            event_category: name,
                            event_action: param.event_action,
                            event_label: time_on_page,
                            non_interaction: param.non_interaction,

                        };
                        if(options.trackTrafficSource) {
                            params['traffic_source'] = param.traffic_source
                        }
                        return params
                    }
                }

            }
            return param;
        }

        function isv4(tag) {
            return tag.indexOf('G') === 0;
        }

        /**
         * Public API
         */
        return {
            tag: function() {
                return "ga";
            },
            isEnabled: function () {
                return options.hasOwnProperty('ga');
            },

            disable: function () {
                initialized = false;
            },

            loadPixel: function () {

                Utils.loadGoogleTag(options.ga.trackingIds[0]);

                var cd = {
                    'dimension1': 'event_hour',
                    'dimension2': 'event_day',
                    'dimension3': 'event_month'
                };

                // configure Dynamic Remarketing CDs
                if (options.ga.retargetingLogic === 'ecomm') {
                    cd.dimension4 = 'ecomm_prodid';
                    cd.dimension5 = 'ecomm_pagetype';
                    cd.dimension6 = 'ecomm_totalvalue';
                } else {
                    cd.dimension4 = 'dynx_itemid';
                    cd.dimension5 = 'dynx_pagetype';
                    cd.dimension6 = 'dynx_totalvalue';
                }

                var config = {
                    'link_attribution': options.ga.enhanceLinkAttr,
                    'anonymize_ip': options.ga.anonimizeIP,
                    'custom_map': cd
                };

                if(options.user_id && options.user_id != 0) {
                    config.user_id = options.user_id;
                }

                // Cross-Domain tracking
                if (options.ga.crossDomainEnabled) {
                    config.linker = {
                        accept_incoming: options.ga.crossDomainAcceptIncoming,
                        domains: options.ga.crossDomainDomains
                    };
                }



                // configure tracking ids

                options.ga.trackingIds.forEach(function (trackingId,index) {
                    if(options.ga.isDebugEnabled.includes("index_"+index)) {
                        config.debug_mode = true;
                    } else {
                        config.debug_mode = false;
                    }
                    if(isv4(trackingId)) {
                        if(options.ga.disableAdvertisingFeatures) {
                            config.allow_google_signals = false
                        }
                        if(options.ga.disableAdvertisingPersonalization) {
                            config.allow_ad_personalization_signals = false
                        }
                    }

                    gtag('config', trackingId, config);
                    
                });

                initialized = true;

                Utils.fireStaticEvents('ga');
                $( document).trigger( "analytics_initialized")
            },

            fireEvent: function (name, data) {

                if (!initialized || !this.isEnabled()) {
                    return false;
                }

                data.delay = data.delay || 0;
                data.params = data.params || {};

                if (data.delay === 0) {

                    fireEvent(name, data);

                } else {

                    setTimeout(function (name, params) {
                        fireEvent(name, params);
                    }, data.delay * 1000, name, data);

                }

                return true;

            },

            onAdSenseEvent: function () {
                // not supported
            },

            onClickEvent: function (event) {
                this.fireEvent(event.name, event);
            },

            onWatchVideo: function (event) {
                this.fireEvent(event.name, event);

            },

            onCommentEvent: function (event) {

                this.fireEvent(event.name, event);

            },

            onFormEvent: function (event) {

                this.fireEvent(event.name, event);

            },

            onDownloadEvent: function (event) {

                this.fireEvent(event.name, event);

            },

            onWooAddToCartOnButtonEvent: function (product_id) {
                if(!options.dynamicEvents.woo_add_to_cart_on_button_click.hasOwnProperty(this.tag()))
                    return;

                if (window.pysWooProductData.hasOwnProperty(product_id)) {
                    if (window.pysWooProductData[product_id].hasOwnProperty('ga')) {
                        var event = Utils.clone(options.dynamicEvents.woo_add_to_cart_on_button_click[this.tag()]);
                        Utils.copyProperties(window.pysWooProductData[product_id]['ga'].params, event.params)
                        event.trackingIds = window.pysWooProductData[product_id]['ga']['trackingIds'];
                        this.fireEvent(event.name, event);
                    }
                }

            },

            onWooAddToCartOnSingleEvent: function (product_id, qty, product_type, is_external, $form) {

                window.pysWooProductData = window.pysWooProductData || [];

                if(!options.dynamicEvents.woo_add_to_cart_on_button_click.hasOwnProperty(this.tag()))
                    return;
                var event = Utils.clone(options.dynamicEvents.woo_add_to_cart_on_button_click[this.tag()]);

                if (product_type === Utils.PRODUCT_VARIABLE && !options.ga.wooVariableAsSimple) {
                    product_id = parseInt($form.find('input[name="variation_id"]').val());
                }

                if (window.pysWooProductData.hasOwnProperty(product_id)) {
                    if (window.pysWooProductData[product_id].hasOwnProperty('ga')) {

                        Utils.copyProperties(window.pysWooProductData[product_id]['ga'].params, event.params);


                        if(product_type === Utils.PRODUCT_GROUPED ) {
                            var groupValue = 0;
                            $form.find(".woocommerce-grouped-product-list .qty").each(function(index){
                                var childId = $(this).attr('name').replaceAll("quantity[","").replaceAll("]","");
                                var quantity = parseInt($(this).val());
                                if(isNaN(quantity)) {
                                    quantity = 0;
                                }
                                var childItem = window.pysWooProductData[product_id]['ga'].grouped[childId];
                                event.params.items.forEach(function(el,index,array) {
                                    if(el.id == childItem.content_id) {
                                        if(quantity > 0){
                                            el.quantity = quantity;
                                            el.price = childItem.price;
                                        } else {
                                            array.splice(index, 1);
                                        }
                                    }
                                });
                                groupValue += childItem.price * quantity;
                            });

                            if(options.woo.addToCartOnButtonValueEnabled &&
                                options.woo.addToCartOnButtonValueOption !== 'global' &&
                                event.params.hasOwnProperty('ecomm_totalvalue')) {
                                event.params.ecomm_totalvalue = groupValue;
                            }

                            if(groupValue == 0) return; // skip if no items selected
                        } else {
                            // update items qty param
                            event.params.items[0].quantity = qty;
                        }

                        // maybe customize value option
                        if (options.woo.addToCartOnButtonValueEnabled &&
                            options.woo.addToCartOnButtonValueOption !== 'global' &&
                            product_type !== Utils.PRODUCT_GROUPED)
                        {
                            if(event.params.hasOwnProperty('ecomm_totalvalue')) {
                                event.params.ecomm_totalvalue = event.params.items[0].price * qty;
                            }
                        }



                        var eventName = is_external ? options.woo.affiliateEventName : event.name;
                        eventName = normalizeEventName(eventName);

                        this.fireEvent(eventName, event);

                    }
                }

            },

            onWooCheckoutProgressStep: function (event) {
                this.fireEvent(event.name, event);
            },

            onWooSelectContent: function (event) {
                this.fireEvent(event.name, event);
            },

            onWooRemoveFromCartEvent: function (event) {
                this.fireEvent(event.name, event);
            },

            onWooAffiliateEvent: function (product_id) {
                if(!options.dynamicEvents.woo_affiliate.hasOwnProperty(this.tag()))
                    return;
                var event = options.dynamicEvents.woo_affiliate[this.tag()];

                if (window.pysWooProductData.hasOwnProperty(product_id)) {
                    if (window.pysWooProductData[product_id].hasOwnProperty('ga')) {

                        event = Utils.clone(event );
                        Utils.copyProperties(window.pysWooProductData[product_id][this.tag()], event.params)
                        this.fireEvent(normalizeEventName(options.woo.affiliateEventName), event);

                    }
                }

            },

            onWooPayPalEvent: function (event) {
                this.fireEvent(event.name, event);
            },

            onEddAddToCartOnButtonEvent: function (download_id, price_index, qty) {
                if(!options.dynamicEvents.edd_add_to_cart_on_button_click.hasOwnProperty(this.tag()))
                    return;
                var event = Utils.clone(options.dynamicEvents.edd_add_to_cart_on_button_click[this.tag()]);


                if (window.pysEddProductData.hasOwnProperty(download_id)) {

                    var index;

                    if (price_index) {
                        index = download_id + '_' + price_index;
                    } else {
                        index = download_id;
                    }

                    if (window.pysEddProductData[download_id].hasOwnProperty(index)) {
                        if (window.pysEddProductData[download_id][index].hasOwnProperty('ga')) {

                            Utils.copyProperties(window.pysEddProductData[download_id][index]['ga'].params, event.params);

                            // update items qty param
                            event.params.items[0].quantity = qty;

                            this.fireEvent(event.name,event);

                        }
                    }

                }

            },

            onEddRemoveFromCartEvent: function (event) {
                this.fireEvent(event.name, event);
            },

            onPageScroll: function (event) {
                if (initialized && this.isEnabled()) {
                    this.fireEvent(event.name, event);
                }
            },
            onTime: function (event) {
                if (initialized && this.isEnabled()) {
                    this.fireEvent(event.name, event);
                }
            },
        };

    }(options);

    window.pys = window.pys || {};
    window.pys.Analytics = Analytics;
    window.pys.Utils = Utils;

    $(document).ready(function () {

        Utils.initializeRequestParams();


        // setup WooCommerce events
        if (options.woo.enabled) {
            
            // WooCommerce checkout progress
            $(document).onFirst('submit click', '#place_order', function () {
                Analytics.onWooCheckoutProgressStep(options.dynamicEvents.woo_initiate_checkout_progress_o[Analytics.tag()]);
            });

            // WooCommerce
            if(options.dynamicEvents.hasOwnProperty("woo_select_content_search") ||
                options.dynamicEvents.hasOwnProperty("woo_select_content_shop") ||
                options.dynamicEvents.hasOwnProperty("woo_select_content_tag") ||
                options.dynamicEvents.hasOwnProperty("woo_select_content_single") ||
                options.dynamicEvents.hasOwnProperty("woo_select_content_category")
            ) {
                $('.product.type-product a.woocommerce-loop-product__link').onFirst('click', function (evt) {
                    var productId = $(this).parent().find("a.add_to_cart_button").attr("data-product_id");
                    if(options.dynamicEvents.hasOwnProperty("woo_select_content_search") &&
                        options.dynamicEvents.woo_select_content_search.hasOwnProperty(productId)) {
                        Analytics.onWooSelectContent(options.dynamicEvents.woo_select_content_search[productId][Analytics.tag()]);
                    } else if(options.dynamicEvents.hasOwnProperty("woo_select_content_shop") &&
                        options.dynamicEvents.woo_select_content_shop.hasOwnProperty(productId)) {
                        Analytics.onWooSelectContent(options.dynamicEvents.woo_select_content_shop[productId][Analytics.tag()]);
                    } else if(options.dynamicEvents.hasOwnProperty("woo_select_content_tag") &&
                        options.dynamicEvents.woo_select_content_tag.hasOwnProperty(productId)) {
                        Analytics.onWooSelectContent(options.dynamicEvents.woo_select_content_tag[productId][Analytics.tag()]);
                    } else if(options.dynamicEvents.hasOwnProperty("woo_select_content_single") &&
                        options.dynamicEvents.woo_select_content_single.hasOwnProperty(productId)) {
                        Analytics.onWooSelectContent(options.dynamicEvents.woo_select_content_single[productId][Analytics.tag()]);
                    } else if(options.dynamicEvents.hasOwnProperty("woo_select_content_category") &&
                        options.dynamicEvents.woo_select_content_category.hasOwnProperty(productId)) {
                        Analytics.onWooSelectContent(options.dynamicEvents.woo_select_content_category[productId][Analytics.tag()]);
                    }
                });
            }
        }


        // load pixel APIs
        Utils.loadPixels();
    });

    


}(jQuery, pysOptions);

function pys_generate_token(length){
    //edit the token allowed characters
    var a = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890".split("");
    var b = [];
    for (var i=0; i<length; i++) {
        var j = (Math.random() * (a.length-1)).toFixed(0);
        b[i] = a[j];
    }
    return b.join("");
}

function getBundlePriceOnSingleProduct(data) {
    var items_sum = 0;
    jQuery(".bundle_form .bundled_product").each(function(index){
        var id = jQuery(this).find(".cart").data("bundled_item_id");
        var item_price = data.prices[id];
        var item_quantity = jQuery(this).find(".bundled_qty").val();
        if(!jQuery(this).hasClass("bundled_item_optional") ||
            jQuery(this).find(".bundled_product_optional_checkbox input").prop('checked')) {
            items_sum += item_price*item_quantity;
        }
    });
    return items_sum;
}

function getPixelBySlag(slug) {
    switch (slug) {
        case "facebook": return window.pys.Facebook;
        case "ga": return window.pys.Analytics;
        case "google_ads": return window.pys.GAds;
        case "bing": return window.pys.Bing;
        case "pinterest": return window.pys.Pinterest;
        case "tiktok": return window.pys.TikTok;
    }
}