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



// "Clone Gallery" choices: app/extensions/cloneSource/CloneSource.php
function robo_gallery_field_getGalleryOptions($galleryId, $value){

	$value = (int) $value;
	$tagOptions = '<option value="0" '.selected( $value, 0, false ).'>'.esc_html__('none').'</option>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress

	$choices = \RoboGallery\app\extensions\cloneSource\CloneSource::choices( (int) $galleryId, $value );
	foreach ( $choices as $id => $title ){
    	$tagOptions .= '<option value="'.(int) $id.'" '.selected( $value, $id, false ).'> '
    	.' &nbsp; '.esc_html($title). ' ['.(int) $id.']'
    	.'</option>';
    }

	return $tagOptions;
}

function jt_cmbre2_render_rbsgallery_field_callback( $field, $value, $object_id, $object_type, $field_type_object ){
	
	$value =  ( (int) $value ) > 0  ? (int)$value : $field->args('default');	
	?>
	
	<div class="form-horizontal">		
		<div class="form-group">
		    <div class="col-sm-12">
		    	<?php echo wp_kses_post( $field->args('desc') ); ?>
		    </div>
	  	</div>

		<div class="form-group">
	    	<label class="col-sm-2  control-label" for="<?php echo esc_attr( $field_type_object->_id() ); ?>"><?php echo esc_html( $field->args( 'name' ) ); ?></label>
		    <div class="col-sm-10">
			     <select name="<?php echo esc_attr( $field_type_object->_name() ); ?>" id="<?php echo esc_attr( $field_type_object->_id() ); ?>" class="rbs_select form-control">
			    	<?php
			    	echo wp_kses( robo_gallery_field_getGalleryOptions( $object_id,  $value ), cmbre2_form_allowed_html() );    	
			    	?>
				</select>
			<?php
		      	 $depends = $field->args('depends');
				if( is_array($depends) && count($depends) ){ ?>
					<script type="text/javascript">
						var  <?php echo esc_attr( $field_type_object->_id() ); ?>_depends = <?php echo wp_json_encode($field->args('depends')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON in a script ?>;
					</script>
				<?php } ?>
		    </div>
		</div>
		
		<div class="form-group">
		    <div class="col-sm-12  ">
		    	<?php echo wp_kses_post( $field->args('desc2') ); ?>
		    </div>
	  	</div>

	</div>
<?php
}
add_filter( 'cmbre2_render_rbsgallery', 'jt_cmbre2_render_rbsgallery_field_callback', 10, 5 );