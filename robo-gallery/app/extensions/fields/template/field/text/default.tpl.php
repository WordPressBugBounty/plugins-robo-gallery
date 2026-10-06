<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<div class="row">
		<?php
			$leftColumns = empty($options['textBefore']) ? 0 : 3;
			$rightColumns = empty($options['textAfter']) ? 0 : 3;
			$centerColumns = 12 - $leftColumns - $rightColumns;
		?>

		<?php if (!empty($options['textBefore'])) : ?>
		<div class="columns small-<?php echo esc_attr($leftColumns); ?> text-before">
			<?php echo wp_kses_post($options['textBefore']); ?>
		</div>
		<?php endif; ?>

		<div class="columns small-<?php echo esc_attr($centerColumns); ?>">
			<input id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
			       type="text" name="<?php echo esc_attr($name); ?>"
			       value="<?php echo esc_attr( $value ); ?>" >
		</div>

		<?php if (!empty($options['textAfter'])) : ?>
			<div class="columns small-<?php echo esc_attr($rightColumns); ?> text-after">
				<?php echo wp_kses_post($options['textAfter']); ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
