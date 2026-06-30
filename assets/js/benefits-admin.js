(function ($) {
	'use strict';

	$(function () {
		// wc-enhanced-select initialises .wc-product-search automatically via its own
		// DOMContentLoaded handler. We only need to init the plain wc-enhanced-select
		// elements (category dropdown) that WC does not auto-init.
		if ($.fn.select2) {
			$('.wc-enhanced-select').filter(':not(.select2-hidden-accessible)').select2({
				allowClear: true,
				placeholder: 'בחר קטגוריות...',
			});
		}
	});
}(jQuery));
