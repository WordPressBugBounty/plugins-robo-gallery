<?php defined('WPINC') || exit; ?>
<div class="field small-10 columns">
	<div class="row">
		<div class="field small-3 columns">
			<?php if ($label) : ?>
				<label class="text-right middle">
					<?php echo wp_kses_post($label); ?>
				</label>
			<?php endif; ?>
		</div>

		<div class="field small-9 columns">
			<input id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
			       type="text" name="<?php echo esc_attr($name); ?>"
			       value="<?php echo esc_attr( $value ); ?>" >
		</div>

		<?php if ($description) : ?>
			<div class="field small-12 columns">
				<p class="help-text"><?php echo wp_kses_post($description); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
