(function () {
	'use strict';

	var SESSION_KEY = 'sts_benefits_popup_seen';

	function showPopup() {
		var overlay = document.getElementById('stsb-popup-overlay');
		if (!overlay) return;
		overlay.classList.add('stsb-visible');
	}

	function dismissPopup() {
		var overlay = document.getElementById('stsb-popup-overlay');
		if (!overlay) return;
		overlay.classList.remove('stsb-visible');
		try {
			sessionStorage.setItem(SESSION_KEY, '1');
		} catch (e) {}
	}

	function init() {
		try {
			if (sessionStorage.getItem(SESSION_KEY)) return;
		} catch (e) {}

		var overlay = document.getElementById('stsb-popup-overlay');
		var closeBtn = document.getElementById('stsb-popup-close');

		if (!overlay) return;

		// Show after a brief delay
		setTimeout(showPopup, 1200);

		if (closeBtn) {
			closeBtn.addEventListener('click', dismissPopup);
		}

		// CTA button click also marks the popup as seen for the session
		var ctaBtn = overlay.querySelector('.stsb-popup-btn');
		if (ctaBtn) {
			ctaBtn.addEventListener('click', function () {
				try { sessionStorage.setItem(SESSION_KEY, '1'); } catch (e) {}
			});
		}

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) dismissPopup();
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') dismissPopup();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
