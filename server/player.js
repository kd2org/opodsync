/**
 * In-browser episode player for oPodSync.
 *
 * Plays an episode's audio directly from the podcast host and reports playback
 * position back to the sync server as gPodder "play" episode actions, so other
 * clients (AntennaPod, etc.) can resume where you left off and vice-versa.
 *
 * No new backend: it POSTs to the existing /api/2/episodes/current.json endpoint,
 * authenticated by the same session cookie used to view this page.
 */
(function () {
	'use strict';

	var bar     = document.getElementById('player-bar');
	var audio   = document.getElementById('pb-audio');
	var titleEl = document.getElementById('pb-title');
	var statusEl = document.getElementById('pb-status');
	var closeBtn = document.getElementById('pb-close');

	if (!bar || !audio) {
		return;
	}

	// Minimum gap between periodic position reports while playing. Each report is
	// one row in episodes_actions, so this trades sync granularity against table
	// growth. Pause / seek / end / page-unload always report regardless.
	var REPORT_INTERVAL = 20000;

	var current   = null; // { media, podcast, title, total }
	var startedAt = 0;    // position (s) where the current play session began
	var lastSent  = 0;    // epoch ms of the last report

	function setStatus(msg) {
		statusEl.textContent = msg || '';
	}

	function buildAction() {
		var position = Math.floor(audio.currentTime || 0);
		var total = Math.floor(audio.duration || current.total || 0) || null;

		var action = {
			podcast:   current.podcast,
			episode:   current.media,
			action:    'play',
			// gPodder expects "YYYY-MM-DDTHH:MM:SSZ"
			timestamp: new Date().toISOString().replace(/\.\d+Z$/, 'Z'),
			started:   Math.floor(startedAt),
			position:  position
		};

		if (total) {
			action.total = total;
		}

		return action;
	}

	// mode: false = throttled (periodic), true = immediate, 'unload' = beacon
	function report(mode) {
		if (!current) {
			return;
		}

		var now = Date.now();
		if (mode === false && now - lastSent < REPORT_INTERVAL) {
			return;
		}
		lastSent = now;

		var body = JSON.stringify([buildAction()]);

		if (mode === 'unload' && navigator.sendBeacon) {
			navigator.sendBeacon(
				'./api/2/episodes/current.json',
				new Blob([body], { type: 'application/json' })
			);
			return;
		}

		fetch('./api/2/episodes/current.json', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: body
		}).catch(function () {
			/* transient network error: the next periodic report will retry */
		});
	}

	function play(btn) {
		var media = btn.getAttribute('data-media');
		if (!media) {
			return;
		}

		// Flush the position of whatever was playing before we switch episodes.
		report(true);

		current = {
			media:   media,
			podcast: btn.getAttribute('data-podcast'),
			title:   btn.getAttribute('data-title') || media,
			total:   parseInt(btn.getAttribute('data-total'), 10) || 0
		};
		lastSent = 0;

		var resume = parseInt(btn.getAttribute('data-pos'), 10) || 0;

		titleEl.textContent = current.title;
		setStatus('Loading…');
		bar.hidden = false;

		var onMeta = function () {
			// Resume, unless we'd land within the last few seconds of the episode.
			if (resume > 0 && (!audio.duration || resume < audio.duration - 5)) {
				try { audio.currentTime = resume; } catch (e) { /* ignore */ }
			}
			startedAt = Math.floor(audio.currentTime || resume || 0);
			setStatus('');
			audio.play().catch(function () {
				setStatus('Could not play — the host may block playback or serve http:// (mixed content).');
			});
		};

		audio.addEventListener('loadedmetadata', onMeta, { once: true });
		audio.src = media;
		audio.load();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.play-btn') : null;
		if (btn) {
			e.preventDefault();
			play(btn);
		}
	});

	audio.addEventListener('timeupdate', function () { report(false); });
	audio.addEventListener('pause',  function () { report(true); });
	audio.addEventListener('seeked', function () { report(true); });
	audio.addEventListener('ended',  function () { report(true); });
	audio.addEventListener('error',  function () {
		setStatus('Could not load — the host may block playback or serve http:// (mixed content).');
	});

	closeBtn.addEventListener('click', function () {
		report(true);
		audio.pause();
		bar.hidden = true;
		current = null;
	});

	// Best-effort flush of the final position when leaving the page.
	window.addEventListener('pagehide', function () { report('unload'); });
})();
