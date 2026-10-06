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

if ( ! defined( 'WPINC' ) ) exit;

require_once ROBO_GALLERY_FRONTEND_MODULES_PATH . 'class/source/type/youtube.php';
require_once ROBO_GALLERY_FRONTEND_MODULES_PATH . 'class/source/type/base.php';
require_once ROBO_GALLERY_FRONTEND_MODULES_PATH . 'class/source/type/slider.php';

class roboGalleryModuleSource{
	private $id = null;
	private $options_id = null;

	private $core = null;
	private $gallery = null;

	private $items = array();
	private $cats = array();
	private $tags = array();

	private $source 	= null;
	
	public $galleryType = 'base';

	public function __construct( $core ){
	        $this->core = $core;
	        $this->gallery = $core->gallery;

	        $this->id = $this->gallery->id;
	        $this->options_id = $this->gallery->options_id;  	       	
	       	$this->core->addEvent('gallery.images.get', array($this, 'initItems'));
	}

 	public function getItems(){
 		if( !is_array($this->items) ) return array();
 		return $this->items;
 	}

 	public function getCats(){
 		if( !is_array($this->cats) ) return array();
 		return $this->cats;
 	}

 	public function getTags(){
 		if( !is_array($this->tags) ) return array();
 		return $this->tags;
 	}

	/**
	 * The gallery's child galleries of all levels that the visitor may see, each
	 * followed by its own children (app/extensions/access/AlbumHierarchy.php).
	 *
	 * @return WP_Post[]
	 */
	public static function getChildGalleries( $galleryId ){
		return \RoboGallery\app\extensions\access\AlbumHierarchy::flatDescendants( (int) $galleryId );
	}

 	// only the YouTube source reports why it has no items
 	public function getErrors(){
 		if( !is_object($this->source) || !method_exists($this->source, 'getErrors') ) return array();
 		$errors = $this->source->getErrors();
 		return is_array($errors) ? $errors : array();
 	}

 	public function initItems(){ 		
 		$this->galleryType = rbsGalleryUtils::getTypeGallery( $this->id );

 		// robogrid prints an empty container: its script loads the images over REST
 		if( 'robogrid' === $this->gallery->gallery_type ) return ;

 		switch ( $this->galleryType ) {
			case 'youtubepro':
			case 'youtube':
				$this->source =new RoboYoutubeSource( $this->id, $this->core );
				break;

			case 'slider':
				$this->source =new RoboSliderSource( $this->id, $this->core );
				break;
				
			default:
				$this->source = new RoboBaseSource( $this->id, $this->core );
				break;
		} 

		$this->items = $this->source->getItems();
		$this->cats  = $this->source->getCats();
		$this->tags  = $this->source->getTags();
 		return ;
 	}

}