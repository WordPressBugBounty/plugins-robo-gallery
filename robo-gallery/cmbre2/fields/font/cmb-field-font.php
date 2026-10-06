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


function rbs_size_get_font_params_row( $value,  $text, $name, $curent , $demoId  ) {

	$html = '';
	$html .= '<label class="btn btn-info '.($value==$curent?'active':'').'">';
	 	$html .= '<input type="checkbox" '
	 		.'class="rbs_fontParams" '
	 		.'autocomplete="off" '
	 		.'name="'.esc_attr($name).'" '
	 		.'data-font-demoid="'.esc_attr($demoId).'" '
	 		.'data-font-option="'.esc_attr($value).'" '
	 		.($value==$curent?' checked ':'').' '
	 		.' value="'.esc_attr($value).'"> ';
	 	$html .= esc_html($text) ;
	$html .= '</label>';
	return $html;
}


function jt_cmbre2_font_field( $metakey, $post_id = 0 ) {
	echo wp_kses( jt_cmbre2_get_font_field( $metakey, $post_id ), cmbre2_form_allowed_html() );
}

function jt_cmbre2_render_font_field_callback( $field, $value, $object_id, $object_type, $field_type_object ) {
	$default = $field->args('default');

	$level = $field->args('level')?1:0;

	$value = wp_parse_args( $value, array(
		'enabled'		=> isset($default['enabled']) 		? $default['enabled'] 				:'0',
		'fontSize' 		=> isset($default['fontSize']) 		? $default['fontSize'] 				:'12',

		'fontLineHeight'=> isset($default['fontLineHeight'])? $default['fontLineHeight'] 		:'100',
		
		'fontBold' 		=> isset($default['fontBold']) 		? $default['fontBold'] 				:'normal',
		'fontItalic' 	=> isset($default['fontItalic']) 	? $default['fontItalic'] 			:'normal',
		'fontUnderline' => isset($default['fontUnderline']) ? $default['fontUnderline'] 		:'none',
		'iconSelect'	=> isset($default['iconSelect']) 	? $default['iconSelect'] 			:'glyphicon-new-window',
		'borderSize'	=> 0,
		
		'color' 		=> isset($default['color']) 		? $default['color'] 				:'#ffffff',
		'colorHover'	=> isset($default['colorHover']) 	? $default['colorHover'] 			:'#ffffff',
		'colorBg'		=> isset($default['colorBg']) 		? $default['colorBg'] 				:'#e54028',
		'colorBgHover'	=> isset($default['colorBgHover']) 	? $default['colorBgHover'] 			:'#b73725',
	) );
	
?>
<div class="form-horizontal">

	<div class="form-group">
	    <label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'enabled' ) ); ?>'">
	    	<?php echo esc_html( $field_type_object->_text( 'font_hfont_text', 'Show' ) ); ?>
	    </label>
	     <div class="col-sm-<?php echo $level?'8 rbs_disabled':'10'; ?>">
	     	<?php
			echo 
				'<input type="checkbox" data-toggle="toggle" data-onstyle="info" class="rbs_action_element" ' 
				.'name="'.esc_attr( $field_type_object->_name('[enabled]') ).'" '
				.'id="'.esc_attr( $field_type_object->_id('enabled') ).'" '
				.( $value['enabled'] ? ' checked ' : '' )
				.'value="1" '
				.'data-font-demoid="'.esc_attr( $field_type_object->_id( 'color' ) ).'" '
				.'data-depends=".'.esc_attr( $field_type_object->_id( 'optionsBlok' ) ).'" '
				.'>';
			 ?>
	    </div>
	    <?php if($level){ ?>
		    <div class="col-sm-2 rbs-block-pro"><?php echo wp_kses_post( ROBO_GALLERY_LABEL_PRO ); ?></div>
		<?php } ?>
	</div>

	<div class="<?php echo esc_attr( $field_type_object->_id( 'optionsBlok' ) );?>">

	<?php if( $field->args('icon') ){ ?>
		<div class="form-group">		
			<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'iconSelect' ) ); ?>'"><?php echo esc_html( $field_type_object->_text( 'font_icon_text', 'Icon' ) ); ?></label>
			<div class="col-sm-1">
				<button class="btn btn-default" role="iconpicker" data-icon="<?php echo esc_attr( $value['iconSelect'] );?>" data-inputid="<?php echo esc_attr( $field_type_object->_id('iconSelect') );?>" data-search="false"></button>
			</div>
			<div class="col-sm-4">
				 <?php echo wp_kses( $field_type_object->input( array(
							'name'  => $field_type_object->_name('[iconSelect]'),
							'id'    => $field_type_object->_id('iconSelect'),
							'value' => $value['iconSelect'],
							'type'  => 'text',
							'class'	=> 'form-control col-sm-2'
						) ), cmbre2_form_allowed_html() ); 
					?>
			</div>
		</div>
	<?php } else {   ?>
		<div class="form-group">
		    <label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( '_hfont' ) ); ?>'"><?php echo esc_html( $field_type_object->_text( 'font_hfont_text', 'Font Style' ) ); ?></label>
		    <div class="col-sm-10">

		    	<div class="btn-group " data-toggle="buttons"> <!-- rbs_checkbox -->
				<?php
					echo wp_kses( rbs_size_get_font_params_row( 'bold',  	'Bold',			$field_type_object->_name('[fontBold]'), 		$value['fontBold'] , 		$field_type_object->_id( 'demo' )), cmbre2_form_allowed_html() );
					echo wp_kses( rbs_size_get_font_params_row( 'italic',  	'Italic', 		$field_type_object->_name('[fontItalic]'), 		$value['fontItalic'], 		$field_type_object->_id( 'demo' ) ), cmbre2_form_allowed_html() );
					echo wp_kses( rbs_size_get_font_params_row( 'underline',  'Underline',	$field_type_object->_name('[fontUnderline]'), 	$value['fontUnderline'], 	$field_type_object->_id( 'demo' ) ), cmbre2_form_allowed_html() );
				 ?>
				</div>
		    </div>
		</div>
	
	<?php }   ?>

	  	<div class="form-group">
	    	<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'fontSize' ) ); ?>'"><?php echo esc_html( $field_type_object->_text( 'font_vfont_text', 'Font Size' ) ); ?></label>
		    <div class="col-sm-10">
		      <?php echo wp_kses( $field_type_object->input( array(
							'name'  			=> $field_type_object->_name( '[fontSize]' ),
							'id'    			=> $field_type_object->_id( 'fontSize' ),
							'value' 			=> (int) $value['fontSize'],
							'data-slider-value' => (int) $value['fontSize'],
							'type'  			=> 'text',
							'class' 			=> 'small-text rbs_slider rbs_font_slider rbs_font_size',
							'data-slider-min'	=> 5,
							'data-slider-max'	=> 50,
							'data-slider-step'	=> 1,
							'data-font-demoid'	=> !$field->args('icon') ? $field_type_object->_id( 'demo' ):'',
					) ), cmbre2_form_allowed_html() ); 
				?> px
		    </div>
	  	</div>

	  	<div class="form-group">
	    	<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'fontLineHeight' ) ); ?>'"><?php echo esc_html( $field_type_object->_text( 'font_vfont_text', 'Line Height' ) ); ?></label>
		    <div class="col-sm-10">
		      <?php echo wp_kses( $field_type_object->input( array(
							'name'  			=> $field_type_object->_name( '[fontLineHeight]' ),
							'id'    			=> $field_type_object->_id( 'fontLineHeight' ),
							'value' 			=> (int) $value['fontLineHeight'],
							'data-slider-value' => (int) $value['fontLineHeight'],
							'type'  			=> 'text',
							'class' 			=> 'small-text rbs_slider rbs_font_slider rbs_font_line ',
							'data-slider-min'	=> 50,
							'data-slider-max'	=> 300,
							'data-slider-step'	=> 1,
							'data-font-demoid'	=> !$field->args('icon') ? $field_type_object->_id( 'demo' ):'',
					) ), cmbre2_form_allowed_html() ); 
				?> %
		    </div>
	  	</div>

<?php if( $field->args('icon') ){ ?>
		<div class="form-group">
	    	<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'borderSize' ) ); ?>'"><?php echo esc_html( $field_type_object->_text( 'font_borderSize_text', 'Border Size' ) ); ?></label>
		    <div class="col-sm-10">
		      <?php echo wp_kses( $field_type_object->input( array(
							'name'  			=> $field_type_object->_name( '[borderSize]' ),
							'id'    			=> $field_type_object->_id( 'borderSize' ),
							'value' 			=> (int) $value['borderSize'],
							'data-slider-value' => (int) $value['borderSize'],
							'type'  			=> 'text',
							'class' 			=> 'small-text rbs_slider rbs_font_slider',
							'data-slider-min'	=> 0,
							'data-slider-max'	=> 30,
							'data-slider-step'	=> 1,
					) ), cmbre2_form_allowed_html() ); 
				?> px
		    </div>
	  	</div>
<?php }   ?>


	  	<div class="form-group">
	  		<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'color' ) ); ?>'">
	  			<?php echo esc_html( $field_type_object->_text( 'font_color_text', 'Color' ) ); ?>
	  		</label>
		    <div class="col-sm-4">
		      <?php 
				echo wp_kses( $field_type_object->input( array(
					'name'  		=> $field_type_object->_name( '[color]' ),
					'id'    		=> $field_type_object->_id( 'color' ),
					'class'         => 'form-control rbs_color rbs_font_color',
					'data-default' 	=> $value['color'],
					'data-alpha'    => 'true',
					'value' 		=> $value['color'],
					'data-demo-id'	=> !$field->args('icon') ? $field_type_object->_id( 'demo' ):'',
				)), cmbre2_form_allowed_html() ); 
			?> 
		    </div>
	  	</div>

	
	  	<div class="form-group">
	  		<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'colorHover' ) ); ?>'">
	  			<?php echo esc_html( $field_type_object->_text( 'font_color_text', 'Hover Color' ) ); ?>
	  		</label>
		    <div class="col-sm-4">
		      <?php 
				echo wp_kses( $field_type_object->input( array(
					'name'  		=> $field_type_object->_name( '[colorHover]' ),
					'id'    		=> $field_type_object->_id( 'colorHover' ),
					'class'         => 'form-control rbs_color rbs_font_color',
					'data-default' 	=> $value['colorHover'],
					'data-alpha'    => 'true',
					'value' 		=> $value['colorHover']
				)), cmbre2_form_allowed_html() ); 
			?> 
		    </div>
	  	</div>
	<?php if( $field->args('icon') ){ ?>
	  	<div class="form-group">
	  		<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'colorBg' ) ); ?>'">
	  			<?php echo esc_html( $field_type_object->_text( 'font_color_text', 'Bg Color' ) ); ?>
	  		</label>
		    <div class="col-sm-4">
		      <?php 
				echo wp_kses( $field_type_object->input( array(
					'name'  		=> $field_type_object->_name( '[colorBg]' ),
					'id'    		=> $field_type_object->_id( 'colorBg' ),
					'class'         => 'form-control rbs_color rbs_font_color',
					'data-default' 	=> $value['colorBg'],
					'data-alpha'    => 'true',
					'value' 		=> $value['colorBg']
				)), cmbre2_form_allowed_html() ); 
			?> 
		    </div>
	  	</div>

	  	<div class="form-group">
	  		<label class="col-sm-2 control-label" for="<?php echo esc_attr( $field_type_object->_id( 'colorBgHover' ) ); ?>'">
	  			<?php echo esc_html( $field_type_object->_text( 'font_color_text', 'Bg Color Hover' ) ); ?>
	  		</label>
		    <div class="col-sm-4">
		      <?php 
				echo wp_kses( $field_type_object->input( array(
					'name'  		=> $field_type_object->_name( '[colorBgHover]' ),
					'id'    		=> $field_type_object->_id( 'colorBgHover' ),
					'class'         => 'form-control rbs_color rbs_font_color',
					'data-default' 	=> $value['colorBgHover'],
					'data-alpha'    => 'true',
					'value' 		=> $value['colorBgHover'],
				)), cmbre2_form_allowed_html() ); 
			?> 
		    </div>
	  	</div>
	<?php } else { ?>
	  	<div class="form-group">
	  	<?php echo '
	  		<div class="col-sm-8 col-sm-offset-2 rbs_hover_demo" '
	  		.'id="'.esc_attr( $field_type_object->_id( 'demo' ) ).'" '
	  		.'style="'
	  			.'color: '.esc_attr( \RoboGallery\app\extensions\validation\CssColor::sanitize( $value['color'], '#ffffff' ) ).'; '
	  			.'font-size:'.(int) $value['fontSize'].'px; '
	  			.'line-height:'.(int) $value['fontLineHeight'].'%; '
	  			.'font-weight:'		.($value['fontBold']=='bold'			?'bold'		:'normal')	.'; '
				.'font-style:'		.($value['fontItalic']=='italic'		?'italic'	:'normal')	.'; '
	  			.'text-decoration:'	.($value['fontUnderline']=='underline'	?'underline':'none')	.'; '
	  		.'">'
	  		.'Demo Text'  
			.'</div>';
		?> 
	  	</div>
	<?php } ?>
	 </div>
</div>
<?php
}
add_filter( 'cmbre2_render_font', 'jt_cmbre2_render_font_field_callback', 10, 5 );