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

/* the slider script (react_parts/slider, build-js.bat copies it here): Swiper and its styles are
   inside, injected into the shadow root - no jQuery, no CSS file, the same in the Alternative mode */
class  roboGalleryModuleAssetsSlider extends roboGalleryModuleAssets{

	protected function initJsFilesListAlt(){
		$this->initJsFilesList();
	}

	protected function initJsFilesList(){
		$this->files['js']['robo-gallery-slider-script'] = array(
			'url' 		=> $this->moduleUrl.'assets/main.js',
			'depend' 	=> array()
		);
	}

}
