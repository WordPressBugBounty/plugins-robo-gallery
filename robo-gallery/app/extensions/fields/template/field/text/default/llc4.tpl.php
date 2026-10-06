<?php defined('WPINC') || exit; ?>
<div class="field small-4 columns">
	<div class="row">
		<?php if ($label) : ?>
			<div class="field small-4 columns">
				<label class="text-right middle">
					<?php echo wp_kses_post($label); ?>
				</label>
			</div>
		<?php endif; ?>
		
		<div class="field small-12 columns">
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
