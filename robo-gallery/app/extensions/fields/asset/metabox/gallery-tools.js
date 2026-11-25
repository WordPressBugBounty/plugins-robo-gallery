/*
 * "Gallery Tools" box (config/metabox/gallery_tools.php): a click on a copy field or its button copies the
 * field's text. The clipboard API, or (an http admin has none) the old copy command on the selected field;
 * if that fails too, the text stays selected for Ctrl+C.
 */
(function () {
	var COPIED_MS = 2000;

	function copyWithSelection(field) {
		field.focus();
		field.select();
		try {
			return true === document.execCommand('copy');
		} catch (e) {
			return false;
		}
	}

	function copy(field) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(field.value).then(function () {
				return true;
			}, function () {
				return copyWithSelection(field);
			});
		}
		return Promise.resolve(copyWithSelection(field));
	}

	function showResult(group, copied) {
		var tool = group.closest('.robo-gallery-tool');
		var status = tool ? tool.querySelector('.robo-gallery-copy-status') : null;

		group.classList.toggle('is-copied', copied);
		if (status) {
			status.classList.toggle('is-hint', !copied);
			status.textContent = group.getAttribute(copied ? 'data-copied' : 'data-select-hint') || '';
		}

		clearTimeout(group.roboGalleryCopyTimer);
		if (copied) {
			group.roboGalleryCopyTimer = setTimeout(function () {
				group.classList.remove('is-copied');
				if (status) {
					status.textContent = '';
				}
			}, COPIED_MS);
		}
	}

	document.addEventListener('click', function (event) {
		var group = event.target.closest ? event.target.closest('.robo-gallery-copy') : null;
		var field = group ? group.querySelector('.robo-gallery-copy-text') : null;
		if (!field) {
			return;
		}

		copy(field).then(function (copied) {
			showResult(group, copied);
			if (!copied) {
				field.focus();
				field.select();
			}
		});
	});
})();
