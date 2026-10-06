<?php defined('WPINC') || exit; ?>

	<div class="field small-12 columns <?php if($is_sub_field) echo 'wrap-field checkbox-group-button'; ?>">
		<fieldset>
			<?php if ($label) : ?>
				<legend><?php echo wp_kses_post($label); ?></legend>
			<?php endif; ?>

			<?php foreach ($options['values'] as $key => $item) : 
				$idElement = "{$id}_{$key}"; ?>
				<div id="field-element-<?php echo esc_attr($idElement); ?>" class="button-element">
					<input id="<?php echo esc_attr($idElement); ?>"
					       type="checkbox" name="<?php echo esc_attr("{$name}[{$item['name']}]"); ?>"
					       value="1" <?php echo isset($value[$item['name']]) && $value[$item['name']] ? 'checked' : '' ?>
					       data-dependents='<?php echo esc_attr($dependents); ?>' >
					<label class="button" for="<?php echo esc_attr($idElement); ?>">
						<?php echo wp_kses_post($item['label']); ?>
					</label>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<?php if ($description) : ?>
			<p class="help-text"><?php echo wp_kses_post($description); ?></p>
		<?php endif; ?>
	</div>
