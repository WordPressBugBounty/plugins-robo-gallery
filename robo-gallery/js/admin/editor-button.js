/*
 * "Add Robo Gallery" button above the classic editor: opens the gallery picker
 * dialog (printed once per page, RoboGallery\app\extensions\editorButton\EditorButton)
 * and inserts the shortcode into the editor whose button was clicked.
 */
jQuery(function ($) {
	var vars = window.robo_gallery_trans || {};
	var dialog = $('#' + vars.dialogId);
	var editorId = '';

	if (!dialog.length) {
		return;
	}

	function selectedGalleryId() {
		return parseInt($('#robo-gallery-editor-id', dialog).val(), 10) || 0;
	}

	dialog.appendTo('body').dialog({
		dialogClass: 'wp-dialog',
		title: vars.roboGalleryTitle,
		modal: true,
		autoOpen: false,
		width: 'auto',
		maxWidth: 700,
		height: 'auto',
		resizable: false,
		draggable: false,
		closeOnEscape: true,
		buttons: [{
			text: vars.closeButton,
			'class': 'button-default',
			click: function () {
				$(this).dialog('close');
			}
		}, {
			text: vars.insertButton,
			'class': 'button-primary',
			click: function () {
				var galleryId = selectedGalleryId();

				if (galleryId) {
					// send_to_editor (media-upload.js) inserts into wpActiveEditor
					if (editorId) {
						window.wpActiveEditor = editorId;
					}
					window.send_to_editor('[robo-gallery id="' + galleryId + '"]');
				}
				$(this).dialog('close');
			}
		}]
	});

	$(document).on('click', '.robo-gallery-insert', function (event) {
		event.preventDefault();
		editorId = $(this).attr('data-editor') || '';
		dialog.dialog('open');
	});
});
