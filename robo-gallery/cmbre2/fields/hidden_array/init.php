<?php
/*
*      Robo Gallery     
*      Version: 1.0
*      By Robosoft
*
*      Contact: https://robosoft.co/robogallery/ 
*      Created: 2015
*      Licensed under the GPLv2 license - http://opensource.org/licenses/gpl-2.0.php
*
*      Copyright (c) 2014-2019, Robosoft. All rights reserved.
*      Available only in  https://robosoft.co/robogallery/ 
*/

defined('WPINC') || exit;


/*function jt_cmbre2_shadow_field( $metakey, $post_id = 0 ) {
	echo wp_kses( jt_cmbre2_get_shadow_field( $metakey, $post_id ), cmbre2_form_allowed_html() );
}*/

function robo_gallery_render_hidden_array_field_callback( $field, $value, $object_id, $object_type, $field_type_object ){
	
	//print_r($object_type);
	//print_r($object_id);

	if( is_array($value) && count($value) ){
		foreach ($value as $key => $val){

			echo wp_kses( $field_type_object->input( array(
							'name'  => $field_type_object->_name( '['.$key.']' ),
							'id'    => $field_type_object->_id( '_'.$key ),
							'value' => $val,						
							'type'  => 'hidden',
			) ), cmbre2_form_allowed_html() );

		}
	}
}


add_filter( 'cmbre2_render_hidden_array', 'robo_gallery_render_hidden_array_field_callback', 10, 5 );