<?php 

defined('WPINC') || exit;
	$colCount = 12;  
	if(isset($options['column'])) $colCount = $options['column'];
	
	$colCountWrap = 12;
	if(isset($options['columnWrap'])) $colCountWrap = $options['columnWrap'];
?>
<div id="field-div-<?php echo esc_attr($id); ?>" 
		class="field small-<?php echo esc_attr($colCountWrap);?> columns" 
		<?php if( isset($options['hide']) ) echo 'style="display:none;"';?>
	>
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<div id="<?php echo esc_attr("field-element-{$id}"); ?>" class="input-group small-<?php echo esc_attr($colCount);?> ">
		<?php if (isset($options['leftLabel'])) : ?>
			<span class="input-group-label">
				<?php echo wp_kses_post($options['leftLabel']); ?>
			</span>
		<?php endif; ?>

		<input id="<?php echo esc_attr($id); ?>" class="input-group-field" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
		       type="text" name="<?php echo esc_attr($name); ?>"
		       value="<?php echo esc_attr( $value ); ?>" >

		<?php if (isset($options['rightLabel'])) : ?>
			<span class="input-group-label">
				<?php echo wp_kses_post($options['rightLabel']); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
