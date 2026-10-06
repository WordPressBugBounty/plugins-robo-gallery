<?php

defined('WPINC') || exit;
	$colCount = 12;  
	if(isset($options['column'])) $colCount = $options['column'];
	
	$colCountWrap = 12;
	if(isset($options['columnWrap'])) $colCountWrap = $options['columnWrap'];
?>
<div class="field small-<?php echo esc_attr($colCountWrap); ?> columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>
		
	<div class="input-group small-<?php echo esc_attr($colCount); ?>">
		<textarea id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?> name="<?php echo esc_attr($name); ?>" ><?php echo esc_textarea( $value ); ?></textarea>
	</div>	

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
