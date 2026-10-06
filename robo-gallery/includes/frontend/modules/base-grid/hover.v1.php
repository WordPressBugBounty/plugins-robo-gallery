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

class  roboGalleryModuleHoverV1 extends roboGalleryModuleAbstraction{

	const hoverTypeDisable 	= 0;
	const hoverTypeEffect 	= 'baseEffect';
	
	const hoverTypeIcons 	= 1;
	const hoverTypeTemplate = 2;

	private $style = array();
	private $hoverType = null;

	private $linkIcon 	= '';
	private $zoomIcon 	= '';
	private $titleHover = '';
	private $descHover 	= '';

	private $templateHover  = '';
	
	public function init(){
		$this->initScss();
		//$this->core->addEvent('gallery.block.before', array($this, 'initHover'));
		$this->core->addEvent('gallery.init', array($this, 'initHover'));
	}


	public function initHover(){
		$this->hoverType = $this->getMeta('hover');
		// '0' / '1' / '2' (Off / Options / Template); an empty value means Off as it
		// did before PHP 8, where '' == 0 stopped being true
		if( (int) $this->hoverType === self::hoverTypeDisable ) return ;

		//if( $this->getMeta('effectType') != self::hoverTypeEffect ) return ;

		$this->initMobileHover();
		$this->initIconsHover();
		$this->initTemplateHover();		

		$this->initCssStyle();

		$this->core->addEvent('gallery.image.init', array($this, 'getHoverContent'));
	}
	

	private function initCssStyle(){
		if( !is_array($this->style) || !count($this->style) ) return ;

		foreach ($this->style as $elClass => $cssStyle) {
			$this->scssContent .= '.robo-gallery-wrap-id#{$galleryid}:not(#no-robo-galery) .'.$elClass.'{'
				.$cssStyle
			.'}';
		}
		
	}

	private function initMobileHover(){
		if( !$this->getMeta('noHoverOnMobile' ) ) return ;
		$this->jsOptions->setValue( 'noHoverOnMobile',  'false' );			
	}

	private function initTemplateHover(){
		if( $this->hoverType != self::hoverTypeTemplate  ) return ;
		$template = $this->getMeta('desc_template');
		// The template is printed as HTML, and the meta can be written raw
		// (core custom fields), so strip scripts, on* handlers and javascript:
		// URLs with the core allowlist. Runs before the @PLACEHOLDER@ substitution,
		// whose values are escaped separately in getHoverContent().
		$this->templateHover = is_string($template) ? wp_kses_post($template) : '';
	}

	private function initIconsHover(){		
		if( $this->hoverType != self::hoverTypeIcons) return ;

		$this->linkIcon 	= $this->getTemplateItem( $this->getMeta('linkIcon'), 'rbsLinkIcon', 1 );
		$this->zoomIcon 	= $this->getTemplateItem( $this->getMeta('zoomIcon'), 'rbsZoomIcon', 1 , ($this->getMeta('thumbClick')?' rbs-lightbox':'') );
		$this->titleHover 	= $this->getTemplateItem( $this->getMeta('showTitle'),'rbsTitle', 	 '@TITLE@' );
		$this->descHover 	= $this->getTemplateItem( $this->getMeta('showDesc'), 'rbsDesc', 	 '@DESC@' );
	}


 	private function getTemplateItem( $item, $class = '', $template = '', $addClass = '' ){
		
		if( !is_array($item) 		 || !count($item) ) 	return ;
		if( !isset($item['enabled']) || !$item['enabled'] ) return ;

		// the colors are written into SCSS text below: an invalid one is dropped,
		// esc_attr() alone would let ";{}" add rules of their own
		foreach( array( 'color', 'colorHover', 'colorBg', 'colorBgHover' ) as $colorKey ){
			if( isset($item[$colorKey]) && !\RoboGallery\app\extensions\validation\CssColor::isValid($item[$colorKey]) ) unset( $item[$colorKey] );
		}

		$this->style[$class] = '';

		if( isset($item['fontSize'])) 		$this->style[$class] .= ' font-size:'.      (int)$item['fontSize'].'px;';
		if( isset($item['fontLineHeight'])) $this->style[$class] .= ' line-height:'.	(int)$item['fontLineHeight'].'%;';
		if( isset($item['color'])) 			$this->style[$class] .= ' color:'.			esc_attr($item['color']).';';
		if( isset($item['fontBold'])) 		$this->style[$class] .= ' font-weight:'.	($item['fontBold']		?'bold'		:'normal').';';
		if( isset($item['fontItalic'])) 	$this->style[$class] .= ' font-style:'.		($item['fontItalic']	?'italic'	:'normal').';';
		if( isset($item['fontUnderline'])) 	$this->style[$class] .= ' text-decoration:'.($item['fontUnderline'] ?'underline':'none').';';
		if( isset($item['colorHover'])) 	$this->style[$class] .= ' &:hover{ color:'.	esc_attr($item['colorHover']).'; }';

		if( $template!=1 ) return '<div class="'.$class.' '.$addClass.'">'.$template.'</div>'; 

		if(isset($item['colorBg'])) $this->style[$class] .= 'background:'.esc_attr($item['colorBg']).';';

		if(isset($item['color']) && isset($item['borderSize']) && $item['borderSize'])
			$this->style[$class] .= 'border:'.(int)$item['borderSize'].'px solid '.esc_attr($item['color']).';';

		if(isset($item['colorHover']) && isset($item['borderSize']) && $item['borderSize'])
			$this->style[$class] .= '&:hover{ border:'.(int)$item['borderSize'].'px solid '.esc_attr($item['colorHover']).'; }';

		if(isset($item['colorBgHover']))
			$this->style[$class] .= '&:hover{ background:'.esc_attr($item['colorBgHover']).'; }';
		
		return '<i class="fa '.esc_attr($item['iconSelect']).' '.$class.' '.$addClass.'" ></i>';
	}


	function getHoverContent( $img ){			
			$hoverHTML = '';

			if($this->hoverType == self::hoverTypeIcons ){
				$hoverHTML .= $this->titleHover;
				if( $this->linkIcon || $this->zoomIcon ){
					$hoverHTML .= '<div class="rbsIcons">';
					if($this->linkIcon && $img['link'])
						$hoverHTML .= '<a href="@LINK@" '.($img['typelink']?'target="_blank"':'').' title="@TITLE@">'
										.$this->linkIcon
									.'</a>';
					if($this->zoomIcon) $hoverHTML .= $this->zoomIcon;
					$hoverHTML .= '</div>';
				}
				$hoverHTML .= $this->descHover;
			}


			/* robo_gallery check in class */
			if( $this->hoverType == self::hoverTypeTemplate  && $this->templateHover){
				$hoverHTML = $this->templateHover; 
			}


			if($hoverHTML){				
				$hoverHTML =  str_replace( 
					array('@TITLE@','@CAPTION@','@DESC@', '@LINK@', '@VIDEOLINK@'), 
					array( 
						esc_attr($img['data']->post_title),
						esc_attr($img['data']->post_excerpt),
						esc_attr($img['data']->post_content),
						esc_url($img['link']),  //need check for link 
						esc_url($img['videolink']), //need check for videolink 
					), 
					$hoverHTML
				);
			}
			$hoverHTML = '<div class="thumbnail-overlay">'.$hoverHTML.'</div>'; //.( !$this->zoomIcon ?'rbs-lightbox':'')
			
			return $hoverHTML;
		}

}