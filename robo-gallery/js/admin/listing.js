/*
 * Galleries list: a click on the shortcode field copies the shortcode
 * (RoboGallery\app\extensions\listing\GalleryListing). Delegated, so rows
 * re-rendered by Quick Edit work too.
 */
(function () {
	var vars = window.robo_gallery_listing || {};
	var timer = null;

	function removeMessage() {
		var old = document.querySelector('.robo-gallery-shortcode-message');
		if (old) {
			old.parentNode.removeChild(old);
		}
	}

	function showMessage(field) {
		removeMessage();

		var message = document.createElement('p');
		message.className = 'robo-gallery-shortcode-message';
		message.textContent = vars.copied || 'ShortCode copied to clipboard!';
		field.parentNode.insertBefore(message, field.nextSibling);

		clearTimeout(timer);
		timer = setTimeout(removeMessage, 3000);
	}

	// fallback for browsers / non-HTTPS admins without the async clipboard API
	function copyWithSelection(field) {
		field.select();
		try {
			return document.execCommand('copy');
		} catch (e) {
			return false;
		}
	}

	document.addEventListener('click', function (event) {
		var field = event.target.closest ? event.target.closest('.robo-gallery-shortcode') : null;
		if (!field || 'INPUT' !== field.tagName) {
			return;
		}

		field.select();

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(field.value).then(function () {
				showMessage(field);
			}, function () {
				if (copyWithSelection(field)) {
					showMessage(field);
				}
			});
			return;
		}

		if (copyWithSelection(field)) {
			showMessage(field);
		}
	});
})();
