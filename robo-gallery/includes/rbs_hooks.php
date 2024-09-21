<?php
/* @@copyright@@ */

defined('WPINC')||exit;


function robo_gallery_title_hook( $title, $id = null ) {
    if ( get_post_type( $id ) === ROBO_GALLERY_TYPE_POST  ) {
        return esc_html($title);
    }
    return $title;
}
add_filter( 'the_title', 'robo_gallery_title_hook', 10, 2 );