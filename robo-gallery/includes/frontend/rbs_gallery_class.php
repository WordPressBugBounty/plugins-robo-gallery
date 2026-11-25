<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'WPINC' ) ) exit;

require_once ROBO_GALLERY_FRONTEND_MODULES_PATH.'core.php';

/* The output of the classic types; Fusion Grid has its own (app/extensions/fusionGrid, chosen by the shortcode). */
class roboGallery{

 	public $id = 0;
 	public $options_id = 0;
 	public $gallery_type = 'grid';
 	public $galleryId = '';	

 	public $returnHtml = '';

 	public $isAjaxCall = false;	

 	public $core = null;

 	public $attr = array();

 	function __construct( $attr ){
 
 		if (wp_doing_ajax()) $this->isAjaxCall = true;
 		
 		if( !is_array($attr) || !isset($attr['id']) ) return ; 		
		
		$attr['id'] = (int) $attr['id'];
		if( !$attr['id'] ) return '';
		
 		$this->attr = $attr;
 		$this->galleryId = 'rbs_gallery_'.uniqid();	
		$this->id = $attr['id'];
		$this->initTypeGallery();
		$this->initOptionId();
		$this->core = new roboGalleryModuleCore( $this );
		$this->core->runEvent('gallery.init');
 	}

 	private function initTypeGallery(){
 		// stored type only (never the request), an empty one is grid
 		$this->gallery_type = rbsGalleryUtils::getTypeGallery( $this->id );
 	}

 	private function initOptionId(){ 
 		// "Clone Gallery" source, or the gallery itself when there is none / it's gone
 		$this->options_id = \RoboGallery\app\extensions\cloneSource\CloneSource::resolve( (int) $this->id );
 	}

 	public function getGallery( ){

 		if( !$this->id ) return '';
 		$this->returnHtml = '';

 		if( \RoboGallery\app\extensions\staticGallery\StaticGallery::isStaticRequest() ) return $this->getStaticGallery();

 		$this->core->runEvent('gallery.render.begin');
 		
 		$this->core->jsOptions->setValue( 'mainContainer', '#robo_gallery_main_block_'.$this->galleryId );
 		$this->core->jsOptions->setValue( 'wrapContainer', '#robo-gallery-wrap-'.$this->galleryId );

		$this->core->runEvent('gallery.images.get' );

		$this->core->runEvent('gallery.block.before');
		
		$this->returnHtml = roboGalleryModuleAssets::printForcedAssets( $this->getBlockGallery() );

		$this->core->runEvent('gallery.block.after');

		$this->core->runEvent('gallery.render.end');

		$static = \RoboGallery\app\extensions\staticGallery\StaticGallery::isNoscriptEnabled() ? $this->renderStatic() : '';
		if( $static ) $this->returnHtml .= '<noscript>'.$static.'</noscript>';

		return $this->returnHtml;
 	}

 	/**
 	 * Where the gallery's script can't run (StaticGallery::isStaticRequest()):
 	 * the texts and the images only, no assets or styles.
 	 */
 	private function getStaticGallery(){
		$this->core->runEvent('gallery.images.get');

		$static = $this->renderStatic();
		if( !$static ) return '';

		$pretext   = $this->core->getMetaCur('pretext');
		$aftertext = $this->core->getMetaCur('aftertext');

		return ( $pretext ? '<div>'.wp_kses_post($pretext).'</div>' : '' )
			.$static
			.( $aftertext ? '<div>'.wp_kses_post($aftertext).'</div>' : '' );
 	}

 	private function renderStatic(){
 		$limit = \RoboGallery\app\extensions\staticGallery\StaticGallery::limit();
 		return \RoboGallery\app\extensions\staticGallery\StaticGallery::render( $this->getStaticItems(), $limit );
 	}

 	/* the source's items (StaticGallery::render() takes the first $limit) */
 	private function getStaticItems(){
 		return $this->core->source->getItems();
 	}

 	private function getBlockGallery(){

 		if( !count($this->core->source->getItems()) ) return $this->showEmptyMessage();
 		return $this->core->renderBlock('gallery.block.main');
 	}


 	private function showEmptyMessage(){
 		switch ( $this->gallery_type ) {
			case 'youtubepro':
			case 'youtube':
				return $this->showEmptyMessageVideo();
				break;
			default:
				 return $this->showEmptyMessageImage();
				break;
		} 

 	}

 	private function showEmptyMessageImage(){
		return sprintf( 
			'<p><strong>%s</strong><br/>%s<br /><strong>%s</strong></p>',
			__('No Images.', 'robo-gallery'),
			__('Please upload images in images manager section. Click on Manage Images button on the right side of the gallery settings.', 'robo-gallery'),
			get_post_meta( $this->options_id, ROBO_GALLERY_PREFIX.'menuSelfImages', true ) ? 
				'' :
				__("Please make sure that you didn't enabled option: Images of the Current Gallery. Option should have Show value to show images.", 'robo-gallery')
		);			
 	}

 	private function showEmptyMessageVideo(){
		return sprintf(
			'<p><strong>%s</strong><br/>%s<br />%s</p>',
			__('No Youtube Videos.', 'robo-gallery'),
			__('Please make sure that you setup Youtube API key in gallery settings.  Check values of the video content IDs. Please contact Robo Gallery support if you can\'t find the reason of this problem.', 'robo-gallery'),
			$this->getSourceErrorsForEditors()
 		);
 	}

 	/**
 	 * The source's own reason (e.g. the YouTube API error) - for those who can
 	 * fix the gallery; visitors get the general text only.
 	 */
 	private function getSourceErrorsForEditors(){
 		if( !current_user_can( 'edit_post', $this->id ) ) return '';

 		$errors = $this->core->source->getErrors();
 		if( !count($errors) ) return '';

 		return '<em>'.esc_html__('Reason (shown to the gallery editors only):', 'robo-gallery').' '
 			.esc_html( implode( '; ', array_map( 'strval', $errors ) ) ).'</em>';
 	}

 }