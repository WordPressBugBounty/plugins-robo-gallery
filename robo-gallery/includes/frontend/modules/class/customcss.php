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

class  roboGalleryModuleCustomCss extends roboGalleryModuleAbstraction{
	
	public function init(){
		$customCss = $this->getMeta('cssStyle');
		if( !$customCss ) return ;
		$customCss = str_replace(array("\r\n", "\r", "\n"), '', $customCss);
		$customCss = str_replace(array("\t"), '', $customCss);
		// saved through wp_kses_post, which turns ">" (child selector) into "&gt;";
		// "<" is never valid CSS and must not close the <style> block
		$customCss = str_replace( array( '&gt;', '<' ), array( '>', '' ), $customCss );
		$customCss = trim($customCss);
		if( $customCss ){
			$this->core->setContent( $customCss, 'CssBefore');		
		}
	}
}