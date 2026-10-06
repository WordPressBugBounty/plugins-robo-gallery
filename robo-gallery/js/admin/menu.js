/*
 * Robo Gallery admin menu: the external links (Pro Version, Support, Gallery
 * Demo, Video Guides) open in a new tab. The URLs come from PHP
 * (RoboGallery\app\extensions\adminMenu\AdminMenu); without JS the menu page
 * itself redirects to the same URL. Loaded in the footer, so #adminmenu exists.
 */
(function () {
	var links = (window.robo_gallery_vars && window.robo_gallery_vars.links) || {};

	Object.keys(links).forEach(function (slug) {
		var anchors = document.querySelectorAll('#adminmenu a[href$="page=' + slug + '"]');

		Array.prototype.forEach.call(anchors, function (anchor) {
			// the menu CSS finds the link by "page=<slug>" in href, which is
			// replaced below: the class keeps it findable
			anchor.classList.add('robo-gallery-menu-' + slug);
			anchor.href = links[slug];
			anchor.target = '_blank';
			anchor.rel = 'noopener';
		});
	});
})();
