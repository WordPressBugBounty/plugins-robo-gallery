/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

// "Add New" links of the galleries open the type dialog instead of the page
// (the page itself opens it too, via showDialog=1, when JS is late or off).
(function () {
	'use strict';

	const openDialog = function (event) {
		if (typeof window.showRoboDialog !== 'function') {
			return;
		}
		event.preventDefault();
		window.showRoboDialog();
	};

	const bind = function (link) {
		if (!link) {
			return;
		}
		link.addEventListener('click', openDialog);
		if (link.href.indexOf('showDialog=1') === -1) {
			link.href += '&showDialog=1';
		}
	};

	// admin menu: Robo Gallery -> Add New
	bind(document.querySelector('#menu-posts-robo_gallery_table a[href*="post-new.php?post_type=robo_gallery_table"]'));

	// "Add New" button at the top of the gallery screens
	const config = window.robo_js_config || {};
	if (config.bodyClass && document.body.classList.contains(config.bodyClass)) {
		bind(document.querySelector('.wrap .page-title-action'));
	}
})();
