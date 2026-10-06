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

// every type: all layouts (base grid, slider, simple, Fusion Grid) output the Begin / End content
$textAddonsTypes = \RoboGallery\app\extensions\galleryType\GalleryTypeList::getTypes();

/*
 * Meta rsg_pretext / rsg_aftertext: HTML shown before / after the gallery
 * (includes/frontend/modules/class/addtexts.php, printed through wp_kses_post()).
 * $_POST is slashed here, so the slashed-in / slashed-out variant of the same
 * filter is used; update_post_meta() unslashes it.
 */
return array(
	'active' => true,
	'order'  => 2,
	'settings' => array(
		'id'       => 'robo_gallery_text_addons',
		'title'    => __( 'Text Addons', 'robo-gallery' ),
		'screen'   => array( ROBO_GALLERY_TYPE_POST ),
		'for'      => array( 'gallery_type' => $textAddonsTypes ),
		'context'  => 'side',
		'priority' => 'low',
	),
	'view'  => 'default',
	'state' => 'open',
	'fields' => array(
		array(
			'type'        => 'textarea',
			'view'        => 'default',
			'name'        => 'pretext',
			'label'       => __( 'Pre Text', 'robo-gallery' ),
			'default'     => '',
			'cb_sanitize' => 'wp_filter_post_kses',
			'attributes'  => array( 'rows' => 5 ),
		),
		array(
			'type'        => 'textarea',
			'view'        => 'default',
			'name'        => 'aftertext',
			'label'       => __( 'After Text', 'robo-gallery' ),
			'default'     => '',
			'cb_sanitize' => 'wp_filter_post_kses',
			'attributes'  => array( 'rows' => 5 ),
		),
	),
);
