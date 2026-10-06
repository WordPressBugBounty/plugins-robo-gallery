<?php defined('WPINC') || exit; ?>
<div class="field small-2 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
	<?php endif; ?>

	<select id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
	        name="<?php echo esc_attr($name); ?>"
	        data-dependents='<?php echo esc_attr($dependents); ?>' >
		<?php foreach ($options['values'] as $optionValue => $optionLabel) : ?>
			<option value="<?php echo esc_attr($optionValue); ?>" <?php if ($optionValue == $value) { echo 'selected'; } ?>>
				<?php echo esc_html($optionLabel); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php if ($label) : ?>
		</label>
	<?php endif; ?>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
