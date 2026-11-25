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

/* the size and direction of the slider container; the rest of the settings go to the
   script in the config (layout.php, SliderOptions::PUBLIC_OPTIONS) */
class  roboGalleryModuleOptionsSlider extends roboGalleryModuleAbstraction{

	/* the slider's settings, see SliderOptions::forFrontend() */
	private $options = array();

	public function init(){

		$this->options = \RoboGallery\app\extensions\slider\SliderOptions::forFrontend( (int) $this->id );

 		$this->initSize();
		$this->initRTL();
	}


	public function initRTL(){
		if ( !is_rtl() ) return ;
		$this->core->element->setElementAttr( 'robo-gallery-slider-block', 'dir', "rtl" );
	}


	/* the values are checked by their definitions (forFrontend): a number and a unit of the option's list;
	   without them the slider's own styles give 100% / 80vh */
	public function initSize(){
 		if( !$this->options['sliderWidthAuto'] ) {
			$this->core->element->addElementStyle( 'robo-gallery-slider-block', 'width', (int) $this->options['sliderWidthValue'] . $this->options['sliderWidthType'] );
		}

		if( !$this->options['sliderHeightAuto'] ) {
			$this->core->element->addElementStyle( 'robo-gallery-slider-block', 'height', (int) $this->options['sliderHeightValue'] . $this->options['sliderHeightType'] );
		}

 	}

}
