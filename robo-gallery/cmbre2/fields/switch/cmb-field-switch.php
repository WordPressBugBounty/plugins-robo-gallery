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


function jt_cmbre2_switch_field( $metakey, $post_id = 0 ) {
	echo wp_kses( jt_cmbre2_get_switch_field( $metakey, $post_id ), cmbre2_form_allowed_html() );
}

function jt_cmbre2_render_switch_field_callback( $field, $value, $object_id, $object_type, $field_type_object ) {

	if( empty($value) ) $value = $field->args('default');

	$level = $field->args('level') ? 1 : 0 ;
	$update = $field->args('update');
	

	$onText = $field->args('onText');
	$offText = $field->args('offText');

	$onStyle = $field->args('onStyle');
	$offStyle = $field->args('offStyle');

	if($field->args('showhide')){
		$onText='Show';
		$offText='Hide';
	}

	?>
<div class="form-horizontal">
	<div class="form-group">
	    <label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id() ); ?>'"><?php echo wp_kses_post( $field->args('name') ); ?></label>
	    <div class="col-sm-<?php echo ($level||$update)?'7 rbs_disabled':'10'; ?>">
	<?php 
			 echo
				'<input type="checkbox" data-toggle="toggle" '
				.($onStyle?'  data-onstyle="'.esc_attr($onStyle).'" '		:' data-onstyle="info" ')
				.($offStyle?' data-offstyle="'.esc_attr($offStyle).'" '	:' data-offstyle="default" ')

				.($onText?' data-on="'.esc_attr($onText).'" ':'')
				.($offText?' data-off="'.esc_attr($offText).'" ':'')
				.($field->args('depends')?'class="rbs_action_element" ':'')
				.'name="'.esc_attr( $field_type_object->_name(  ) ).'" '
				.'id="'.esc_attr( $field_type_object->_id( ) ).'" '
				.($field->args('depends')?'data-depends="'.esc_attr($field->args('depends')).'" ':'')
				.( $value==1 ?'checked="checked" ':'')
				.'value="1"> <span class="rbs_desc">'.wp_kses_post($field->args('desc')).'</span>';
			?> 
 		</div>
 		<?php if($level){ ?>
			<div class="col-sm-3 rbs-block-pro"><?php echo wp_kses_post( ROBO_GALLERY_LABEL_PRO ); ?></div>
		<?php } ?>
		<?php if($update){ ?>
			<div class="col-sm-3 rbs-block-update-pro"><?php echo wp_kses_post( ROBO_GALLERY_LABEL_UPDATE_PRO ); ?></div>
		<?php } ?>

	</div>
</div>
<?php
}

add_filter( 'cmbre2_render_switch', 'jt_cmbre2_render_switch_field_callback', 10, 5 );