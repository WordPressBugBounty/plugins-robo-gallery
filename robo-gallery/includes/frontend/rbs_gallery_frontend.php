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

// A gallery's own page shows the gallery after its (usually empty) content.
function roboGalleryTag($content){
	// excerpts (search results, archives, meta descriptions) run the_content too:
	// no full gallery render, assets and view count for them
	if( doing_filter( 'get_the_excerpt' ) ) return $content;
	if( post_password_required() ) return $content;
	if( get_post_type() != ROBO_GALLERY_TYPE_POST || !is_main_query() ) return $content;
	return $content.do_shortcode( '[robo-gallery id=' . (int) get_the_ID() . ']' );
}
add_filter( 'the_content', 'roboGalleryTag');


function robo_gallery_shortcode( $attr ) { 	
	if( !isset($attr) || !isset($attr['id']) ) return '';
	
	$attr['id'] = (int) $attr['id'];
	if( !$attr['id'] ) return '';

	/* The same access rules as the gallery's own page (canView(): WP status and
	   password, direct-link / token mode), so embedding the shortcode elsewhere
	   can't bypass them. */
	$accessManager = new \RoboGallery\app\extensions\access\GalleryAccessManager();
	if ( ! $accessManager->canView( $attr['id'] ) ) {
		// drafts, WP-private, other post types, token galleries: render nothing;
		// only a missing WP post password gets the password form
		return $accessManager->needsPasswordForm( $attr['id'] ) ? get_the_password_form( $attr['id'] ) : '';
	}

	// who is counted and how: app/extensions/stats/ViewCounter.php
	( new \RoboGallery\app\extensions\stats\ViewCounter() )->count( $attr['id'] );

	$gallery = new roboGallery($attr);
	
	return $gallery->getGallery();	
}
add_shortcode( 'robo-gallery', 'robo_gallery_shortcode' );


