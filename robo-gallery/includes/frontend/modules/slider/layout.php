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

/**
 * The slider: an empty container (.RoboGallerySlider) and its config,
 * window["robogallery_slider_config_<galleryId>"] = { version, rtl, options, slides },
 * rendered by the script (react_parts/slider, assets/main.js) in a shadow root.
 * Inside the container until the script runs: the loader. The images for
 * visitors without JavaScript follow it, as for every type (StaticGallery).
 */
class  roboGalleryModuleLayoutSlider extends roboGalleryModuleAbstraction{

	/* the config format the script reads (react_parts/slider/src/config.js) */
	const CONFIG_VERSION = 1;

	public function init(){
		$this->core->addEvent('gallery.block.main', array($this, 'renderMainBlock') );
	}


	public function renderMainBlock(){

		$type_source = esc_attr( $this->getMeta('gallery_type_source') );
		$slides      = $this->getSlides();

		return
	 		$this->core->getContent('Begin')
	 		.'<style type="text/css" scoped>'.wp_strip_all_tags($this->core->getContent('CssBefore')).'</style>'

			.'<div id="robo-gallery-slider-wrap'.$this->galleryId.'" class="robo-gallery-slider-wrap robo-gallery-slider-wrap-id'.$this->id.' robo-gallery-'. $type_source.'">'

				.$this->core->getContent('BlockBefore')

				.'<div id="robo-gallery-slider-block-'.$this->galleryId.'" data-config-id="'.esc_attr($this->galleryId).'" robogallery_id="'.(int) $this->id.'"
					class="RoboGallerySlider robo-gallery-slider-container robo-gallery-slider-'.$this->id.'"
					style="'.$this->core->element->getElementStyles('robo-gallery-slider-block').'"
					'.$this->core->element->getElementAttrs('robo-gallery-slider-block').'
				>'
					.$this->core->getContent('FirstInit')
				.'</div>'

				.$this->core->getContent('BlockAfter')

			.'</div>'

			.'<script>'.$this->compileJavaScript( $slides ).'</script>'

			.$this->core->getContent('End');
	}


	/**
	 * The slides of the config: the source items (order, child galleries:
	 * RoboSliderSource) with an image; the content module adds the text.
	 *
	 * @return array
	 */
	public function getSlides(){
		$slides = array();
		foreach ( $this->core->source->getItems() as $item ){
			if( !is_array($item) ) continue ;

			$src = esc_url_raw( $item['thumb'] ?? '' );
			if( !$src ) continue ;

			$slide = array(
				'id'     => (int) ( $item['id'] ?? 0 ),
				'src'    => $src,
				'width'  => (int) ( $item['sizeW'] ?? 0 ),
				'height' => (int) ( $item['sizeH'] ?? 0 ),
				// text for the script's alt attribute: entities decoded, as the browser read them before
				'alt'    => is_string( $item['alt'] ?? null ) ? html_entity_decode( $item['alt'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '',
				'text'   => '',
			);

			$slides[] = $this->core->applyFilters( 'gallery.slider.slide', $slide, $item );
		}
		return $slides;
	}


	/* JSON_HEX_*: no "</script>", quote or ampersand of a title can leave the script */
 	public function compileJavaScript( $slides ){
		$config = array(
			'version' => self::CONFIG_VERSION,
			'rtl'     => is_rtl(),
			'options' => array_intersect_key(
				\RoboGallery\app\extensions\slider\SliderOptions::forFrontend( (int) $this->id ),
				array_flip( \RoboGallery\app\extensions\slider\SliderOptions::PUBLIC_OPTIONS )
			),
			'slides'  => $slides,
		);

 		return 'window["robogallery_slider_config_'.esc_js($this->galleryId).'"] = '
			.wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ).';';
 	}
}
