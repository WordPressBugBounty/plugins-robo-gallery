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

defined('WPINC') || exit;


function jt_cmbre2_rbstextarea_field( $metakey, $post_id = 0 ) {
	echo wp_kses( jt_cmbre2_get_rbstextarea_field( $metakey, $post_id ), cmbre2_form_allowed_html() );
}

function jt_cmbre2_render_rbstextarea_field_callback( $field, $value, $object_id, $object_type, $field_type_object ) {

	$value =  $value ? trim($value) : $field->args('default') ;
	$hide_label =  $field->args('hide_label')  ? 1 : 0 ;
	?>
	<div class="form-horizontal">
		<div class="form-group">
		<?php if(!$hide_label){ ?>
			<div class="col-sm-2 ">
		    	<label class=" control-label" for="<?php echo esc_attr( $field_type_object->_id() ); ?>"><?php echo esc_html(  $field->args('name') ); ?></label>
		   		<?php echo wp_kses_post( $field_type_object->_desc( true ) );  ?>
		    </div>
		<?php } ?>
		    <div class="<?php echo $hide_label?'col-sm-12':'col-sm-10'; ?>">
		    	<?php
		    		echo '<textarea '
		    				.'id="'.esc_attr( $field_type_object->_id() ).'" '
		    				.'name="'.esc_attr( $field_type_object->_name() ).'" '
		    				.'class="form-control '.esc_attr($field_type_object->args('class')).'" '
		    				.'rows="6">'.esc_textarea($value)
		    		.'</textarea>';
				?> 
		    </div>
		</div>
	</div>
	<?php
}
add_filter( 'cmbre2_render_rbstextarea', 'jt_cmbre2_render_rbstextarea_field_callback', 10, 5 );