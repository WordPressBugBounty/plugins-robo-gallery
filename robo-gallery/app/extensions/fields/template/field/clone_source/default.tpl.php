<?php

defined('WPINC') || exit;
	$colCount = 12;
	if(isset($options['column'])) $colCount = $options['column'];
	?>
<div class="field small-12 columns">
	<?php if ($label) : ?>
		<label for="field-select-<?php echo esc_attr($id); ?>">
			<?php echo wp_kses_post($label); ?>
		</label>
	<?php endif; ?>

	<select id="field-select-<?php echo esc_attr($id); ?>"
			class="input-group small-<?php echo esc_attr($colCount);?> "
			name="<?php echo esc_attr($name); ?>" >
		<?php foreach ($options['values'] as $optionValue => $optionTitle) : ?>
			<option value="<?php echo (int) $optionValue; ?>" <?php selected( (int) $value, (int) $optionValue ); ?>>
				<?php
				// a gallery with its ID: titles can repeat or be empty
				if ($optionValue && '' === trim((string) $optionTitle)) {
					$optionTitle = __('(no title)'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
				}
				echo esc_html( $optionValue ? $optionTitle . ' [' . (int) $optionValue . ']' : $optionTitle );
				?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
