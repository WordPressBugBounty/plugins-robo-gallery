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

class  roboGalleryModuleResizeV1  extends roboGalleryModuleAbstraction{

	public function init(){		
		
		$this->core->addFilter('gallery.source.items', array($this, 'resizeImg'), 0, 1 );

		$gallery_type = $this->getMeta('gallery_type');
		$gallery_source = $this->getMeta('gallery_type_source');

		if( $gallery_type == 'mosaicpro' ) {

			if( $gallery_source=='mosaicpro-1' ){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN1'), 0, 1 );
			}elseif( $gallery_source=='mosaicpro-2' ){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN2'), 0, 1 );
			}elseif( $gallery_source=='mosaicpro-3' ){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN3'), 0, 1 );
			}elseif($gallery_source=='mosaicpro-4'){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN4'), 0, 1 );
			}elseif($gallery_source=='mosaicpro-5'){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN5'), 0, 1 );
			}elseif($gallery_source=='mosaicpro-6'){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicProN6'), 0, 1 );
			} else {
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicPro'), 0, 1 );
			}
		}
		
		if( $gallery_type == 'mosaic' ){
			$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMosaicFree'), 0, 1 );
		}
		
		if( $gallery_type == 'masonry' ){
			$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMasonryFree'), 0, 1 );
		}

		//if( $gallery_type == 'masonry' || $gallery_type == 'masonrypro' ){
			//$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMasonryFree'), 0, 1 );
		//}

		if( $gallery_type == 'masonrypro' ){
			if( 
				$gallery_source=='masonrypro-1' ||
								
				$gallery_source=='masonrypro-4' || 
				$gallery_source=='masonrypro-5' || 
				$gallery_source=='masonrypro-6' || 
				$gallery_source=='masonrypro-7' || 
				$gallery_source=='masonrypro-8' 
			){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMasonryProN1'), 0, 1 );
			}
			if( $gallery_source=='masonrypro-3' ){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMasonryProN3'), 0, 1 );
			}
			if( $gallery_source=='masonrypro-2' ){
				$this->core->addFilter('gallery.source.items', array($this, 'resizeImgMasonryProN2'), 0, 1 );
			}
		}

	}

	public function resizeImg( $items ){
		
		//echo 'Gallery Type :: ' . $this->getMeta('gallery_type') . '<br/ >';
		//echo 'Gallery Demo :: '.$this->getMeta('gallery_type_source') . '<br/ >';
		
		if ( ! is_array( $items ) || !count( $items ) ){
			return array();
		}

		//if($this->getMeta('gallery_type') != 'mosaic') return $items;
		
		return $items;
	}

	/**
	 * The large tiles of masonry: the 'large' size, which keeps the image's
	 * proportions (a small image gets its original). Since June 2025 releases
	 * these tiles showed the full original (the old cropped
	 * RoboGalleryMansoryImagesTop size was no longer registered); 'large' looks
	 * the same at tile width and weighs less.
	 *
	 * The tile's proportions (sizeW/sizeH -> data-width/data-height, the tile
	 * height in jquery.collagePlus.js) come from the original: WordPress rounds a
	 * resized image to whole pixels (1000x1500 -> 683x1024), and with the
	 * floored tile height that is 1px less on some tiles, adding up down a
	 * masonry column.
	 */
	private function withMasonryThumb( $img ){
		if( empty($img['id']) ) return $img;

		$tile = wp_get_attachment_image_src( $img['id'], 'large' );
		if( !is_array($tile) || empty($tile[0]) || empty($tile[1]) || empty($tile[2]) ) return $img;

		$img['thumb'] = $tile[0];
		$img['sizeW'] = (int) $tile[1];
		$img['sizeH'] = (int) $tile[2];

		$meta = wp_get_attachment_metadata( $img['id'] );
		if( is_array($meta) && !empty($meta['width']) && !empty($meta['height']) ){
			$img['sizeW'] = (int) $meta['width'];
			$img['sizeH'] = (int) $meta['height'];
		}
		return $img;
	}

	public function resizeImgMasonryProN1( $items ){
		//echo 'resizeImgMasonryProN1';
		if ( ! is_array( $items ) || !count( $items ) ) return array();		

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ){	

			$counterImg++;			
			
             //                               2 5  4   6    4  4    6  4    6   4  4   6   4   6 
			if( !in_array( $counterImg, array( 2, 7, 11, 17, 21, 25, 31,/* 35, 41, 45, 49, 55, 59, 65*/ ) ) ){
				$items[ $imgKey ] = $this->withMasonryThumb( $img );
			}


			if( $counterImg == 31 ) $counterImg = 7;
		}
		return $items;
	}

	public function resizeImgMasonryProN2( $items ){		
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();		

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ){	

			$counterImg++;			
			
             //                               2 5  4   6    4  4    6  4    6   4  4   6   4   6 
			if( !in_array( $counterImg, 
				array( 
					2, 4, 
					6, 8, 10,
					11, 13, 17, 
					21, 25, 31,/* 35, 41, 45, 49, 55, 59, 65*/ ) ) ){
				$items[ $imgKey ] = $this->withMasonryThumb( $img );
			}


			if( $counterImg == 31 ) $counterImg = 7;
		}
		return $items;
	}

	public function resizeImgMasonryProN3( $items ){		
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();		

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ){	

			$counterImg++;			
			
             //                               2 5  4   6    4  4    6  4    6   4  4   6   4   6 
			if( !in_array( $counterImg, 
				array( 
					2,     4, 
					 8, 10,
					 11, 13, 17, 21, 25, 31,/* 35, 41, 45, 49, 55, 59, 65*/ ) ) ){
				$items[ $imgKey ] = $this->withMasonryThumb( $img );
			}


			if( $counterImg == 31 ) $counterImg = 7;
		}
		return $items;
	}


	public function resizeImgMasonryFree( $items ){
		//echo "resizeImgMasonryFree";
		if ( ! is_array( $items ) || !count( $items ) ) return array();		

		$counterImg = 0;
		

		foreach ( $items as $imgKey => $img ) {

			if( $counterImg == 31 ) $counterImg = 7;

			$counterImg++;			
			
             //                               2 5  4   6    4  4    6  4    6   4  4   6   4   6 
			if( !in_array( $counterImg, array( 2, 7, 11, 17, 21, 25, 31,/* 35, 41, 45, 49, 55, 59, 65*/ ) ) ){
				$items[ $imgKey ] = $this->withMasonryThumb( $img );
			}
		}
		return $items;
	}


	public function resizeImgMosaicFree( $items ){
		//echo "resizeImgMosaicFree";
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 1){
				$items[ $imgKey ]['col'] = 4;
			}

			if( $counterImg == 5){
				$items[ $imgKey ]['col'] = 3;
			}

			if( $counterImg == 10){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 13 ){
				$items[ $imgKey ]['col'] = 4;
			}

			if( $counterImg == 19 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}

	public function resizeImgMosaicPro( $items ){
		//echo "resizeImgMosaicPro";
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 1){
				$items[ $imgKey ]['col'] = 4;
			}

			if( $counterImg == 5){
				$items[ $imgKey ]['col'] = 3;
			}

			if( $counterImg == 10){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 15 ){
				$items[ $imgKey ]['col'] = 4;
			}

			if( $counterImg == 19 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}

	public function resizeImgMosaicProN1( $items ){
		//echo "resizeImgMosaicProN1";
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 5){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 16){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 18 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}

	public function resizeImgMosaicProN2( $items ){
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 2){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 3){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 12){
				$counterImg = 0;
			}			
			
		}
		return $items;
	}

	public function resizeImgMosaicProN3( $items ){
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 1){
				$items[ $imgKey ]['col'] = 4;
			}

			if( $counterImg == 10){
				$items[ $imgKey ]['col'] = 3;				
			}

			if( $counterImg == 11){
				$items[ $imgKey ]['col'] = 2;				
			}

			if( $counterImg == 15){
				$counterImg = 0;
			}
			
		}
		return $items;
	}


	public function resizeImgMosaicProN4( $items ){
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 4){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 11){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 12 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}

	public function resizeImgMosaicProN5( $items ){
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 5){
				$items[ $imgKey ]['col'] = 2;
			}
			if( $counterImg == 6){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 8){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 11 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}

	public function resizeImgMosaicProN6( $items ){
		
		if ( ! is_array( $items ) || !count( $items ) ) return array();

		$counterImg = 0;

		foreach ( $items as $imgKey => $img ) {

			$counterImg++;

			if( $counterImg == 4){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 11){
				$items[ $imgKey ]['col'] = 2;
			}

			if( $counterImg == 12 ){
				$counterImg = 0;
			}
			
		}
		return $items;
	}
}