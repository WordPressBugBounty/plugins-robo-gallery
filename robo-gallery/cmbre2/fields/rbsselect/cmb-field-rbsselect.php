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


function rbs_rbsselect_get_options( $options, $value = false, $content=array() ) {
    $state_options = '';
    foreach ( $options as $abrev => $state ) {
        $state_options .= 
       	'<option '
        	.'value="'. esc_attr($abrev) .'" '
        	// HTML for bootstrap-select; the browser decodes the attribute back for its script
        	.(isset($content[$abrev])?' data-content="'.esc_attr($state.' '.str_replace('"', "'", $content[$abrev])).'"':'')
        	.($value==$abrev?' selected="selected"':'')
        .'>'. esc_html($state) .'</option>';
    }
    return $state_options;
}

function jt_cmbre2_render_rbsselect_field_callback( $field, $value, $object_id, $object_type, $field_type_object ){
	$value =  $value?$value:$field->args('default');
	$level = $field->args('level')?1:0;
	?>
	<div class="form-horizontal">
		<div class="form-group">
	    	<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id() ); ?>"><?php echo esc_html( $field->args( 'name' ) ); ?></label>
		    <div class="col-sm-<?php echo $level?'8 rbs_disabled':'10'; ?>">
		      <?php
		      	echo wp_kses( $field_type_object->select(array(
					'name'  		=> $field_type_object->_name(),
					'id'    		=> $field_type_object->_id(),
					'class'   		=> 'rbs_select form-control '.($field->args('depends') && count($field->args('depends'))?' rbs_action_element_select':''),  //selectpicker
					'options' 		=> rbs_rbsselect_get_options( $field->args('options'),  $value, $field->args('content') ),
					'data-depends'	=> $field->args('depends') && count($field->args('depends')) ? 1 : 0 ,
					'desc'    		=> $field_type_object->_desc( true ),
				)), cmbre2_form_allowed_html() );

				if( $field->args('depends') && count($field->args('depends')) ){
				?>
				<script type="text/javascript">
					var  <?php echo esc_attr( $field_type_object->_id() ); ?>_depends = <?php echo wp_json_encode($field->args('depends')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a script ?>;
				</script>
				<?php } ?>
		    </div>
		    <?php if($level){ ?>
		    	<div class="col-sm-2 rbs-block-pro"><?php echo wp_kses_post( ROBO_GALLERY_LABEL_PRO ); ?></div>
		    <?php } ?>
	  	</div>
	</div>
<?php
}
add_filter( 'cmbre2_render_rbsselect', 'jt_cmbre2_render_rbsselect_field_callback', 10, 5 );