/*
 * Galleries list: the "Statistics" button next to "Add New" shows and hides
 * the statistics panel (RoboGallery\app\extensions\stats\StatsPanel). The panel
 * is printed with the notices; it moves under the page title here. Opened
 * at once for #robo-gallery-stats: the old Statistics page and Reset link there.
 */
(function () {
	var vars = window.robo_gallery_stats || {};
	var panel = document.getElementById(vars.panelId || 'robo-gallery-stats');
	var headerEnd = document.querySelector('.wrap .wp-header-end');
	var heading = document.querySelector('.wrap .wp-heading-inline');

	if (!panel || !headerEnd || !heading) {
		return;
	}

	headerEnd.parentNode.insertBefore(panel, headerEnd.nextSibling);

	// "Add New" is missing for users who can't create galleries
	var actions = document.querySelectorAll('.wrap .page-title-action');
	var after = actions.length ? actions[actions.length - 1] : heading;

	// id kept from the old button
	var button = document.createElement('a');
	button.id = 'rbs_stats_button';
	button.className = 'page-title-action';
	button.href = '#' + panel.id;
	button.setAttribute('role', 'button');
	button.setAttribute('aria-controls', panel.id);
	button.textContent = vars.button || 'Statistics';
	after.parentNode.insertBefore(button, after.nextSibling);

	function toggle(open) {
		panel.hidden = !open;
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	toggle('#' + panel.id === window.location.hash);

	button.addEventListener('click', function (event) {
		event.preventDefault();
		toggle(panel.hidden);
	});

	var form = panel.querySelector('form');
	if (form && vars.confirm) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm(vars.confirm)) {
				event.preventDefault();
			}
		});
	}
})();
