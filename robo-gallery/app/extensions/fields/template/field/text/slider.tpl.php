<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<?php if ($label) : ?>
		<label>
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>
</div>

<?php
	$textBeforeColumns = empty($options['textBefore']) ? 0 : 2;
	$textAfterColumns = empty($options['textAfter']) ? 0 : 2;
	$textColumns = 2;
	$sliderColumns = 12 - $textBeforeColumns - $textAfterColumns - $textColumns;
?>

<?php if (!empty($options['textBefore'])) : ?>
	<div class="field columns small-<?php echo esc_attr($textBeforeColumns); ?> text-before">
		<?php echo wp_kses_post($options['textBefore']); ?>
	</div>
<?php endif; ?>

<div class="field columns small-<?php echo esc_attr($textColumns); ?>">
	<input id="<?php echo esc_attr($id); ?>" <?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- name="value" pairs, each value esc_attr()'d in roboGalleryFieldsField::getData() ?>
	       type="number" name="<?php echo esc_attr($name); ?>"
	       value="<?php echo esc_attr( $value ); ?>" >
</div>

<?php if (!empty($options['textAfter'])) : ?>
	<div class="field columns small-<?php echo esc_attr($textAfterColumns); ?> text-after">
		<?php echo wp_kses_post($options['textAfter']); ?>
	</div>
<?php endif; ?>

<div class="field small-<?php echo esc_attr($sliderColumns); ?> columns">
	<div class="slider" data-slider
	     data-initial-start="<?php echo esc_attr( $value ); ?>"
	     data-start="<?php echo esc_attr($options['data-start']); ?>"
	     data-end="<?php echo esc_attr($options['data-end']); ?>"
	     data-step="<?php echo esc_attr($options['step']); ?>">
		<span class="slider-handle" data-slider-handle role="slider" tabindex="1"
		      aria-controls="<?php echo esc_attr($id); ?>"></span>
		<span class="slider-fill" data-slider-fill></span>
	</div>
</div>

<div class="field small-12 columns">
	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
