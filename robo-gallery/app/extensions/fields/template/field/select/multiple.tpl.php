<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<?php
	// a stored value that isn't a list (the default, raw meta) selects nothing
	$selectedValues = is_array($value) ? array_map('strval', array_filter($value, 'is_scalar')) : array();
	?>
	<select id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?> multiple
	        name="<?php echo esc_attr($name); ?>[]" >
		<?php foreach ($options['values'] as $optionValue => $optionLabel) : ?>
			<option value="<?php echo esc_attr($optionValue); ?>" <?php if (in_array((string) $optionValue, $selectedValues, true)) { echo 'selected'; } ?>>
				<?php echo esc_html($optionLabel); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
