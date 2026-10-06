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

// "Video Guides" box in the gallery editor sidebar: one random guide per page load
$rbs_gallery_guides = array(
	array( 'https://www.youtube.com/watch?v=3vBl9Ke6bsg', __( 'How to install key?', 'robo-gallery' ), 'green' ),
	array( 'https://www.youtube.com/watch?v=DdCpRuLFxzk', __( 'How to make custom grid layout?', 'robo-gallery' ), 'violet' ),
	array( 'https://www.youtube.com/watch?v=-CuGOo7XRmQ', __( 'New Categories Manager', 'robo-gallery' ), 'green' ),
	array( 'https://www.youtube.com/watch?v=mZ_yOXkxRsk', __( 'How to setup Polaroid style?', 'robo-gallery' ), 'violet' ),
	array( 'https://www.youtube.com/watch?v=m9XIeqMnhYI', __( 'Install and configuration guide', 'robo-gallery' ), 'green' ),
	array( 'https://www.youtube.com/watch?v=RrWn8tMuKsw', __( 'How to manage gallery post?', 'robo-gallery' ), 'violet' ),
	array( 'https://www.youtube.com/watch?v=fI3uYOlUbo4', __( 'How to upload gallery images?', 'robo-gallery' ), 'green' ),
	array( 'https://www.youtube.com/watch?v=lxDR6E8erBA', __( 'How to create shortcode?', 'robo-gallery' ), 'violet' ),
);
list( $rbs_guide_link, $rbs_guide_text, $rbs_guide_color ) = $rbs_gallery_guides[ array_rand( $rbs_gallery_guides ) ];

$guides_group = new_cmbre2_box( array(
	'id'           => ROBO_GALLERY_PREFIX . 'guides_metabox',
	'title'        => __( 'Video Guides', 'robo-gallery' ),
	'object_types' => array( ROBO_GALLERY_TYPE_POST ),
	'context'      => 'side',
	'priority'     => 'high',
	'show_names'   => false,
) );

$guides_group->add_field( array(
	'id'         => ROBO_GALLERY_PREFIX . 'guide_desc',
	'type'       => 'title',
	'before_row' => '<a href="' . esc_url( $rbs_guide_link ) . '" target="_blank" rel="noopener" class="rbs_guide rbs_guide_' . esc_attr( $rbs_guide_color ) . '">'
		. esc_html( $rbs_guide_text ) . '</a>',
	'after_row'  => '',
) );
