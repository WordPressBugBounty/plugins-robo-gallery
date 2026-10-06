/* copy_banner_php */

(function () {

  const CAN_SHADOW = !!( document.head.attachShadow || document.head.createShadowRoot ); //false;//

  const roboGalleries = document.getElementsByClassName( "robo-gallery-simple-container" );

  if (roboGalleries == null || roboGalleries.length < 1) {
    return;
  }

  for (var i = 0; i < roboGalleries.length; i++){
		buildRoboGallery(roboGalleries[i]);
  }

  function buildRoboGallery(gallery) {
    if (gallery.getAttribute("data-options") == undefined) return;

    var id = gallery.id,
      options_id = gallery.getAttribute("data-options"),
      objectOptions = window[options_id],
      loader = window[objectOptions.loadingContainerObj];

    if (CAN_SHADOW) {
      const gallery_source = gallery.firstChild;
      const shadowContainer = gallery.attachShadow({ mode: "open" });
      shadowContainer.appendChild(gallery_source);
    }

    //console.log('id: ', id, 'objectOptions: ', objectOptions );
    if (gallery.style.display == "none") gallery.style.display = "block";
    if (loader !== null) loader.style.display = "none";
  }
})();
