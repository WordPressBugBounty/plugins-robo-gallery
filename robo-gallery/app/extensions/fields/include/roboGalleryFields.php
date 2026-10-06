<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Config-driven metaboxes of the gallery editor (fields/config/metabox/*.php).
 * Loaded in the admin only (fields/init.php); assets and the body class only on
 * the gallery edit screen - the CMB2 option boxes of the classic galleries use
 * the same styles (div.roboGalleryFields).
 */
class roboGalleryFields{

	protected static $instance;

	protected $config;

	protected function __construct(){}

	public static function getInstance(){
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(){
		add_action('init', 					array($this, 'initConfig'));
		add_action('init', 					array($this, 'addMetaBoxes'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
		add_filter('admin_body_class', 		array($this, 'adminBodyClass'));
	}

	public function initConfig(){
		$this->config = new roboGalleryFieldsConfig();
	}

	public function addMetaBoxes(){
		foreach ((array)$this->config->get('metabox') as $name => $metaBoxConfig) {
			// a config can opt out for the current request by returning an empty array
			// (e.g. shortcode.php without a gallery ID)
			if (empty($metaBoxConfig['settings']['id'])) {
				continue;
			}
			new roboGalleryFieldsMetaBoxClass($metaBoxConfig);
		}
	}

	/**
	 * The gallery add/edit screen (post.php / post-new.php of the gallery post type).
	 */
	protected function isGalleryEditScreen(){
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		return $screen && 'post' === $screen->base && ROBO_GALLERY_TYPE_POST === $screen->post_type;
	}

	public function enqueueScripts(){
		if (!$this->isGalleryEditScreen()) {
			return;
		}

		/* CSS */
		wp_enqueue_style( ROBO_GALLERY_ASSETS_PREFIX.'app-style', 			ROBO_GALLERY_FIELDS_URL . 'asset/core/css/app-style.css', array(), ROBO_GALLERY_VERSION);
		wp_enqueue_style( ROBO_GALLERY_ASSETS_PREFIX.'app-update-key', 		ROBO_GALLERY_FIELDS_URL . 'asset/fields/css/update.key.css', array(), ROBO_GALLERY_VERSION);
		wp_enqueue_style( ROBO_GALLERY_ASSETS_PREFIX.'-field-type-youtube', ROBO_GALLERY_FIELDS_URL . 'asset/fields/youtube/style.css', array(), ROBO_GALLERY_VERSION);

		/* JS */
		wp_enqueue_script( ROBO_GALLERY_ASSETS_PREFIX.'foundation', 	ROBO_GALLERY_FIELDS_URL . 'asset/foundation/foundation.min.js', array('jquery'), ROBO_GALLERY_VERSION, true);
		wp_enqueue_script( ROBO_GALLERY_ASSETS_PREFIX.'app', 			ROBO_GALLERY_FIELDS_URL . 'asset/core/js/app.js', array(ROBO_GALLERY_ASSETS_PREFIX.'foundation'), ROBO_GALLERY_VERSION, true);

		// Field help tooltips (asset/help, template/element/label.tooltip) are not
		// used by any field config, so help.js/help.css are not loaded.
	}


	public function adminBodyClass($classes){
		return $this->isGalleryEditScreen() ? $classes . ' ' . ROBO_GALLERY_FIELDS_BODY_CLASS : $classes;
	}

	public function getConfig(){
		return $this->config;
	}
}
