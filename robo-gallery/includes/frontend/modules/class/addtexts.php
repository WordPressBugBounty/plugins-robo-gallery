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

class  roboGalleryModuleAddTexts extends roboGalleryModuleAbstraction{
	
	public function init(){
		$pretext = $this->getMetaCur('pretext');
		if( $pretext ) $this->core->setContent( '<div>'.wp_kses_post($pretext).'</div>', 'Begin');
		
		$aftertext = $this->getMetaCur('aftertext');
		if( $aftertext ) $this->core->setContent( '<div>'.wp_kses_post($aftertext).'</div>', 'End');	
	}
}