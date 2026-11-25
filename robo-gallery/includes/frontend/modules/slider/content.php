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

/* the text of the content panel of each slide (filter gallery.slider.slide, see layout.php);
   the script shows it as text */
class  roboGalleryModuleContentSlider extends roboGalleryModuleAbstraction{

	/* the slider's settings, see SliderOptions::forFrontend() */
	private $options = array();

	public function init(){

		$this->options = \RoboGallery\app\extensions\slider\SliderOptions::forFrontend( (int) $this->id );

		if( $this->options['sliderContent'] ){
			$this->core->addFilter( 'gallery.slider.slide', array($this, 'addText'), 10, 2 );
		}
	}

	/**
	 * @param array $slide the slide of the config
	 * @param array $img   the source item
	 * @return array
	 */
	public function addText( $slide, $img ){

		if( empty($img['data']) ) return $slide;

		$desc = '';
		switch ( $this->options['sliderContentSource'] ){
			case 'title':
					$desc = $img['data']->post_title;
				break;
			case 'caption':
					$desc = $img['data']->post_excerpt;
				break;
			case 'description':
					$desc = $img['data']->post_content;
				break;

			default:
				break;
		}

		// plain text, as the script shows it: no markup (the old panel printed the tags escaped,
		// as text), entities decoded (stored titles may hold &amp; &lt;, the browser showed them decoded)
		$slide['text'] = is_string($desc) ? trim( html_entity_decode( wp_strip_all_tags( $desc ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';

		return $slide;
	}

}
