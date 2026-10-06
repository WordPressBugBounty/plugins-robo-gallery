<?php
/*
*      Robo Gallery By Robosoft
*      Contact: https://robosoft.co/robogallery/ 
*      Copyright (c) 2014-2019, Robosoft. All rights reserved.
*/

if ( ! defined( 'WPINC' ) ) exit;


/**
 * The compiled CSS files (frontend/modules/class/scss.php) are named
 * robo_gallery_css_id<gallery>_<cache_id>.css. A new cache_id makes a new file,
 * so the old ones are removed here instead of piling up in cache/css/.
 */
function robo_gallery_delete_css_files( $pattern ) {
	$files = glob( ROBO_GALLERY_CACHE_CSS_PATH . $pattern );
	if ( ! is_array( $files ) ) return;

	foreach ( $files as $file ) {
		if ( is_file( $file ) ) wp_delete_file( $file );
	}
}

function robo_gallery_save_gallery( $post_id, $post, $update ) {

    $post_type = get_post_type($post_id);

    if ( ROBO_GALLERY_TYPE_POST != $post_type ) return;
    /* delete db_cache */
    delete_transient( ROBO_GALLERY_PREFIX.'cache_id'. $post_id );

    /* the CSS of the old cache id: this gallery's and of the galleries that clone its
       settings (they use the source's cache id); the id goes into a glob pattern */
    foreach ( (array) get_post_meta( $post_id, ROBO_GALLERY_PREFIX.'cache_id' ) as $old_cache_id ) {
        if ( is_string( $old_cache_id ) && preg_match( '/^[a-zA-Z0-9]{1,32}$/', $old_cache_id ) ) {
            robo_gallery_delete_css_files( 'robo_gallery_css_id*_' . $old_cache_id . '.css' );
        }
    }

    /* delete cache id */
    delete_post_meta( $post_id, ROBO_GALLERY_PREFIX.'cache_id' );
    /* set new cache id */
    add_post_meta( $post_id, ROBO_GALLERY_PREFIX.'cache_id', uniqid() );
}
add_action( 'save_post', 'robo_gallery_save_gallery', 10, 3 );


function robo_gallery_new_gallery( $post_id, $post, $update ) {

	if( wp_is_post_revision( $post_id ) ) return;

	$post_type = get_post_type($post_id);
    if ( ROBO_GALLERY_TYPE_POST != $post_type ) return;

	add_post_meta($post_id, ROBO_GALLERY_PREFIX.'cache_id', uniqid() );

}
add_action( 'wp_insert_post', 'robo_gallery_new_gallery', 10, 3 );


function robo_gallery_delete_gallery_css( $post_id ) {
	if ( ROBO_GALLERY_TYPE_POST != get_post_type( $post_id ) ) return;
	robo_gallery_delete_css_files( 'robo_gallery_css_id' . (int) $post_id . '_*.css' );
}
add_action( 'before_delete_post', 'robo_gallery_delete_gallery_css' );

