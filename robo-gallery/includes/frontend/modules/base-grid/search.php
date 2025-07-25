<?php
/* 
*      Robo Gallery     
*      Version: 5.0.6 - 12273
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'WPINC' ) ) exit;

class  roboGalleryModuleSearchV1 extends roboGalleryModuleAbstraction{		

	public function init(){
		$this->core->addEvent('gallery.init', array($this, 'initSearch'), 1);
	}

 	public function initSearch(){
 		if( !get_post_meta( $this->gallery->id,  ROBO_GALLERY_PREFIX.'searchEnable', true ) ) return '';

 		$this->scssVar['galleryid'] = $this->gallery->real_id ? $this->gallery->real_id : $this->gallery->id;

		$this->core->addEvent('scss.initImport', 		array($this, 'initScss'));
		$this->core->addEvent('scss.initVariables', 	array($this, 'initVariables'));
		$this->core->addEvent('scss.initContent', 		array($this, 'initContent'));
 				
		$searchColor = get_post_meta( $this->gallery->id,  ROBO_GALLERY_PREFIX.'searchColor', true );
		if($searchColor){
			$this->scssVar['searchColor'] = $searchColor;
			$this->scssContent .= ' 
			.robo-gallery-wrap-id#{$galleryid}:not(#no-robo-galery) .rbs_search_wrap{ 
				color: $searchColor;
				input.rbs-search{ 
					border-color: $searchColor; 
					color: $searchColor; 
					&::placeholder { 
						color: $searchColor; 
					}
				}				
			}';
		}

		$retHtml = ''; 
		/* Search gallery item block */
		$retHtml .= '<div class="rbs_search_wrap">';
			$searchLabel = get_post_meta( $this->gallery->id,  ROBO_GALLERY_PREFIX.'searchLabel', true );
			$retHtml .= '<input type="text" class="rbs-search" placeholder="'.$searchLabel.'" />';
		$retHtml .= '</div>';

		/* Setup  gallery */
		$this->jsOptions->setValue( 'search',  		'#'.$this->gallery->galleryId.'filter .rbs-search' );
		$this->jsOptions->setValue( 'searchTarget',  '.rbs-img-image' );

		$this->core->setContent( $retHtml, 'menuV1.buttons');		
 	}

	public function initVariables( $scssCompiler ){
		$scssCompiler->addVariables( $this->scssVar );
	}

	public function initContent( $scssCompiler ){		
		$scssCompiler->addContent( $this->scssContent );
	}	

}