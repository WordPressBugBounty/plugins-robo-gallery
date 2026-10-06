<?php defined('WPINC') || exit; ?>
<div class="field small-8 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<input id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
	       type="text" name="<?php echo esc_attr($name); ?>"
	       value="<?php echo esc_attr( $value ); ?>" >

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
