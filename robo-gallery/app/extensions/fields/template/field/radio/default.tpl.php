<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<fieldset>
		<?php if ($label) : ?>
			<legend><?php echo wp_kses_post($label); ?></legend>
		<?php endif; ?>

		<?php foreach ($options['values'] as $key => $item) : ?>
			<?php $idElement = "{$id}_{$key}"; ?>
			<input id="<?php echo esc_attr($idElement); ?>"
			       type="radio" name="<?php echo esc_attr($name); ?>"
			       value="<?php echo esc_attr($item['value']); ?>" <?php echo $item['value'] === $value ? 'checked' : ''; ?>
			       data-dependents='<?php echo esc_attr($dependents); ?>' >
			<label for="<?php echo esc_attr($idElement); ?>"><?php echo wp_kses_post($item['label']); ?></label>
		<?php endforeach; ?>
	</fieldset>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
