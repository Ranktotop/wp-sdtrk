var localScrollTracked = false;
var localClickedButtons = [];

function wp_sdtrk_runLocal() {
	jQuery(document).ready(function() {
		if (wp_sdtrk_local.enabled === "" || !wp_sdtrk_event) {
			return;
		}
		wp_sdtrk_track_local();
	});
}

//Fire on Server
function wp_sdtrk_track_local() {
	var metaData = {event: wp_sdtrk_event, type: 'local', subtype: 'init'};
	wp_sdtrk_sendAjax(metaData);

	//Time Trigger
	if (wp_sdtrk_event.getTimeTrigger().length > 0) {
		wp_sdtrk_track_local_timeTracker();
	}

	//Scroll-Trigger
	if (wp_sdtrk_event.getScrollTrigger() !== false) {
		wp_sdtrk_track_local_scrollTracker();
	}

	//Click-Trigger
	if (wp_sdtrk_event.getClickTrigger() !== false) {
		wp_sdtrk_track_local_clickTracker();
	}
}

//Activate time-tracker for Server
function wp_sdtrk_track_local_timeTracker() {
	var metaData = {event: wp_sdtrk_event, type: 'local', subtype: 'tt'};
		wp_sdtrk_event.getTimeTrigger().forEach((triggerTime) => {
			var time = parseInt(triggerTime);
			if (!isNaN(time)) {
				time = time * 1000;
				jQuery(document).ready(function() {
					setTimeout(function() {
						metaData.timeEventName = 'Watchtime_' + triggerTime.toString() + '_Seconds';
						wp_sdtrk_sendAjax(metaData);
					}, time);
				});
			}

		});
}

//Activate scroll-tracker for Server
function wp_sdtrk_track_local_scrollTracker() {
	if (localScrollTracked === true) {
		return;
	}
	var metaData = {event: wp_sdtrk_event, type: 'local', subtype: 'sd'};
		window.addEventListener('scroll', function() {
			if (localScrollTracked === true) {
				return;
			}
			var st = jQuery(this).scrollTop();
			var wh = jQuery(document).height() - jQuery(window).height();
			var target = wp_sdtrk_event.getScrollTrigger();
			var perc = Math.ceil((st * 100) / wh)

			if (perc >= target) {
				localScrollTracked = true;
				metaData.scrollEventName = 'Scrolldepth_' + wp_sdtrk_event.getScrollTrigger() + '_Percent';
				wp_sdtrk_sendAjax(metaData);
			}
		});
}

//Activate click-tracker for Server
function wp_sdtrk_track_local_clickTracker() {
	if (wp_sdtrk_buttons.length < 1) {
		return;
	}
	var metaData = {event: wp_sdtrk_event, type: 'local', subtype: 'bc'};
		wp_sdtrk_buttons.forEach((el) => {
			jQuery(el[0]).on('click', function() {
				if (!localClickedButtons.includes(el[1])) {
					localClickedButtons.push(el[1]);
					metaData.clickEventName = 'ButtonClick';
					metaData.clickEventTag = el[1];
					wp_sdtrk_sendAjax(metaData);
				}
			});

		});
}