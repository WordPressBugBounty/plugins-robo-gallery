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

if( empty($_GET['post'])  ) return array();

// every type
$shortcodeTypes = \RoboGallery\app\extensions\galleryType\GalleryTypeList::getTypes();

$shortcode = '[robo-gallery id="' . (int) $_GET['post'] . '"]';

return array(
	'active' 	=> true,
	'order' 	=> 1,
	'settings' 		=> array(
		'id' 		=> 'robo_gallery_shortcode',
		'title' 	=> __('Gallery Shortcode', 'robo-gallery'),
		'screen' 	=> array( ROBO_GALLERY_TYPE_POST ),
		'for' 		=> array( 'gallery_type' => $shortcodeTypes ),
		'context' 	=> 'side',
		'priority' 	=> 'low',
	),
	'view' 	=> 'default',
	'state' => 'open',
	// the copy button is handled in asset/core/js/app.js
	'content' => sprintf(
		'<div class="robo-gallery-shortcode">%1$s</div>
		 <div class="robo-gallery-shortcode-copy-wrap">
			<button type="button" class="robo-gallery-shortcode-copy" data-shortcode="%2$s" data-copied="%3$s">%4$s</button>
			<span class="robo-gallery-shortcode-copied" aria-live="polite"></span>
		 </div>
		 <div class="robo-gallery-shortcode-desc">%5$s</div>',
		esc_html( $shortcode ),
		esc_attr( $shortcode ),
		esc_attr__( 'Copied!', 'robo-gallery' ),
		esc_html__( 'Copy shortcode', 'robo-gallery' ),
		esc_html__( 'use this shortcode to insert this gallery into page, post or widget', 'robo-gallery' )
	)
);
