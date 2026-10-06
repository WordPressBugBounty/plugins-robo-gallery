<?php 

defined('WPINC') || exit;
	$colCount = 12;  
	if(isset($options['column'])) $colCount = $options['column'];
	
	$colCountWrap = 12;
	if(isset($options['columnWrap'])) $colCountWrap = $options['columnWrap'];
	?>
<div class="field small-<?php echo esc_attr($colCountWrap);?> columns">
	<?php if ($label) : ?>
		<label for="field-select-<?php echo esc_attr($id); ?>">
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<select id="field-select-<?php echo esc_attr($id); ?>" 
			<?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
			class="input-group small-<?php echo esc_attr($colCount);?> "
		    name="<?php echo esc_attr($name); ?>"
		    data-dependents='<?php echo esc_attr($dependents); ?>' >
		<?php foreach ($options['values'] as $optionValue => $optionLabel) : ?>
			<option 			
				value="<?php echo esc_attr($optionValue); ?>" 
				<?php if ($optionValue == $value) { echo 'selected'; } ?>
				 <?php if (!empty($options['disabled']) && is_array($options['disabled']) && in_array($optionValue, $options['disabled']) ) { echo 'disabled'; } ?>
			>
				<?php echo esc_html($optionLabel); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
