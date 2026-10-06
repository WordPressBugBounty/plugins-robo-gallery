<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>
	
	<div class="field small-2 columns"><strong>Screen size</strong></div>
	<div class="field small-3 columns"> <strong> Auto size</strong></div>
	<hr />
	<div id="<?php echo esc_attr($id); ?>" class="field small-12 columns" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>>
			<?php echo $fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sub-fields rendered (and escaped) by their own templates ?>
	</div>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
