<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<fieldset>
		<?php if ($label) : ?>
			<legend><?php echo wp_kses_post($label); ?></legend>
		<?php endif; ?>

		<?php foreach ($options['values'] as $key => $item) : ?>
			<?php $idElement = "{$id}_{$key}"; ?>
			<input id="<?php echo esc_attr($idElement); ?>"
			       type="checkbox" name="<?php echo esc_attr("{$name}[{$item['name']}]"); ?>"
			       value="1" <?php echo isset($value[$item['name']]) && $value[$item['name']] ? 'checked' : ''; ?>
			       data-dependents='<?php echo esc_attr($dependents); ?>' >
			<label for="<?php echo esc_attr($idElement); ?>"><?php echo wp_kses_post($item['label']); ?></label>
		<?php endforeach; ?>
	</fieldset>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
